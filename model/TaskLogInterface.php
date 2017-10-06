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

use common_report_Report as Report;
use Psr\Log\LoggerAwareInterface;

/**
 * Interface TaskLogInterface
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
interface TaskLogInterface extends LoggerAwareInterface
{
    const SERVICE_ID = 'taoTaskQueue/taskLog';

    const OPTION_TASK_LOG_BROKER = 'task_log_broker';

    const STATUS_ENQUEUED = 'enqueued';
    const STATUS_DEQUEUED = 'dequeued';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_UNKNOWN = 'unknown';

    /**
     * @return void
     */
    public function createContainer();

    /**
     * Insert a new task with status into the container.
     *
     * @param TaskInterface $task
     * @param string        $status
     * @param null|string   $label
     * @return TaskLogInterface
     */
    public function add(TaskInterface $task, $status, $label = null);

    /**
     * Set a status for a task.
     *
     * @param string $taskId
     * @param string $newStatus
     * @param string|null $prevStatus
     * @return int
     */
    public function setStatus($taskId, $newStatus, $prevStatus = null);

    /**
     * Gets the status of a task.
     *
     * @param string $taskId
     * @return string
     */
    public function getStatus($taskId);

    /**
     * Saves the report and a status for a task.
     *
     * @param string $taskId
     * @param Report $report
     * @param string|null $newStatus
     * @return TaskLogInterface
     */
    public function setReport($taskId, Report $report, $newStatus = null);

    /**
     * Gets the report for a task if that exists.
     *
     * @param string $taskId
     * @return Report|null
     */
    public function getReport($taskId);
}