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

use JsonSerializable;
use oat\taoTaskQueue\model\Entity\TaskLogEntity;
use oat\taoTaskQueue\model\ValueObjects\TaskLogStatus;

class TaskLogCollection implements JsonSerializable
{
    /** @var TaskLogEntity[]  */
    private $taskLogs = [];

    /**
     * @param TaskLogEntity[] $taskLogs
     */
    public function __construct(array $taskLogs)
    {
        $this->taskLogs = $taskLogs;
    }

    /**
     * @param array $rows
     * @return TaskLogCollection
     *
     * @throws \Exception
     * @throws \common_exception_Error
     */
    public static function createFromArray(array $rows)
    {
        $logs = [];

        foreach ($rows as $row) {
            $logs[] = TaskLogEntity::createFromArray($row);
        }

        return new static($logs);
    }

    /**
     * @return TaskLogCollection
     */
    public static function createEmptyCollection()
    {
        return new static([]);
    }

    /**
     * @return int
     */
    public function getNumberOfTasksCompleted()
    {
        $count = 0;
        $completedStatus = TaskLogStatus::completed();

        foreach ($this->taskLogs as $log) {
            if ($log->getStatus()->equals($completedStatus)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return int
     */
    public function getNumberOfTasksFailed()
    {
        $count = 0;
        $failedStatus = TaskLogStatus::failed();

        foreach ($this->taskLogs as $log) {
            if ($log->getStatus()->equals($failedStatus)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return int
     */
    public function getNumberOfTasksInProgress()
    {
        $count = 0;
        $runningStatus = TaskLogStatus::running();

        foreach ($this->taskLogs as $log) {
            if ($log->getStatus()->equals($runningStatus)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        $data = [];

        foreach ($this->taskLogs as $taskLog) {
            $data[] = $taskLog->jsonSerialize();
        }

        return $data;
    }
}