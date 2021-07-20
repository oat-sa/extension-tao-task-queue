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

namespace oat\taoTaskQueue\test\unit\model\Repository;

use oat\generis\test\TestCase;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\QueueInterface;
use oat\tao\model\taskQueue\TaskLog;
use oat\tao\model\taskQueue\TaskLog\Entity\EntityInterface;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;
use oat\taoTaskQueue\model\Repository\StuckTaskQuery;
use oat\taoTaskQueue\model\Repository\StuckTaskRepository;
use PHPUnit\Framework\MockObject\MockObject;

class StuckTaskRepositoryTest extends TestCase
{
    /** @var StuckTaskRepository */
    private $subject;

    /** @var TaskLogInterface|MockObject */
    private $taskLog;

    /** @var QueueDispatcherInterface|MockObject */
    private $queueDispatcher;

    public function setUp(): void
    {
        $this->taskLog = $this->createMock(TaskLogInterface::class);
        $this->queueDispatcher = $this->createMock(QueueDispatcherInterface::class);
        $this->subject = new StuckTaskRepository();
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    TaskLogInterface::SERVICE_ID => $this->taskLog,
                    QueueDispatcherInterface::SERVICE_ID => $this->queueDispatcher,
                ]
            )
        );
    }

    public function testFindAll(): void
    {
        $query = new StuckTaskQuery(
            'query',
            [
                'task2',
            ],
            [
                TaskLog::STATUS_ENQUEUED,
            ],
            StuckTaskRepository::MIN_AGE
        );

        $queue = $this->createMock(QueueInterface::class);
        $broker = $this->createMock(RdsQueueBroker::class);
        $taskLogEntity = $this->createMock(EntityInterface::class);
        $taskLogs = [$taskLogEntity];

        $this->taskLog
            ->expects($this->once())
            ->method('search')
            ->willReturn($taskLogs);

        $this->queueDispatcher
            ->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue);

        $queue->expects($this->once())
            ->method('getBroker')
            ->willReturn($broker);

        $broker->expects($this->once())
            ->method('getTaskByTaskLogId')
            ->willReturn(null);

        $taskLogEntity->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn($query->getAgeDateTime());

        $taskLogEntity->expects($this->once())
            ->method('getId')
            ->willReturn('id');

        $collection = $this->subject->findAll($query);

        $this->assertCount(1, $collection);
    }
}
