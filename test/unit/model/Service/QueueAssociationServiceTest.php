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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoTaskQueue\test\unit\model\Service;

use oat\generis\test\MockObject;
use oat\generis\test\TestCase;
use oat\oatbox\log\LoggerService;
use oat\oatbox\service\ServiceManager;
use oat\oatbox\task\TaskInterface\TaskQueue;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoMediaManager\model\relation\task\MediaToMediaRelationMigrationTask;
use oat\taoTaskQueue\model\Service\QueueAssociationService;
use Prophecy\Argument;

class QueueAssociationServiceTest extends TestCase
{
    /** @var QueueAssociationService */
    private $subject;

    /** @var QueueDispatcher|MockObject */
    private $queueDispatcherMock;

    /** @var ServiceManager|MockObject */
    private $serviceManagerMock;

    /** @var LoggerService|MockObject */
    private $logger;

    public function setUp(): void
    {
        $this->queueDispatcherMock = $this->createMock(QueueDispatcher::class);
        $this->logger = $this->createMock(LoggerService::class);
        $this->subject = new QueueAssociationService();

        $this->serviceManagerMock = $this->createMock(ServiceManager::class);
        $services = [
            QueueDispatcher::SERVICE_ID => $this->queueDispatcherMock
        ];

        $this->serviceManagerMock
            ->method('get')
            ->willReturnCallback(function ($id) use ($services) {
                return $services[$id];
            });


        $this->subject->setServiceLocator($this->serviceManagerMock);
    }

    public function testAddTaskQueueAssociations(): void
    {
        $this->serviceManagerMock
            ->expects($this->once())
            ->method('register');

        $this->queueDispatcherMock
            ->method('getOption')
            ->withConsecutive(
                [
                    QueueDispatcherInterface::OPTION_QUEUES,
                ],
                [
                    QueueDispatcherInterface::OPTION_TASK_TO_QUEUE_ASSOCIATIONS,
                ]
            )
            ->willReturnOnConsecutiveCalls(
                [
                    'queue1',
                    'queue2',
                ],
                [
                    'classA' => 'queue1',
                ]
            );

        $this->queueDispatcherMock
            ->expects($this->once())
            ->method('setOptions')
            ->withAnyParameters();

        $this->queueDispatcherMock
            ->expects($this->once())
            ->method('getOptions')
            ->willReturn([]);

        $this->subject->addTaskQueueAssociations(MediaToMediaRelationMigrationTask::class, 'newQueueName');
    }
}
