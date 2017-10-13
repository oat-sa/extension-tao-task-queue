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

use oat\taoTaskQueue\model\Task\CallbackTaskInterface;
use oat\taoTaskQueue\model\Task\TaskInterface;
use Psr\Log\LoggerAwareInterface;

interface QueueDispatcherInterface extends \Countable, LoggerAwareInterface
{
    const SERVICE_ID = 'taoTaskQueue/taskQueue';

    /**
     * An array of queues like ['queueA'=>5, 'queueB'=>45, 'queueC'=>50]
     */
    const OPTION_QUEUES = 'queues';

    /**
     * An array of tasks names with the specified queue where the tasks needs to be published to.
     */
    const OPTION_TASKS = 'tasks_by_queues';
    const OPTION_TASK_LOG = 'task_log';

    const QUEUE_PREFIX = 'TQ';

    /**
     * Add new Queue.
     *
     * @param QueueInterface $queue
     * @return QueueDispatcherInterface
     */
    public function addQueue(QueueInterface $queue);

    /**
     * @param string $queueName
     * @return QueueInterface
     */
    public function getQueue($queueName);

    /**
     * @return QueueInterface[]
     */
    public function getQueues();

    /**
     * Get the names of the registered queues.
     *
     * @return array
     */
    public function getQueueNames();

    /**
     * Has the given queue/queue name already been set?
     *
     * @param string $queueName
     * @return bool
     */
    public function hasQueue($queueName);

    /**
     * @return QueueInterface
     */
    public function getDefaultQueue();

    /**
     * Initialize queues.
     *
     * @return void
     */
    public function initialize();

    /**
     * Create a task to be managed by the queue from any callable
     *
     * @param callable    $callable
     * @param array       $parameters
     * @param null|string $label Label for the task
     * @return CallbackTaskInterface
     */
    public function createTask(callable $callable, array $parameters = [], $label = null);

    /**
     * Publish a task to a queue.
     *
     * @param TaskInterface $task
     * @param null|string $label Label for the task
     * @return bool Is the task successfully enqueued?
     */
    public function enqueue(TaskInterface $task, $label = null);

    /**
     * Receive a task from a specified queue or from a queue selected by a predefined strategy
     *
     * @param null|string $queueName
     * @return null|TaskInterface
     */
    public function dequeue($queueName = null);

    /**
     * Acknowledge that the task has been received and consumed.
     *
     * @param TaskInterface $task
     */
    public function acknowledge(TaskInterface $task);

    /**
     * Are all queues a sync one?
     *
     * @return bool
     */
    public function isSync();
}