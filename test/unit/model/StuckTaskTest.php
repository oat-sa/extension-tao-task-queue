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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoTaskQueue\test\unit\model;

use oat\generis\test\TestCase;
use oat\tao\model\taskQueue\Task\TaskInterface;
use oat\tao\model\taskQueue\TaskLog\Entity\EntityInterface;
use oat\taoTaskQueue\model\StuckTask;

class StuckTaskTest extends TestCase
{
    public function testGetters(): void
    {
        $taskLog = $this->createMock(EntityInterface::class);
        $task = $this->createMock(TaskInterface::class);
        $stuckTask = new StuckTask(
            $taskLog,
            'queue',
            $task,
            'taskId'
        );

        $this->assertSame($task, $stuckTask->getTask());
        $this->assertSame($taskLog, $stuckTask->getTaskLog());
        $this->assertSame('taskId', $stuckTask->getTaskId());
        $this->assertSame('queue', $stuckTask->getQueueName());
    }
}
