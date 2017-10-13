<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */

namespace oat\taoTaskQueue\model;

use oat\oatbox\log\LoggerAwareTrait;
use common_report_Report as Report;
use oat\taoTaskQueue\model\Task\TaskInterface;

/**
 * Processes tasks from the queue.
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
final class Worker implements WorkerInterface
{
    use LoggerAwareTrait;

    private $queue;
    private $maxIterations = 0; //0 means infinite iteration
    private $iterations;
    private $shutdown;
    private $paused;
    private $waitInterval = 1; //sec
    private $processId;
    private $logContext;
    private $taskLog;
    /**
     * @var bool
     */
    private $handleSignals;

    /**
     * @param QueueInterface   $queue
     * @param TaskLogInterface $taskLog
     * @param bool             $handleSignals
     */
    public function __construct(QueueInterface $queue, TaskLogInterface $taskLog, $handleSignals = true)
    {
        $this->queue = $queue;
        $this->taskLog = $taskLog;
        $this->handleSignals = $handleSignals;
        $this->processId = getmypid();

        $this->logContext = [
            'QueueName' => $this->queue->getName(),
            'PID'       => $this->processId
        ];

        if ($handleSignals) {
            $this->registerSigHandlers();
        }
    }

    /**
     * Start processing tasks from the queue.
     */
    public function processQueue()
    {
        $this->logInfo('Starting worker.', $this->logContext);

        while ($this->isRunning()) {

            if($this->paused) {
                $this->logDebug('Paused... ', array_merge($this->logContext, [
                    'Iteration' => $this->iterations
                ]));
                usleep($this->waitInterval * 1000000);
                continue;
            }

            ++$this->iterations;

            $this->logContext = array_merge($this->logContext, [
                'Iteration' => $this->iterations
            ]);

            try{
                $this->logDebug('Fetching tasks from queue ', $this->logContext);

                $task = $this->queue->dequeue();

                // if no task to process, sleep for the specified time and continue.
                if (!$task) {
                    $this->logDebug('No task to work on. Sleeping for '. $this->waitInterval .' sec', $this->logContext);
                    usleep($this->waitInterval * 1000000);
                    continue;
                }

                if (!$task instanceof TaskInterface) {
                    $this->logDebug('The received queue item ('. $task .') not processable.', $this->logContext);
                    continue;
                }

                $this->processTask($task);

                unset($task);
            } catch (\Exception $e) {
                $this->logError('Fetching data from queue failed with MSG: '. $e->getMessage(), $this->logContext);
                continue;
            }
        }

        $this->logInfo('Worker finished.', $this->logContext);
    }

    /**
     * Process a task.
     *
     * @param TaskInterface $task
     * @return string
     */
    public function processTask(TaskInterface $task)
    {
        $report = Report::createInfo(__('Running task %s', $task->getId()));

        try {
            $this->logDebug('Processing task '. $task->getId(), $this->logContext);

            $rowsTouched = $this->taskLog->setStatus($task->getId(), TaskLogInterface::STATUS_RUNNING, TaskLogInterface::STATUS_DEQUEUED);

            // if the task is being executed by another worker, just return, no report needs to be saved
            if (!$rowsTouched) {
                $this->logDebug('Task '. $task->getId() .' seems to be processed by another worker.', $this->logContext);
                return TaskLogInterface::STATUS_UNKNOWN;
            }

            // execute the task
            $taskReport = $task();

            if (!$taskReport instanceof Report) {
                $this->logWarning('Task '. $task->getId() .' should return a report object.', $this->logContext);
                $taskReport = Report::createInfo(__('Task not returned any report.'));
            }

            $report->add($taskReport);
            unset($taskReport, $rowsTouched);
        } catch (\Exception $e) {
            $this->logError('Executing task '. $task->getId() .' failed with MSG: '. $e->getMessage(), $this->logContext);
            $report = Report::createFailure(__('Executing task %s failed', $task->getId()));
        }

        $status = $report->getType() == Report::TYPE_ERROR || $report->containsError()
            ? TaskLogInterface::STATUS_FAILED
            : TaskLogInterface::STATUS_COMPLETED;

        $this->taskLog->setReport($task->getId(), $report, $status);

        unset($report);

        // delete message from queue
        $this->queue->acknowledge($task);

        return $status;
    }

    /**
     * @inheritdoc
     */
    public function setMaxIterations($maxIterations)
    {
        $this->maxIterations = (int) $maxIterations * $this->queue->getNumberOfTasksToReceive();

        return $this;
    }

    /**
     * @return bool
     */
    private function isRunning()
    {
        if ($this->handleSignals) {
            pcntl_signal_dispatch();
        }

        if ($this->shutdown) {
            return false;
        }

        if ($this->maxIterations > 0) {
            return $this->iterations < $this->maxIterations;
        }

        return true;
    }

    /**
     * Register signal handlers that a worker should respond to.
     *
     * TERM/INT/QUIT: Shutdown after the current job is finished then exit.
     * USR2: Pause worker, no new jobs will be processed but the current one will be finished.
     * CONT: Resume worker.
     */
    private function registerSigHandlers()
    {
        if (!function_exists('pcntl_signal')) {
            $this->logError('Please make sure that "pcntl" is enabled.', $this->logContext);
            throw new \RuntimeException('Please make sure that "pcntl" is enabled.');
        }

        declare(ticks = 1);

        pcntl_signal(SIGTERM, array($this, 'shutdown'));
        pcntl_signal(SIGINT, array($this, 'shutdown'));
        pcntl_signal(SIGQUIT, array($this, 'shutdown'));
        pcntl_signal(SIGUSR2, array($this, 'pauseProcessing'));
        pcntl_signal(SIGCONT, array($this, 'unPauseProcessing'));

        $this->logInfo('Finished setting up signal handlers', $this->logContext);
    }

    public function shutdown()
    {
        $this->logInfo('TERM/INT/QUIT received; shutting down gracefully...', $this->logContext);
        $this->shutdown = true;
    }

    public function pauseProcessing()
    {
        $this->logInfo('USR2 received; pausing task processing...', $this->logContext);
        $this->paused = true;
    }

    public function unPauseProcessing()
    {
        $this->logInfo('CONT received; resuming task processing...', $this->logContext);
        $this->paused = false;
    }
}