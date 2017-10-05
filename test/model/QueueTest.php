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

use oat\taoTaskQueue\test\model\Asset\CallableFixture;
use oat\oatbox\service\ServiceManager;
use oat\taoTaskQueue\model\AbstractTask;
use oat\taoTaskQueue\model\CallbackTaskInterface;
use oat\taoTaskQueue\model\QueueBroker\QueueBrokerInterface;
use oat\taoTaskQueue\model\Queue;
use oat\taoTaskQueue\model\TaskLogInterface;

/**
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class QueueTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideServiceOptions
     */
    public function testQueueServiceOptionsAtInstantiationForException(array $options, $exceptionMessage)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);
        new Queue($options);
    }

    public function provideServiceOptions()
    {
        return [
            'MissingQueueName' => [[], 'Queue name needs to be set.'],
            'QueueNameEmpty' => [['queue_name' => ""], 'Queue name needs to be set.'],
            'MissingQueueBroker' => [['queue_name' => "queue"], 'Queue Broker service needs to be set.'],
            'QueueBrokerEmpty' => [['queue_name' => "queue", 'queue_broker' => ""], 'Queue Broker service needs to be set.'],
        ];
    }

    public function testGetNameShouldReturnTheValueOfQueueNameOption()
    {
        $queue = new Queue([
            'queue_name' => 'fakeQueue',
            'queue_broker' => 'fakeBroker'
        ]);
        $this->assertEquals('fakeQueue', $queue->getName());
    }

    public function testGetBrokerInstantiatingTheBrokerAndReturningItWithTheRequiredInterface()
    {
        $queueBrokerMock = $this->createMock(QueueBrokerInterface::class);

        $serviceManagerMock = $this->getMockBuilder(ServiceManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();

        $serviceManagerMock->expects($this->once())
            ->method('get')
            ->willReturn($queueBrokerMock);

        $queueMock = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->setMethods(['getServiceManager', 'getOption', 'getName'])
            ->getMock();

        $queueMock->expects($this->once())
            ->method('getServiceManager')
            ->willReturn($serviceManagerMock);

        $queueMock->expects($this->once())
            ->method('getOption')
            ->willReturn('broker/serviceName');

        $queueMock->expects($this->once())
            ->method('getName')
            ->willReturn('fakeQueueName');

        $brokerCaller = function () {
            return $this->getBroker();
        };

        // Bind the closure to $queueMock's scope.
        // $bound is now a Closure, and calling it is like asking $queueMock to call $this->getBroker(); and return the results.
        $bound = $brokerCaller->bindTo($queueMock, $queueMock);

        $this->assertInstanceOf(QueueBrokerInterface::class, $bound());
    }

    public function testGetTaskLogInstantiatingTheTaskLogAndReturningItWithTheRequiredInterface()
    {
        $taskLogMock = $this->createMock(TaskLogInterface::class);

        $serviceManagerMock = $this->getMockBuilder(ServiceManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();

        $serviceManagerMock->expects($this->once())
            ->method('get')
            ->willReturn($taskLogMock);

        $queueMock = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->setMethods(['getServiceManager', 'getOption'])
            ->getMock();

        $queueMock->expects($this->once())
            ->method('getServiceManager')
            ->willReturn($serviceManagerMock);

        $queueMock->expects($this->once())
            ->method('getOption')
            ->willReturn('broker/serviceName');

        $taskLogCaller = function () {
            return $this->getTaskLog();
        };

        $bound = $taskLogCaller->bindTo($queueMock, $queueMock);

        $this->assertInstanceOf(TaskLogInterface::class, $bound());
    }

    public function testCreateTaskWhenUsingANewTaskImplementingTaskInterfaceShouldReturnCallbackTask()
    {
        $taskMock = $this->getMockForAbstractClass(AbstractTask::class, [], "", false);

        $queueMock = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->setMethods(['enqueue'])
            ->getMock();

        $queueMock->expects($this->once())
            ->method('enqueue')
            ->willReturn($this->returnValue(true));

        $this->assertInstanceOf(CallbackTaskInterface::class, $queueMock->createTask($taskMock, []) );
    }

    public function testCreateTaskWhenUsingStaticClassMethodCallShouldReturnCallbackTask()
    {
        $queueMock = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->setMethods(['enqueue'])
            ->getMock();

        $queueMock->expects($this->once())
            ->method('enqueue')
            ->willReturn($this->returnValue(true));

        $this->assertInstanceOf(CallbackTaskInterface::class, $queueMock->createTask([CallableFixture::class, 'exampleStatic'], []) );
    }

    /**
     * @dataProvider provideEnqueueOptions
     */
    public function testEnqueueWhenTaskPushedOrNot($isEnqueued, $expected)
    {
        $taskMock = $this->getMockForAbstractClass(AbstractTask::class, [], "", false);

        $queueBrokerMock = $this->createMock(QueueBrokerInterface::class);

        $queueBrokerMock->expects($this->once())
            ->method('push')
            ->willReturn($isEnqueued);

        $taskLogMock = $this->createMock(TaskLogInterface::class);

        $queueMock = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBroker', 'getTaskLog', 'isSync', 'runWorker'])
            ->getMock();

        $queueMock->expects($this->once())
            ->method('getBroker')
            ->willReturn($queueBrokerMock);

        if ($isEnqueued) {
            $taskLogMock->expects($this->once())
                ->method('add');

            $queueMock->expects($this->once())
                ->method('getTaskLog')
                ->willReturn($taskLogMock);

            $queueMock->expects($this->once())
                ->method('isSync')
                ->willReturn(true);

            $queueMock->expects($this->once())
                ->method('runWorker');
        }

        $this->assertEquals($expected, $queueMock->enqueue($taskMock));
    }

    public function provideEnqueueOptions()
    {
        return [
            'ShouldBeSuccessful' => [true, true],
            'ShouldBeFailed' => [false, false],
        ];
    }

    /**
     * @dataProvider provideDequeueOptions
     */
    public function testDequeueWhenTaskPoppedOrNot($dequeuedElem, $expected)
    {
        $queueBrokerMock = $this->createMock(QueueBrokerInterface::class);

        $queueBrokerMock->expects($this->once())
            ->method('pop')
            ->willReturn($dequeuedElem);

        $taskLogMock = $this->createMock(TaskLogInterface::class);

        $queueMock = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBroker', 'getTaskLog'])
            ->getMock();

        $queueMock->expects($this->once())
            ->method('getBroker')
            ->willReturn($queueBrokerMock);

        if ($dequeuedElem) {
            $taskLogMock->expects($this->once())
                ->method('setStatus');

            $queueMock->expects($this->once())
                ->method('getTaskLog')
                ->willReturn($taskLogMock);
        }

        $this->assertEquals($expected, $queueMock->dequeue());
    }

    public function provideDequeueOptions()
    {
        $taskMock = $this->getMockForAbstractClass(AbstractTask::class, [], "", false);

        return [
            'ShouldBeSuccessful' => [$taskMock, $taskMock],
            'ShouldBeFailed' => [null, null],
        ];
    }

    public function testAcknowledgeShouldCallDeleteOnBroker()
    {
        $taskMock = $this->getMockForAbstractClass(AbstractTask::class, [], "", false);

        $queueBrokerMock = $this->createMock(QueueBrokerInterface::class);

        $queueBrokerMock->expects($this->once())
            ->method('delete');

        $queueMock = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBroker'])
            ->getMock();

        $queueMock->expects($this->once())
            ->method('getBroker')
            ->willReturn($queueBrokerMock);

        $queueMock->acknowledge($taskMock);
    }

    public function testCountShouldCallCountOnBroker()
    {
        $taskMock = $this->getMockForAbstractClass(AbstractTask::class, [], "", false);

        $queueBrokerMock = $this->createMock(QueueBrokerInterface::class);

        $queueBrokerMock->expects($this->once())
            ->method('count');

        $queueMock = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBroker'])
            ->getMock();

        $queueMock->expects($this->once())
            ->method('getBroker')
            ->willReturn($queueBrokerMock);

        $queueMock->count($taskMock);
    }

    public function testInitializeShouldCallCreateQueueOnBroker()
    {
        $taskMock = $this->getMockForAbstractClass(AbstractTask::class, [], "", false);

        $queueBrokerMock = $this->createMock(QueueBrokerInterface::class);

        $queueBrokerMock->expects($this->once())
            ->method('createQueue');

        $queueMock = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBroker'])
            ->getMock();

        $queueMock->expects($this->once())
            ->method('getBroker')
            ->willReturn($queueBrokerMock);

        $queueMock->initialize($taskMock);
    }
}