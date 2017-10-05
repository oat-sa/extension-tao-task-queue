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

use Psr\Log\LoggerAwareInterface;

/**
 * Interface QueueInterface
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
interface QueueInterface extends \Countable, LoggerAwareInterface
{
    const SERVICE_ID = 'generis/taskqueue'; //intentionally using the name of the old queue service

    const QUEUE_PREFIX = 'TQ';

    const OPTION_QUEUE_NAME = 'queue_name';
    const OPTION_QUEUE_BROKER = 'queue_broker';
    const OPTION_TASK_LOG = 'task_log';

    /**
     * Initialize queue.
     *
     * @return void
     */
    public function initialize();

    /**
     * Returns queue name.
     *
     * @return string
     */
    public function getName();

    /**
     * Create a task to be managed by the queue from any callable
     *
     * @param callable $callable
     * @param array $parameters
     * @return CallbackTask
     */
    public function createTask(callable $callable, array $parameters = []);

    /**
     * Publish a task to the queue.
     *
     * @param TaskInterface $task
     * @return bool Is the task successfully enqueued?
     */
    public function enqueue(TaskInterface $task);

    /**
     * Receive a task from the queue.
     *
     * @return null|TaskInterface
     */
    public function dequeue();

    /**
     * Acknowledge that the task has been received and consumed.
     *
     * @param TaskInterface $task
     */
    public function acknowledge(TaskInterface $task);

    /**
     * Is the given queue a sync queue?
     *
     * @return bool
     */
    public function isSync();

    /**
     * The amount of tasks that can be received in one pop by this queue.
     *
     * @return int
     */
    public function getNumberOfTasksToReceive();
}