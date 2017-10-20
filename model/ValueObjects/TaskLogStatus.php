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

class TaskLogStatus
{
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /** @var  string */
    private $status;

    /**
     * @param $status
     */
    protected function __construct($status)
    {
        $this->status = $status;
    }

    /**
     * @param string $status
     * @return TaskLogStatus
     *
     * @throws Exception
     */
    public static function create($status)
    {
        switch ($status) {
            case TaskLogInterface::STATUS_ENQUEUED:
            case TaskLogInterface::STATUS_DEQUEUED:
            case TaskLogInterface::STATUS_RUNNING:
               return TaskLogStatus::running();
            break;
            case TaskLogInterface::STATUS_COMPLETED:
                return TaskLogStatus::completed();
                break;
            case TaskLogInterface::STATUS_FAILED:
            case TaskLogInterface::STATUS_UNKNOWN:
                return TaskLogStatus::failed();
                break;
            default:
                throw new \Exception('Invalid Status provided');
                break;
        }
    }

    /**
     * @return TaskLogStatus
     */
    public static function completed()
    {
        return new self(self::STATUS_COMPLETED);
    }

    /**
     * @return TaskLogStatus
     */
    public static function failed()
    {
        return new self(self::STATUS_FAILED);
    }

    /**
     * @return TaskLogStatus
     */
    public static function running()
    {
        return new self(self::STATUS_RUNNING);
    }

    /**
     * @param TaskLogStatus $logStatus
     *
     * @return bool
     */
    public function equals(TaskLogStatus $logStatus)
    {
       return $this->status === $logStatus->status;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->status;
    }
}