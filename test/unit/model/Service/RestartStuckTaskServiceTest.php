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

namespace oat\taoTaskQueue\test\unit\model\Service;

use oat\generis\test\TestCase;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\QueueInterface;
use oat\tao\model\taskQueue\Task\TaskInterface;
use oat\tao\model\taskQueue\TaskLog\Entity\EntityInterface;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;
use oat\taoTaskQueue\model\Service\RestartStuckTaskService;
use oat\taoTaskQueue\model\StuckTask;
use PHPUnit\Framework\MockObject\MockObject;

class RestartStuckTaskServiceTest extends TestCase
{
    /** @var RestartStuckTaskService */
    private $subject;

    /** @var TaskLogInterface|MockObject */
    private $taskLog;

    /** @var QueueDispatcherInterface|MockObject */
    private $queueDispatcher;

    public function setUp(): void
    {
        $this->taskLog = $this->createMock(TaskLogInterface::class);
        $this->queueDispatcher = $this->createMock(QueueDispatcherInterface::class);
        $this->subject = new RestartStuckTaskService();
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    TaskLogInterface::SERVICE_ID => $this->taskLog,
                    QueueDispatcherInterface::SERVICE_ID => $this->queueDispatcher,
                ]
            )
        );
    }

    public function testRestart(): void
    {
        $queue = $this->createMock(QueueInterface::class);
        $broker = $this->createMock(RdsQueueBroker::class);
        $taskLogEntity = $this->createMock(EntityInterface::class);
        $task = $this->createMock(TaskInterface::class);
        $taskLogs = [$taskLogEntity];

        $stuckTask = new StuckTask(
            $taskLogEntity,
            'queue',
            $task,
            '123'
        );

        $this->queueDispatcher
            ->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue);

        $this->taskLog
            ->expects($this->once())
            ->method('setStatus')
            ->willReturn($taskLogs);

        $queue->expects($this->once())
            ->method('getBroker')
            ->willReturn($broker);

        $broker->expects($this->once())
            ->method('changeTaskVisibility');

        $this->subject->restart($stuckTask);
    }
}
