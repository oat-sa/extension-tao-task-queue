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
use DateTimeInterface;
use Exception;
use JsonSerializable;
use oat\taoTaskQueue\model\ValueObjects\TaskLogCategorizedStatus;

class TaskLogEntity implements JsonSerializable
{
    /** @var string */
    private $id;

    /** @var string */
    private $taskName;

    /** @var  string */
    private $label;

    /** @var TasksLogsStats */
    private $status;

    /** @var string */
    private $owner;

    /** @var  Report */
    private $report;

    /** @var  DateTimeInterface */
    private $createdAt;

    /** @var  DateTimeInterface */
    private $updatedAt;

    /**
     * TaskLogEntity constructor.
     *
     * @param string                   $id
     * @param string                   $taskName
     * @param string                   $label
     * @param TaskLogCategorizedStatus $status
     * @param string                   $owner
     * @param Report                   $report
     * @param DateTimeInterface        $createdAt
     * @param DateTimeInterface        $updatedAt
     */
    public function __construct(
        $id,
        $taskName,
        $label,
        TaskLogCategorizedStatus $status,
        $owner,
        DateTimeInterface $createdAt,
        DateTimeInterface $updatedAt,
        Report $report = null
    ) {
        $this->id = $id;
        $this->taskName = $taskName;
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
            $row['id'],
            $row['task_name'],
            $row['label'],
            TaskLogCategorizedStatus::create($row['status']),
            $row['owner'],
            DateTime::createFromFormat('Y-m-d H:i:s', $row['created_at']),
            DateTime::createFromFormat('Y-m-d H:i:s', $row['updated_at']),
            Report::jsonUnserialize($row['report'])
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
     * @return DateTimeInterface
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return DateTimeInterface
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
            'report' => is_null($this->report) ? [] : $this->report->JsonSerialize()
        ];
    }
}