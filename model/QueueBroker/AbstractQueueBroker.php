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

namespace oat\taoTaskQueue\model\QueueBroker;

use oat\oatbox\service\ConfigurableService;
use oat\oatbox\action\ActionService;
use oat\oatbox\action\ResolutionException;
use oat\oatbox\log\LoggerAwareTrait;
use oat\taoTaskQueue\model\QueueDispatcher;
use oat\taoTaskQueue\model\Task\CallbackTaskInterface;
use oat\taoTaskQueue\model\Task\TaskInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;

/**
 * Class AbstractQueueBroker
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
abstract class AbstractQueueBroker extends ConfigurableService implements QueueBrokerInterface
{
    use LoggerAwareTrait;

    private $queueName;
    private $preFetchedQueue;

    /**
     * AbstractMessageBroker constructor.
     *
     * @param array  $options
     */
    public function __construct($options = []) {
        parent::__construct($options);

        $this->preFetchedQueue = new \SplQueue();
    }

    /**
     * Do the specific pop mechanism related to the given broker.
     * Tasks need to be added to the internal pre-fetched queue.
     *
     * @return void
     */
    abstract protected function doPop();

    /**
     * Internal mechanism of deleting a message, specific for the given broker
     *
     * @param string $id
     * @param array $logContext
     * @return void
     */
    abstract protected function doDelete($id, array $logContext = []);

    /**
     * @return null|TaskInterface
     */
    public function pop()
    {
        // if there is item in the pre-fetched queue, let's return that
        if ($message = $this->popPreFetchedMessage()) {
            return $message;
        }

        $this->doPop();

        return $this->popPreFetchedMessage();
    }

    /**
     * Pop a task from the internal queue.
     *
     * @return TaskInterface|null
     */
    private function popPreFetchedMessage()
    {
        if ($this->preFetchedQueue->count()) {
            return $this->preFetchedQueue->dequeue();
        }

        return null;
    }

    /**
     * Add a task to the internal queue.
     *
     * @param TaskInterface $task
     */
    protected function pushPreFetchedMessage(TaskInterface $task)
    {
        $this->preFetchedQueue->enqueue($task);
    }

    /**
     * Unserialize the given task JSON.
     *
     * If the json is not valid, it deletes the task straight away without processing it.
     *
     * @param string $taskJSON
     * @param string $idForDeletion An identification of the given task
     * @param array  $logContext
     * @return null|TaskInterface
     */
    protected function unserializeTask($taskJSON, $idForDeletion, array $logContext = [])
    {
        // if it's a valid task JSON, let's work with it
        if (($basicData = json_decode($taskJSON, true)) !== null
            && json_last_error() === JSON_ERROR_NONE
            && isset($basicData[TaskInterface::JSON_TASK_CLASS_NAME_KEY])
        ) {
            $className = $basicData[TaskInterface::JSON_TASK_CLASS_NAME_KEY];

            // if the body contains a valid class name, let's instantiate it
            if (class_exists($className) && is_subclass_of($className, TaskInterface::class)) {
                $metaData = $basicData[TaskInterface::JSON_METADATA_KEY];

                /** @var TaskInterface $task */
                $task = new $className($metaData[TaskInterface::JSON_METADATA_ID_KEY], $metaData[TaskInterface::JSON_METADATA_OWNER_KEY]);
                $task->setMetadata($metaData);
                $task->setParameter($basicData[TaskInterface::JSON_PARAMETERS_KEY]);

                // unserialize created_at
                if (isset($metaData[TaskInterface::JSON_METADATA_CREATED_AT_KEY])) {
                    $task->setCreatedAt(new \DateTime($metaData[TaskInterface::JSON_METADATA_CREATED_AT_KEY]));
                }

                // if it's a CallbackTask and the callable it's a string (meaning it's an Action class name) than we need to restore that object as well.
                if ($task instanceof CallbackTaskInterface && is_string($task->getCallable())) {
                    try {
                        $callable = $this->getActionResolver()->resolve($task->getCallable());

                        if ($callable instanceof ServiceLocatorAwareInterface) {
                            $callable->setServiceLocator($this->getServiceLocator());
                        }

                        $task->setCallable($callable);
                    } catch (ResolutionException $e) {
                        $this->logError('Callable/Action class ' . $task->getCallable() . ' does not exist', $logContext);

                        return null;
                    }
                }

                return $task;
            }
        }

        // if we have an invalid task message:
        // - the given string is not json-decode-able, it's just an arbitrary string
        // - it's a valid json but not containing the 'body' key
        $this->doDelete($idForDeletion, $logContext);

        return null;
    }

    /**
     * @return ActionService|ConfigurableService
     */
    protected function getActionResolver()
    {
        return $this->getServiceManager()->get(ActionService::SERVICE_ID);
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setQueueName($name)
    {
        $this->queueName = $name;

        return $this;
    }

    /**
     * @return string
     */
    protected function getQueueName()
    {
        return $this->queueName;
    }

    /**
     * @return string
     */
    protected function getQueueNameWithPrefix()
    {
        return sprintf("%s_%s", QueueDispatcher::QUEUE_PREFIX, $this->getQueueName());
    }

    /**
     * @inheritdoc
     */
    public function getNumberOfTasksToReceive()
    {
        if($this->hasOption(self::OPTION_NUMBER_OF_TASKS_TO_RECEIVE)) {
            return abs((int) $this->getOption(self::OPTION_NUMBER_OF_TASKS_TO_RECEIVE));
        }

        return 1;
    }
}