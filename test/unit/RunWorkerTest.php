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
 * Copyright (c) 2019-2023 (original work) Open Assessment Technologies SA.
 */

namespace oat\taoTaskQueue\test\unit;

use common_report_Report;
use oat\generis\test\ServiceManagerMockTrait;
use oat\oatbox\session\SessionService;
use oat\taoTaskQueue\scripts\tools\RunWorker;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\tao\model\taskQueue\QueueInterface;
use oat\oatbox\log\LoggerService;
use oat\tao\model\taskQueue\Task\TaskInterface;
use PHPUnit\Framework\TestCase;

class RunWorkerTest extends TestCase
{
    use ServiceManagerMockTrait;

    public function testGetReport()
    {
        $queue = $this->createMock(QueueInterface::class);
        $queue
            ->method('getNumberOfTasksToReceive')
            ->willReturn(1);
        $queue
            ->method('getName')
            ->willReturn('unitQueue');
        $queue
            ->method('dequeue')
            ->willReturn($this->createMock(TaskInterface::class));
        $queue
            ->method('hasPreFetchedMessages')
            ->willReturn(false);

        $dispatch = $this->createMock(QueueDispatcherInterface::class);
        $dispatch
            ->method('isSync')
            ->willReturn(false);
        $dispatch
            ->method('getQueue')
            ->with('unitQueue')
            ->willReturn($queue);

        $serviceLocator = $this->getServiceManagerMock([
            QueueDispatcherInterface::SERVICE_ID => $dispatch,
            TaskLogInterface::SERVICE_ID => $this->createMock(TaskLogInterface::class),
            LoggerService::SERVICE_ID => $this->createMock(LoggerService::class),
            SessionService::class => $this->createMock(SessionService::class),
        ]);

        $worker = new RunWorker();
        $worker->setServiceLocator($serviceLocator);
        $report = $worker->__invoke(['--queue=unitQueue', '--limit=1']);

        $this->assertInstanceOf(
            common_report_Report::class,
            $report,
            'Returned report must be as expected.'
        );
        $this->assertEquals(common_report_Report::TYPE_SUCCESS, $report->getType());
    }
}
