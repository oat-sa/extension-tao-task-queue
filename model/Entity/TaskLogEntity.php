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

namespace oat\taoTaskQueue\model\Entity;

use common_report_Report as Report;
use DateTime;
use Exception;
use JsonSerializable;
use oat\taoTaskQueue\model\TaskLogBroker\TaskLogBrokerInterface;
use oat\taoTaskQueue\model\ValueObjects\TaskLogCategorizedStatus;

class TaskLogEntity implements JsonSerializable
{
    /** @var string */
    private $id;

    /** @var string */
    private $taskName;

    /** @var array */
    private $parameters;

    /** @var  string */
    private $label;

    /** @var TasksLogsStats */
    private $status;

    /** @var string */
    private $owner;

    /** @var  Report */
    private $report;

    /** @var  DateTime */
    private $createdAt;

    /** @var  DateTime */
    private $updatedAt;

    /**
     * TaskLogEntity constructor.
     *
     * @param string                   $id
     * @param string                   $taskName
     * @param array                    $parameters
     * @param string                   $label
     * @param TaskLogCategorizedStatus $status
     * @param string                   $owner
     * @param Report                   $report
     * @param DateTime        $createdAt
     * @param DateTime        $updatedAt
     */
    public function __construct(
        $id,
        $taskName,
        array $parameters,
        $label,
        TaskLogCategorizedStatus $status,
        $owner,
        DateTime $createdAt,
        DateTime $updatedAt,
        Report $report = null
    ) {
        $this->id = $id;
        $this->taskName = $taskName;
        $this->parameters = $parameters;
        $this->label = $label;
        $this->status = $status;
        $this->owner = $owner;
        $this->report = $report;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }


    /**
     * @param array $row
     * @return TaskLogEntity
     * @throws \common_exception_Error
     * @throws Exception
     */
    public static function createFromArray(array $row)
    {
        return new self(
            $row[TaskLogBrokerInterface::COLUMN_ID],
            $row[TaskLogBrokerInterface::COLUMN_TASK_NAME],
            $row[TaskLogBrokerInterface::COLUMN_PARAMETERS] ? json_decode($row[TaskLogBrokerInterface::COLUMN_PARAMETERS], true) : [],
            $row[TaskLogBrokerInterface::COLUMN_LABEL],
            TaskLogCategorizedStatus::createFromString($row[TaskLogBrokerInterface::COLUMN_STATUS]),
            $row[TaskLogBrokerInterface::COLUMN_OWNER],
            DateTime::createFromFormat('Y-m-d H:i:s', $row[TaskLogBrokerInterface::COLUMN_CREATED_AT]),
            DateTime::createFromFormat('Y-m-d H:i:s', $row[TaskLogBrokerInterface::COLUMN_UPDATED_AT]),
            Report::jsonUnserialize($row[TaskLogBrokerInterface::COLUMN_REPORT])
        );
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTaskName()
    {
        return $this->taskName;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @return Report|null
     */
    public function getReport()
    {
        return $this->report;
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }


    /**
     * @return TaskLogCategorizedStatus
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getFileNameFromReport()
    {
        $filename = '';

        if ($this->getStatus()->isFailed() || is_null($this->getReport())) {
            return $filename;
        }

        /** @var Report  $successReport */
        foreach ($this->getReport()->getSuccesses() as $successReport) {
            if (!is_null($filename = $successReport->getData())) {
                break;
            }
        }

        return $filename;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'taskName' => $this->taskName,
            'taskLabel' => $this->label,
            'status' => (string) $this->status,
            'statusLabel' => $this->status->getLabel(),
            'createdAt' => $this->createdAt->format(DateTime::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTime::ATOM),
            'report' => $this->report
        ];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array_merge($this->jsonSerialize(), [
            'report' => is_null($this->report) ? [] : $this->report->toArray()
        ]);
    }
}