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

class TaskLogCategorizedStatus
{
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /** @var  string */
    private $status;

    public static $categorizeMapping = array(
        self::STATUS_IN_PROGRESS => [
            TaskLogInterface::STATUS_ENQUEUED,
            TaskLogInterface::STATUS_DEQUEUED,
            TaskLogInterface::STATUS_RUNNING,
        ],
        self::STATUS_COMPLETED   => [
            TaskLogInterface::STATUS_COMPLETED,
        ],
        self::STATUS_FAILED      => [
            TaskLogInterface::STATUS_FAILED,
            TaskLogInterface::STATUS_UNKNOWN,
        ]
    );

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
    public static function create($status)
    {
        switch ($status) {
            case TaskLogInterface::STATUS_ENQUEUED:
            case TaskLogInterface::STATUS_DEQUEUED:
            case TaskLogInterface::STATUS_RUNNING:
               return TaskLogCategorizedStatus::inProgress();
            break;
            case TaskLogInterface::STATUS_COMPLETED:
                return TaskLogCategorizedStatus::completed();
                break;
            case TaskLogInterface::STATUS_FAILED:
            case TaskLogInterface::STATUS_UNKNOWN:
                return TaskLogCategorizedStatus::failed();
                break;
            default:
                throw new \Exception('Invalid status provided');
                break;
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
    public static function failed()
    {
        return new self(self::STATUS_FAILED);
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