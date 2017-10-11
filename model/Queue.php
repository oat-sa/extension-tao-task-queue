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

use oat\taoTaskQueue\model\QueueBroker\QueueBrokerInterface;
use oat\oatbox\log\LoggerAwareTrait;
use oat\taoTaskQueue\model\QueueBroker\SyncQueueBrokerInterface;
use oat\taoTaskQueue\model\Task\TaskInterface;

/**
 * Queue Service
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class Queue implements QueueInterface
{
    use LoggerAwareTrait;

    private $name;

    /**
     * @var QueueBrokerInterface
     */
    private $broker;

    /**
     * @var TaskLogInterface
     */
    private $taskLog;

    /**
     * Queue constructor.
     *
     * @param string $name
     * @param QueueBrokerInterface $broker
     * @param TaskLogInterface     $taskLog
     */
    public function __construct($name, QueueBrokerInterface $broker, TaskLogInterface $taskLog)
    {
        $this->name = $name;
        $this->broker = $broker;
        $this->taskLog = $taskLog;

        $this->getBroker()->setQueueName($this->getName());

        if ($this->isSync()) {
            $this->initialize();
        }
    }

    /**
     * @inheritdoc
     */
    public function initialize()
    {
        $this->getBroker()->createQueue();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the queue broker service.
     *
     * @return QueueBrokerInterface
     */
    protected function getBroker()
    {
        return $this->broker;
    }

    /**
     * @return TaskLogInterface
     */
    protected function getTaskLog()
    {
        return $this->taskLog;
    }

    /**
     * @inheritdoc
     */
    public function enqueue(TaskInterface $task, $label = null)
    {
        try {
            $isEnqueued = $this->getBroker()->push($task);

            if ($isEnqueued) {
                $this->getTaskLog()
                    ->add($task, TaskLogInterface::STATUS_ENQUEUED, $label);
            }

            return $isEnqueued;
        } catch (\Exception $e) {
            $this->logError('Enqueueing '. $task .' failed with MSG: '. $e->getMessage());
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function dequeue()
    {
        if ($task = $this->getBroker()->pop()) {
            $this->getTaskLog()
                ->setStatus($task->getId(), TaskLogInterface::STATUS_DEQUEUED);

            return $task;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function acknowledge(TaskInterface $task)
    {
        $this->getBroker()->delete($task);
    }

    /**
     * Count of messages in the queue.
     *
     * @return int
     */
    public function count()
    {
        return $this->getBroker()->count();
    }

    /**
     * @return bool
     */
    public function isSync()
    {
        return $this->getBroker() instanceof SyncQueueBrokerInterface;
    }

    /**
     * @inheritdoc
     */
    public function getNumberOfTasksToReceive()
    {
        return $this->getBroker()->getNumberOfTasksToReceive();
    }
}