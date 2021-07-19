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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */

declare(strict_types=1);

namespace oat\taoTaskQueue\model;

use oat\tao\model\taskQueue\Task\TaskInterface;
use oat\tao\model\taskQueue\TaskLog\Entity\EntityInterface;

class StuckTask
{
    /** @var EntityInterface */
    private $taskLog;

    /** @var string */
    private $queueName;

    /** @var TaskInterface|null */
    private $task;

    /** @var string|null */
    private $taskId;

    public function __construct(
        EntityInterface $taskLog,
        string $queueName,
        TaskInterface $task = null,
        string $taskId = null
    ) {
        $this->taskLog = $taskLog;
        $this->queueName = $queueName;
        $this->task = $task;
        $this->taskId = $taskId;
    }

    public function getTaskLog(): EntityInterface
    {
        return $this->taskLog;
    }

    public function getTask(): ?TaskInterface
    {
        return $this->task;
    }

    public function getTaskId(): ?string
    {
        return $this->taskId;
    }

    public function isOrphan(): bool
    {
        return !$this->task;
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }
}
