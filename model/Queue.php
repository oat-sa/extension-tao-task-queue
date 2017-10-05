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

use oat\oatbox\service\ConfigurableService;
use oat\taoTaskQueue\model\QueueBroker\QueueBrokerInterface;
use oat\oatbox\log\LoggerAwareTrait;
use oat\taoTaskQueue\model\QueueBroker\SyncQueueBrokerInterface;

/**
 * Queue Service
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class Queue extends ConfigurableService implements QueueInterface
{
    use LoggerAwareTrait;

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
     * @param array $options
     */
    public function __construct(array $options)
    {
        parent::__construct($options);

        if (!$this->hasOption(self::OPTION_QUEUE_NAME) || empty($this->getOption(self::OPTION_QUEUE_NAME))) {
            throw new \InvalidArgumentException("Queue name needs to be set.");
        }

        if (!$this->hasOption(self::OPTION_QUEUE_BROKER) || empty($this->getOption(self::OPTION_QUEUE_BROKER))) {
            throw new \InvalidArgumentException("Queue Broker service needs to be set.");
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
        return $this->getOption(self::OPTION_QUEUE_NAME);
    }

    /**
     * Returns the queue broker service.
     *
     * @return QueueBrokerInterface
     */
    protected function getBroker()
    {
        if (is_null($this->broker)) {
            $this->broker = $this->getServiceManager()->get($this->getOption(self::OPTION_QUEUE_BROKER));
            $this->broker->setQueueName($this->getName());

            if ($this->isSync()) {
                $this->initialize();
            }
        }

        return $this->broker;
    }

    /**
     * @return TaskLogInterface
     */
    protected function getTaskLog()
    {
        if (is_null($this->taskLog)) {
            $this->taskLog = $this->getServiceManager()->get($this->getOption(self::OPTION_TASK_LOG));
        }

        return $this->taskLog;
    }

    /**
     * Run worker on-the-fly for one run.
     */
    protected function runWorker()
    {
        (new Worker($this, $this->getTaskLog(), false))
            ->setMaxIterations(1)
            ->processQueue();
    }

    /**
     * Creates a CallbackTask with any callable and enqueueing it straightaway.
     *
     * @param callable $callable
     * @param array  $parameters
     * @return CallbackTask
     */
    public function createTask(callable $callable, array $parameters = [])
    {
        $id = \common_Utils::getNewUri();
        $owner = \common_session_SessionManager::getSession()->getUser()->getIdentifier();

        $callbackTask = new CallbackTask($id, $owner);
        $callbackTask->setCallable($callable)
            ->setParameter($parameters);

        if ($this->enqueue($callbackTask)) {
            $callbackTask->markAsEnqueued();
        }

        return $callbackTask;
    }

    /**
     * @inheritdoc
     */
    public function enqueue(TaskInterface $task)
    {
        try {
            $isEnqueued = $this->getBroker()->push($task);

            if ($isEnqueued) {
                $this->getTaskLog()
                    ->add($task, TaskLogInterface::STATUS_ENQUEUED);
            }

            // if we need to run the task straightaway
            if ($isEnqueued && $this->isSync()) {
                $this->runWorker();
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