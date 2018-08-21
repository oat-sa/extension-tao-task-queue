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
 * Copyright (c) 2018 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */

namespace oat\taoTaskQueue\scripts\tools;

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\AbstractAction;
use oat\oatbox\log\LoggerAwareTrait;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\Task\TaskInterface;
use oat\tao\model\taskQueue\QueueInterface;
use oat\tao\model\taskQueue\Worker\OneTimeWorker;
use oat\tao\model\taskQueue\Task\CallbackTask;
use oat\tao\model\taskQueue\TaskLogInterface;
use common_report_Report as Report;

/**
 * Class QueueHeartbeat
 *
 *
 * @package oat\taoTaskQueue
 * @author Aleh Hutnikau, <hutnikau@1pt.com>
 */
class QueueHeartbeat extends AbstractAction
{
    use LoggerAwareTrait;
    use OntologyAwareTrait;

    private $owner;

    /**
     * @inheritdoc
     */
    public function __invoke($params = [])
    {
        if (isset($params[0])) {
            $this->owner = $params[0];
        }
        $queueService = $this->getQueueDispatcher();
        $queues = $queueService->getQueues();
        $report = Report::createInfo(__('Add heartbeat tasks to all the registered queues:'));

        foreach ($queues as $queue) {
            try {
                $this->createTask($queue);
                $report->add(Report::createSuccess(__('Heartbeat task added to `%s` queue', $queue->getName())));
            } catch (\Exception $e) {
                $report->add(Report::createFailure(
                    __('Can\'t add heartbeat task to `%s` queue. Error message: %s', $queue->getName(), $e->getMessage())
                ));
            }
        }
        return $report;
    }

    /**
     * @param QueueInterface $queue
     * @return CallbackTask
     * @throws \common_exception_Error
     * @throws \oat\oatbox\service\exception\InvalidServiceManagerException
     */
    private function createTask(QueueInterface $queue)
    {
        $id = \common_Utils::getNewUri();
        $callbackTask = new CallbackTask($id, $this->getOwner());
        $callbackTask->setCallable($this->getCallable());

        if ($this->enqueue($queue, $callbackTask)) {
            $callbackTask->markAsEnqueued();
        }

        return $callbackTask;
    }

    /**
     * @param QueueInterface $queue
     * @param TaskInterface $task
     * @return bool
     * @throws \oat\oatbox\service\exception\InvalidServiceManagerException
     */
    private function enqueue(QueueInterface $queue, TaskInterface $task)
    {
        $label = $queue->getName() . '_heartbeat';
        $isEnqueued = $queue->enqueue($task, $label);

        // if we need to run the task straightaway, then run a worker on-the-fly for one round.
        if ($isEnqueued && $queue->isSync()) {
            (new OneTimeWorker($queue, $this->getTaskLog()))->run();
        }

        return $isEnqueued;
    }

    /**
     * @return string
     * @throws \common_exception_Error
     */
    private function getOwner()
    {
        if ($this->owner === null) {
            $this->owner = \common_session_SessionManager::getSession()->getUser()->getIdentifier();
        }
        return $this->owner;
    }

    /**
     * @return callable
     */
    private function getCallable()
    {
        return $this->propagate(new HeartbeatTask);
    }

    /**
     * @return TaskLogInterface
     * @throws \oat\oatbox\service\exception\InvalidServiceManagerException
     */
    private function getTaskLog()
    {
        return $this->getServiceManager()->get($this->getQueueDispatcher()->getOption(QueueDispatcher::OPTION_TASK_LOG));
    }

    /**
     * @return QueueDispatcherInterface
     */
    private function getQueueDispatcher()
    {
        return $this->getServiceLocator()->get(QueueDispatcherInterface::class);
    }
}
