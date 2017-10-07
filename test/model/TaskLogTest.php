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

namespace oat\taoTaskQueue\test\model;

use oat\oatbox\service\ServiceManager;
use oat\taoTaskQueue\model\Task\AbstractTask;
use oat\taoTaskQueue\model\TaskLog;
use oat\taoTaskQueue\model\TaskLogBroker\TaskLogBrokerInterface;

/**
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class TaskLogTest extends \PHPUnit_Framework_TestCase
{
    public function testTaskLogServiceShouldThrowExceptionWhenTaskLogBrokerOptionIsNotSet()
    {
        $this->expectException(\InvalidArgumentException::class);
        new TaskLog([]);
    }

    public function testGetBrokerInstantiatingTheTaskLogBrokerAndReturningItWithTheRequiredInterface()
    {
        $logBrokerMock = $this->createMock(TaskLogBrokerInterface::class);

        $serviceManagerMock = $this->getMockBuilder(ServiceManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();

        $serviceManagerMock->expects($this->once())
            ->method('get')
            ->willReturn($logBrokerMock);

        $taskLogMock = $this->getMockBuilder(TaskLog::class)
            ->disableOriginalConstructor()
            ->setMethods(['getServiceManager', 'getOption'])
            ->getMock();

        $taskLogMock->expects($this->once())
            ->method('getServiceManager')
            ->willReturn($serviceManagerMock);

        $taskLogMock->expects($this->once())
            ->method('getOption')
            ->willReturn('fake/service');

        $brokerCaller = function () {
            return $this->getBroker();
        };

        $bound = $brokerCaller->bindTo($taskLogMock, $taskLogMock);

        $this->assertInstanceOf(TaskLogBrokerInterface::class, $bound());
    }

    public function testAddWhenWrongStatusIsSuppliedThenErrorMessageShouldBeLogged()
    {
        $taskMock = $this->getMockForAbstractClass(AbstractTask::class, [], "", false);

        $taskLogMock = $this->getMockBuilder(TaskLog::class)
            ->disableOriginalConstructor()
            ->setMethods(['logError'])
            ->getMock();

        $taskLogMock->expects($this->atLeastOnce())
            ->method('logError');

        $taskLogMock->add($taskMock, 'fake_status');
    }

    public function testAddWhenStatusIsOkayThenTaskShouldBeAddedByBroker()
    {
        $taskMock = $this->getMockForAbstractClass(AbstractTask::class, [], "", false);

        $logBrokerMock = $this->createMock(TaskLogBrokerInterface::class);

        $logBrokerMock->expects($this->once())
            ->method('add');

        $taskLogMock = $this->getMockBuilder(TaskLog::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBroker'])
            ->getMock();

        $taskLogMock->expects($this->once())
            ->method('getBroker')
            ->willReturn($logBrokerMock);;

        $taskLogMock->add($taskMock, 'enqueued');
    }

    public function testSetStatusWhenNewAndPrevStatusIsOkayThenStatusShouldBeUpdatedByBroker()
    {
        $logBrokerMock = $this->createMock(TaskLogBrokerInterface::class);

        $logBrokerMock->expects($this->once())
            ->method('updateStatus');

        $taskLogMock = $this->getMockBuilder(TaskLog::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBroker', 'validateStatus'])
            ->getMock();

        $taskLogMock->expects($this->once())
            ->method('getBroker')
            ->willReturn($logBrokerMock);

        $taskLogMock->expects($this->exactly(2))
            ->method('validateStatus');

        $taskLogMock->setStatus('fakeId', 'dequeued', 'running');
    }

    public function testGetStatusWhenTaskExistItReturnsItsStatus()
    {
        $expectedStatus = 'dequeued';

        $logBrokerMock = $this->createMock(TaskLogBrokerInterface::class);

        $logBrokerMock->expects($this->once())
            ->method('getStatus')
            ->willReturn($expectedStatus);

        $taskLogMock = $this->getMockBuilder(TaskLog::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBroker', 'validateStatus'])
            ->getMock();

        $taskLogMock->expects($this->once())
            ->method('getBroker')
            ->willReturn($logBrokerMock);

        $this->assertEquals($expectedStatus, $taskLogMock->getStatus('existingTaskId'));
    }
}