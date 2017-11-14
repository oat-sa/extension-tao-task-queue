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
use oat\taoTaskQueue\model\Entity\TaskLogEntity;
use oat\taoTaskQueue\model\Entity\TasksLogsStats;
use oat\taoTaskQueue\model\Task\TaskInterface;
use oat\taoTaskQueue\model\TaskLog\DataTablePayload;
use oat\taoTaskQueue\model\TaskLog\TaskLogFilter;
use oat\taoTaskQueue\model\TaskLogBroker\TaskLogBrokerInterface;
use oat\taoTaskQueue\model\TaskLogBroker\TaskLogCollection;
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

    const DEFAULT_LIMIT = 20;

    const SUPER_USER = 'SuperUser';

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

    /**
     * @param TaskLogFilter $filter
     * @return TaskLogCollection|TaskLogEntity[]
     */
    public function search(TaskLogFilter $filter);

    /**
     * @param TaskLogFilter $filter
     * @return DataTablePayload
     */
    public function getDataTablePayload(TaskLogFilter $filter);

    /**
     * @param string $userId
     * @param null $limit
     * @param null $offset
     * @return TaskLogCollection|TaskLogEntity[]
     */
    public function findAvailableByUser($userId, $limit = null, $offset = null);

    /**
     * @param string $userId
     * @return TasksLogsStats
     */
    public function getStats($userId);

    /**
     * @param string $taskId
     * @param string $userId
     * @return TaskLogEntity
     *
     * @throws \common_exception_NotFound
     */
    public function getByIdAndUser($taskId, $userId);

    /**
     * @param TaskLogEntity $entity
     * @param bool $forceArchive
     * @return bool
     *
     * @throws \Exception
     */
    public function archive(TaskLogEntity $entity, $forceArchive = false);

    /**
     * Gets the current broker instance.
     *
     * @return TaskLogBrokerInterface
     */
    public function getBroker();

    /**
     * Is the current broker RDS based?
     *
     * @return bool
     */
    public function isRds();
}