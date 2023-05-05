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

namespace oat\taoTaskQueue\model\ValueObjects;

use Exception;
use oat\taoTaskQueue\model\TaskLogInterface;

/**
 * @deprecated Use \oat\tao\model\taskQueue\TaskLog\CategorizedStatus
 */
class TaskLogCategorizedStatus
{
    public const STATUS_CREATED = 'created';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ARCHIVED = 'archived';

    /** @var  string */
    private $status;

    public static $categorizeMapping = [
        self::STATUS_CREATED => [
            TaskLogInterface::STATUS_ENQUEUED
        ],
        self::STATUS_IN_PROGRESS => [
            TaskLogInterface::STATUS_DEQUEUED,
            TaskLogInterface::STATUS_RUNNING,
            TaskLogInterface::STATUS_CHILD_RUNNING
        ],
        self::STATUS_COMPLETED   => [
            TaskLogInterface::STATUS_COMPLETED,
            TaskLogInterface::STATUS_ARCHIVED
        ],
        self::STATUS_FAILED      => [
            TaskLogInterface::STATUS_FAILED,
            TaskLogInterface::STATUS_UNKNOWN
        ],
        self::STATUS_ARCHIVED    => [
            TaskLogInterface::STATUS_ARCHIVED,
        ]
    ];

    /**
     * @param $status
     */
    protected function __construct($status)
    {
        $this->status = $status;
    }

    /**
     * @param string $status
     * @return TaskLogCategorizedStatus
     *
     * @throws Exception
     */
    public static function createFromString($status)
    {
        switch ($status) {
            case TaskLogInterface::STATUS_ENQUEUED:
                return TaskLogCategorizedStatus::created();
                break;

            case TaskLogInterface::STATUS_DEQUEUED:
            case TaskLogInterface::STATUS_RUNNING:
            case TaskLogInterface::STATUS_CHILD_RUNNING:
                return TaskLogCategorizedStatus::inProgress();
                break;

            case TaskLogInterface::STATUS_COMPLETED:
                return TaskLogCategorizedStatus::completed();
                break;

            case TaskLogInterface::STATUS_ARCHIVED:
                return TaskLogCategorizedStatus::archived();
                break;

            case TaskLogInterface::STATUS_FAILED:
            case TaskLogInterface::STATUS_UNKNOWN:
                return TaskLogCategorizedStatus::failed();
                break;

            default:
                throw new \Exception('Invalid status provided');
        }
    }

    /**
     * @return TaskLogCategorizedStatus
     */
    public static function completed()
    {
        return new self(self::STATUS_COMPLETED);
    }

    /**
     * @return TaskLogCategorizedStatus
     */
    public static function archived()
    {
        return new self(self::STATUS_ARCHIVED);
    }

    /**
     * @return TaskLogCategorizedStatus
     */
    public static function failed()
    {
        return new self(self::STATUS_FAILED);
    }

    /**
     * @return TaskLogCategorizedStatus
     */
    public static function created()
    {
        return new self(self::STATUS_CREATED);
    }

    /**
     * @return TaskLogCategorizedStatus
     */
    public static function inProgress()
    {
        return new self(self::STATUS_IN_PROGRESS);
    }

    /**
     * @return bool
     */
    public function isCreated()
    {
        return $this->equals(TaskLogCategorizedStatus::created());
    }

    /**
     * @return bool
     */
    public function isInProgress()
    {
        return $this->equals(TaskLogCategorizedStatus::inProgress());
    }

    /**
     * @return bool
     */
    public function isCompleted()
    {
        return $this->equals(TaskLogCategorizedStatus::completed());
    }

    /**
     * @return bool
     */
    public function isFailed()
    {
        return $this->equals(TaskLogCategorizedStatus::failed());
    }

    /**
     * @return bool
     */
    public function isArchived()
    {
        return $this->equals(TaskLogCategorizedStatus::archived());
    }

    /**
     * @param TaskLogCategorizedStatus $logStatus
     *
     * @return bool
     */
    public function equals(TaskLogCategorizedStatus $logStatus)
    {
        return $this->status === $logStatus->status;
    }

    /**
     * @param string $status
     * @return array
     */
    public static function getMappedStatuses($status)
    {
        return self::$categorizeMapping[$status];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->status;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        switch ($this->status) {
            case self::STATUS_CREATED:
                return __('Queued');
                break;

            case self::STATUS_IN_PROGRESS:
                return __('In Progress');
                break;

            case self::STATUS_COMPLETED:
                return __('Completed');
                break;

            case self::STATUS_FAILED:
                return __('Failed');
                break;
        }
    }
}
