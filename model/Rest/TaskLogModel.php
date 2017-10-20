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

namespace oat\taoTaskQueue\model\Rest;

use oat\taoTaskQueue\model\Entity\TaskLogEntity;
use oat\taoTaskQueue\model\Entity\TasksLogsStats;
use oat\taoTaskQueue\model\TaskLogBroker\TaskLogCollection;
use oat\taoTaskQueue\model\TaskLogInterface;

class TaskLogModel
{
    /**
     * @var TaskLogInterface
     */
    private $repository;

    /**
     * @param TaskLogInterface $repository
     */
    public function __construct(TaskLogInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return TaskLogCollection
     */
    public function findAvailableByUser($userId)
    {
        return $this->repository->findAvailableByUser($userId);
    }

    /**
     * @param string $taskLogId
     * @param string $userId
     *
     * @return TaskLogEntity
     * @throws \common_exception_NotFound
     */
    public function getByIdAndUser($taskLogId, $userId)
    {
        return $this->repository->getByIdAndUser($taskLogId, $userId);
    }

    /**
     * @return TasksLogsStats
     */
    public function getStats($userId)
    {
        $collection = $this->repository->findAvailableByUser($userId);

        return new TasksLogsStats(
            $collection->getNumberOfTasksCompleted(),
            $collection->getNumberOfTasksFailed(),
            $collection->getNumberOfTasksInProgress()
        );
    }

    /**
     * @param $taskId
     * @param $userId
     * @return bool
     * @throws \common_exception_NotFound
     * @throws \Exception
     */
    public function archive($taskId, $userId)
    {
        $taskLog = $this->getByIdAndUser($taskId, $userId);

        return $this->repository->archive($taskLog);
    }
}