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
use oat\taoTaskQueue\model\TaskLogInterface;
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
     * @var null
     */
    private $category;

    /**
     * TaskLogEntity constructor.
     *
     * @param string                   $id
     * @param string                   $taskName
     * @param TaskLogCategorizedStatus $status
     * @param array                    $parameters
     * @param string                   $label
     * @param string                   $owner
     * @param DateTime|null            $createdAt
     * @param DateTime|null            $updatedAt
     * @param Report|null              $report
     * @param string|null              $category
     */
    public function __construct(
        $id,
        $taskName,
        TaskLogCategorizedStatus $status,
        array $parameters,
        $label,
        $owner,
        DateTime $createdAt = null,
        DateTime $updatedAt = null,
        Report $report = null,
        $category = null
    ) {
        $this->id = $id;
        $this->taskName = $taskName;
        $this->status = $status;
        $this->parameters = $parameters;
        $this->label = $label;
        $this->owner = $owner;
        $this->report = $report;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->category = $category;
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
            TaskLogCategorizedStatus::createFromString($row[TaskLogBrokerInterface::COLUMN_STATUS]),
            isset($row[TaskLogBrokerInterface::COLUMN_PARAMETERS]) ? json_decode($row[TaskLogBrokerInterface::COLUMN_PARAMETERS], true) : [],
            isset($row[TaskLogBrokerInterface::COLUMN_LABEL]) ? $row[TaskLogBrokerInterface::COLUMN_LABEL] : '',
            isset($row[TaskLogBrokerInterface::COLUMN_OWNER]) ? $row[TaskLogBrokerInterface::COLUMN_OWNER] : '',
            isset($row[TaskLogBrokerInterface::COLUMN_CREATED_AT]) ? DateTime::createFromFormat('Y-m-d H:i:s', $row[TaskLogBrokerInterface::COLUMN_CREATED_AT], new \DateTimeZone(TIME_ZONE)) : null,
            isset($row[TaskLogBrokerInterface::COLUMN_UPDATED_AT]) ? DateTime::createFromFormat('Y-m-d H:i:s', $row[TaskLogBrokerInterface::COLUMN_UPDATED_AT], new \DateTimeZone(TIME_ZONE)) : null,
            isset($row[TaskLogBrokerInterface::COLUMN_REPORT]) ? Report::jsonUnserialize($row[TaskLogBrokerInterface::COLUMN_REPORT]) : null,
            isset($row[TaskLogBrokerInterface::COLUMN_CATEGORY]) ? $row[TaskLogBrokerInterface::COLUMN_CATEGORY] : null
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
     * @return DateTime|null
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return DateTime|null
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
     * @return string
     */
    public function getCategory()
    {
        return $this->category ?: TaskLogInterface::CATEGORY_UNKNOWN;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        // add basic fields which always have values
        $rs = [
            'id' => $this->id,
            'taskName' => $this->taskName,
            'status' => (string) $this->status,
            'statusLabel' => $this->status->getLabel(),
            'file' => (bool) $this->getFileNameFromReport(),
            'category' => $this->getCategory()
        ];

        // add other fields only if they have values
        if ($this->label) {
            $rs['taskLabel'] = $this->label;
        }

        if ($this->createdAt instanceof \DateTime) {
            $rs['createdAt'] = $this->createdAt->getTimestamp();
            $rs['createdAtElapsed'] = (new \DateTime('now', new \DateTimeZone(TIME_ZONE)))->getTimestamp() - $this->createdAt->getTimestamp();
        }

        if ($this->updatedAt instanceof \DateTime) {
            $rs['updatedAt'] = $this->updatedAt->getTimestamp();
            $rs['updatedAtElapsed'] = (new \DateTime('now', new \DateTimeZone(TIME_ZONE)))->getTimestamp() - $this->updatedAt->getTimestamp();
        }

        if ($this->report instanceof Report) {
            $rs['report'] = $this->report->JsonSerialize();
        }

        return $rs;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->jsonSerialize();
    }
}