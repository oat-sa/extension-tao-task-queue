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
use oat\taoTaskQueue\model\Task\CallbackTask;
use oat\taoTaskQueue\model\Task\CallbackTaskInterface;
use oat\taoTaskQueue\model\Task\TaskInterface;

class QueueDispatcher extends ConfigurableService implements QueueDispatcherInterface
{
    use LoggerAwareTrait;

    /**
     * @var QueueInterface[]
     */
    private $queues = [];

    /**
     * @var QueueBrokerInterface
     */
    private $broker;

    /**
     * @var TaskLogInterface
     */
    private $taskLog;

    public function __construct(array $options)
    {
        parent::__construct($options);

        if (!$this->hasOption(self::OPTION_QUEUES) || empty($this->getOption(self::OPTION_QUEUES))) {
            throw new \InvalidArgumentException("Queues needs to be set.");
        }

        $this->normalizeQueuesOption();

        if (!$this->hasOption(self::OPTION_QUEUE_BROKER) || empty($this->getOption(self::OPTION_QUEUE_BROKER))) {
            throw new \InvalidArgumentException("Queue Broker service needs to be set.");
        }

        if (!$this->hasOption(self::OPTION_TASK_LOG) || empty($this->getOption(self::OPTION_TASK_LOG))) {
            throw new \InvalidArgumentException("Task Log service needs to be set.");
        }
    }

    /**
     * @param $queueName
     * @return QueueInterface
     */
    public function getQueue($queueName)
    {
        $this->assertValidQueueName($queueName);

        if (!array_key_exists($queueName, $this->queues)) {
            $this->queues[$queueName] = new Queue($queueName, $this->getBroker(), $this->getTaskLog());
        }

        return $this->queues[$queueName];
    }

    /**
     * Return the first queue as a default one.
     *
     * @return QueueInterface
     */
    public function getDefaultQueue()
    {
        $queues = $this->getOption(self::OPTION_QUEUES);
        reset($queues);

        return $this->getQueue(key($queues));
    }

    /**
     * Gets random ques based on weighting.
     *
     * Example array, such as array('A'=>5, 'B'=>45, 'C'=>50) means that "A" has a 5% chance of being selected, "B" 45%, and "C" 50%.
     * The return value is the array key, A, B, or C in this case.
     * The values are simply relative to each other. If one value weight was 2, and the other weight of 1,
     * the value with the weight of 2 has about a 66% chance of being selected.
     * Also note that weights should be integers.
     *
     * @return QueueInterface
     */
    public function getQueueByWeight()
    {
        $finalName = '';

        $rand = mt_rand(1, (int) array_sum($this->getOption(self::OPTION_QUEUES)));

        foreach ($this->getOption(self::OPTION_QUEUES) as $queueName => $weight) {
            $rand -= $weight;
            if ($rand <= 0) {
                $finalName = $queueName;
                break;
            }
        }

        return $this->getQueue($finalName);
    }

    /**
     * Initialize queue.
     *
     * @return void
     */
    public function initialize()
    {
        // TODO
    }

    /**
     * @inheritdoc
     */
    public function createTask(callable $callable, array $parameters = [], $label = null)
    {
        $id = \common_Utils::getNewUri();
        $owner = \common_session_SessionManager::getSession()->getUser()->getIdentifier();

        $callbackTask = new CallbackTask($id, $owner);
        $callbackTask->setCallable($callable)
            ->setParameter($parameters);

        if ($this->enqueue($callbackTask, $label)) {
            $callbackTask->markAsEnqueued();
        }

        return $callbackTask;
    }

    /**
     * @param TaskInterface $task
     * @param null|string   $label
     * @return bool
     */
    public function enqueue(TaskInterface $task, $label = null)
    {
        $queue = $this->getQueueForTask($task);
        $isEnqueued = $queue->enqueue($task, $label);

        // if we need to run the task straightaway
        if ($isEnqueued && $queue->isSync()) {
            $this->runWorker($queue);
        }

        return $isEnqueued;
    }

    /**
     * @inheritdoc
     */
    public function dequeue($queueName = null)
    {
        if (!is_null($queueName)) {
            return $this->getQueue($queueName)->dequeue();
        }

        // if there is only one queue defined, let's use that
        if(count($this->getOption(self::OPTION_QUEUES)) === 1) {
            return $this->getQueue(key($this->getOption(self::OPTION_QUEUES)))->dequeue();
        }

        // default option getting a queue by weights
        return $this->getQueueByWeight()->dequeue();
    }

    public function acknowledge(TaskInterface $task)
    {
        $this->getQueueForTask($task)->acknowledge($task);
    }

    /**
     * Count of messages in all queues.
     *
     * @return int
     */
    public function count()
    {
        // TODO
    }

    /**
     * @return bool
     */
    public function isSync()
    {
        return $this->getDefaultQueue()->isSync();
    }

    /**
     * @inheritdoc
     */
    public function getNumberOfTasksToReceive()
    {
        return $this->getDefaultQueue()->getNumberOfTasksToReceive();
    }

    /**
     * @param TaskInterface $task
     * @return QueueInterface
     */
    private function getQueueForTask(TaskInterface $task)
    {
        $className = $task instanceof CallbackTaskInterface && is_object($task->getCallable()) ? get_class($task->getCallable()) : get_class($task);

        if (array_key_exists($className, (array) $this->getOptions(self::OPTION_TASKS))) {
            $queueName = $this->getOptions(self::OPTION_TASKS)[$className];

            return $this->getQueue($queueName);
        }

        return $this->getDefaultQueue();
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
     * Run worker on-the-fly for one round.
     *
     * @param QueueInterface $queue
     */
    protected function runWorker(QueueInterface $queue)
    {
        (new Worker($this, $this->getTaskLog(), false))
            ->setMaxIterations(1)
            ->setDedicatedQueue($queue->getName())
            ->processQueue();
    }

    /**
     * @param $queueName
     * @throws \InvalidArgumentException
     */
    private function assertValidQueueName($queueName)
    {
        if (array_key_exists($queueName, $this->getOption(self::OPTION_QUEUES))) {
            return;
        }

        throw new \InvalidArgumentException('Queue "'. $queueName .'" does not exist.');
    }

    /**
     * If we have queues defined like "only_one_queue" or ['queue_alone'] or ['queueA', 'queueB', 'queueC'] or ['queueA' => 4, 'queueB']
     * than it tries to normalize it.
     */
    private function normalizeQueuesOption()
    {
        $queues = [];

        foreach ((array) $this->getOption(self::OPTION_QUEUES) as $queueName => $weight) {
            if (!is_string($queueName)) {
                $queueName = $weight;
                $weight = 1;
            }

            $queues[$queueName] = abs($weight);
        }

        $this->setOption(self::OPTION_QUEUES, $queues);
    }

}