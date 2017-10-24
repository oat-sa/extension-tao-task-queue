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

namespace oat\taoTaskQueue\model\TaskLogBroker;

use common_report_Report as Report;
use oat\taoTaskQueue\model\Entity\TaskLogEntity;
use oat\taoTaskQueue\model\Entity\TasksLogsStats;
use oat\taoTaskQueue\model\Task\TaskInterface;

/**
 * Interface TaskLogBrokerInterface
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
interface TaskLogBrokerInterface
{
    const DEFAULT_CONTAINER_NAME = 'task_log';

    /**
     * Creates the container where the task logs will be stored.
     * @return void
     */
    public function createContainer();

    /**
     * Inserts a new task log with status for a task.
     *
     * @param TaskInterface $task
     * @param string        $status
     * @param null|string   $label
     * @return void
     */
    public function add(TaskInterface $task, $status, $label = null);

    /**
     * Update the status of a task.
     *
     * The previous status can be used for querying the record.
     *
     * @param string $taskId
     * @param string $newStatus
     * @param string|null $prevStatus
     * @return int count of touched records
     */
    public function updateStatus($taskId, $newStatus, $prevStatus = null);

    /**
     * Gets the status of a task.
     *
     * @param string $taskId
     * @return string
     */
    public function getStatus($taskId);

    /**
     * Add a report for a task. New status can be supplied as well.
     *
     * @param string $taskId
     * @param Report $report
     * @param null|string $newStatus
     * @return int
     */
    public function addReport($taskId, Report $report, $newStatus = null);

    /**
     * Gets a report for a task.
     *
     * @param string $taskId
     * @return Report|null
     */
    public function getReport($taskId);

    /**
     * @param string $userId
     * @param int $limit
     * @param int $offset
     * @return TaskLogCollection
     */
    public function findAvailableByUser($userId, $limit, $offset);

    /**
     * @param string $userId
     * @return TasksLogsStats
     */
    public function getStats($userId);

    /**
     * @param string $taskId
     * @param $userId
     *
     * @return TaskLogEntity
     *
     * @throws \common_exception_NotFound
     */
    public function getByIdAndUser($taskId, $userId);

    /**
     * @param TaskLogEntity $entity
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function archive(TaskLogEntity $entity);
}