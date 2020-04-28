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
 * Copyright (c) 2019. (original work) Open Assessment Technologies SA;
 */

namespace oat\taoTaskQueue\test\unit;

use common_report_Report;
use oat\generis\test\TestCase;
use oat\oatbox\session\SessionService;
use oat\taoSync\model\OfflineMachineChecksService;
use oat\taoTaskQueue\scripts\tools\RunWorker;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\tao\model\taskQueue\QueueInterface;
use oat\oatbox\log\LoggerService;
use oat\tao\model\taskQueue\Task\TaskInterface;

class RunWorkerTest extends TestCase
{
    /**
     * Test getReport method
     */
    public function testGetReport()
    {
        $sessionService = $this->createMock(SessionService::class);
        $task = $this->prophesize(TaskInterface::class);
        $queue = $this->prophesize(QueueInterface::class);
        $queue->getNumberOfTasksToReceive()->willReturn(1);
        $queue->getName()->willReturn("unitQueue");
        $queue->dequeue()->willReturn($task->reveal());
        $dispatch = $this->prophesize(QueueDispatcherInterface::class);
        $dispatch->isSync()->willReturn(false);
        $dispatch->getQueue('unitQueue')->willReturn($queue->reveal());
        $tasklog = $this->prophesize(TaskLogInterface::class);
        $log = $this->prophesize(LoggerService::class);
        $sl = $this->getServiceLocatorMock([
            QueueDispatcherInterface::SERVICE_ID => $dispatch->reveal(),
            TaskLogInterface::SERVICE_ID => $tasklog->reveal(),
            LoggerService::SERVICE_ID => $log->reveal(),
            SessionService::class=>$sessionService
        ]);

        $worker = new RunWorker();
        $worker->setServiceLocator($sl);
        $report = $worker->__invoke(["--queue=unitQueue", "--limit=1"]);
        $this->assertInstanceOf(common_report_Report::class, $report, 'Returned report must be as expected.');
        $this->assertEquals(common_report_Report::TYPE_SUCCESS, $report->getType());
    }
}
