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

use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\QueueInterface;
use oat\tao\model\taskQueue\QueuerInterface;
use oat\tao\model\taskQueue\Task\TaskInterface;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\tao\model\taskQueue\Worker\AbstractWorker;

/**
 * Processes tasks from the queue service running for limited/unlimited time
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
final class LongRunningWorker extends AbstractWorker
{
    const WAIT_INTERVAL = 1; // sec
    const MAX_SLEEPING_TIME_FOR_DEDICATED_QUEUE = 30; //max sleeping time if working on only one queue

    private $maxIterations = 0; //0 means infinite iteration
    private $iterations = 0;
    private $shutdown;
    private $paused;
    private $iterationsWithOutTask = 0;
    private $handleSignals;
    private $sigHandlersRegistered = false;

    public function __construct(QueuerInterface $queuer, TaskLogInterface $taskLog, $handleSignals = true)
    {
        parent::__construct($queuer, $taskLog);
        $this->handleSignals = $handleSignals;
    }

    protected function getLogContext()
    {
        $rs = [
            'PID' => getmypid(),
            'Iteration' => $this->iterations
        ];

        if ($this->queuer instanceof QueueInterface) {
            $rs['QueueName'] = $this->queuer->getName();
        }

        return $rs;
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $this->registerSigHandlers();
        $this->logInfo('Starting LongRunningWorker.', $this->getLogContext());

        while ($this->isRunning()) {
            if ($this->paused) {
                $this->logInfo('Worker paused... ', $this->getLogContext());
                usleep(self::WAIT_INTERVAL * 1000000);
                continue;
            }

            ++$this->iterations;

            try {
                $this->logDebug('Fetching tasks from queue ', $this->getLogContext());

                $task = $this->queuer->dequeue();

                // if no task to process, sleep for the specified time and continue.
                if (!$task) {
                    ++$this->iterationsWithOutTask;
                    $waitInterval = $this->getWaitInterval();
                    $this->logInfo('No tasks found. Sleeping for ' . $waitInterval . ' sec', $this->getLogContext());
                    usleep($waitInterval * 1000000);

                    continue;
                }

                // we have task, so set this back to 0
                $this->iterationsWithOutTask = 0;

                if (!$task instanceof TaskInterface) {
                    $this->logWarning('The received queue item (' . $task . ') not processable.', $this->getLogContext());
                    continue;
                }

                $this->processTask($task);

                unset($task);
            } catch (\Exception $e) {
                $this->logError('Fetching data from queue failed with MSG: ' . $e->getMessage(), $this->getLogContext());
                continue;
            }
        }

        $this->logInfo('LongRunningWorker finished.', $this->getLogContext());
    }

    /**
     * @inheritdoc
     */
    public function setMaxIterations($maxIterations)
    {
        if (!$this->queuer instanceof QueueInterface) {
            throw new \LogicException('Limit can be set only if a dedicated queue is set.');
        }

        $this->maxIterations = $maxIterations * $this->queuer->getNumberOfTasksToReceive();

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
        if ($this->handleSignals && !$this->sigHandlersRegistered) {
            if (!function_exists('pcntl_signal')) {
                $this->logError('Please make sure that "pcntl" is enabled.', $this->getLogContext());
                throw new \RuntimeException('Please make sure that "pcntl" is enabled.');
            }

            declare(ticks=1);

            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
            pcntl_signal(SIGQUIT, [$this, 'shutdown']);
            pcntl_signal(SIGUSR2, [$this, 'pauseProcessing']);
            pcntl_signal(SIGCONT, [$this, 'unPauseProcessing']);

            $this->sigHandlersRegistered = true;

            $this->logInfo('Finished setting up signal handlers', $this->getLogContext());
        }
    }

    public function shutdown()
    {
        $this->logInfo('TERM/INT/QUIT received; shutting down gracefully...', $this->getLogContext());
        $this->shutdown = true;
    }

    public function pauseProcessing()
    {
        $this->logInfo('USR2 received; pausing task processing...', $this->getLogContext());
        $this->paused = true;
    }

    public function unPauseProcessing()
    {
        $this->logInfo('CONT received; resuming task processing...', $this->getLogContext());
        $this->paused = false;
    }

    /**
     * Calculate the sleeping time dynamically in case of no task to work on.
     *
     * @return int (sec)
     */
    private function getWaitInterval()
    {
        if ($this->queuer instanceof QueueInterface) {
            $waitTime = $this->iterationsWithOutTask * self::WAIT_INTERVAL;

            return min($waitTime, self::MAX_SLEEPING_TIME_FOR_DEDICATED_QUEUE);
        } elseif ($this->queuer instanceof QueueDispatcherInterface) {
            return (int) $this->queuer->getWaitTime();
        } else {
            return self::WAIT_INTERVAL;
        }
    }
}
