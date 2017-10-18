<?php

namespace oat\taoTaskQueue\test\model;

use oat\taoTaskQueue\model\Queue;
use oat\taoTaskQueue\model\QueueBroker\InMemoryQueueBroker;
use oat\taoTaskQueue\model\QueueDispatcher;
use oat\taoTaskQueue\model\Task\AbstractTask;
use oat\taoTaskQueue\model\Task\CallbackTaskInterface;
use oat\taoTaskQueue\test\model\Asset\CallableFixture;

/**
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class QueueDispatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testDispatcherWhenQueuesAreEmptyThenThrowException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Queues needs to be set');
        new QueueDispatcher([]);
    }

    public function testDispatcherWhenDuplicatedQueuesAreSetThenThrowException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/There are duplicated Queue names/');
        new QueueDispatcher([
            QueueDispatcher::OPTION_QUEUES =>[
                new Queue('queueA', new InMemoryQueueBroker()),
                new Queue('queueA', new InMemoryQueueBroker())
            ]
        ]);
    }

    public function testDispatcherWhenNotRegisteredQueueIsUsedForTaskThenThrowException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/There are duplicated Queue names/');
        new QueueDispatcher([
            QueueDispatcher::OPTION_QUEUES => [
                new Queue('queueA', new InMemoryQueueBroker()),
                new Queue('queueA', new InMemoryQueueBroker())
            ],
            QueueDispatcher::OPTION_LINKED_TASKS => [
                'fake/class/name' => 'fake_queue_name'
            ]
        ]);
    }

    public function testCreateTaskWhenUsingANewTaskImplementingTaskInterfaceShouldReturnCallbackTask()
    {
        $taskMock = $this->getMockForAbstractClass(AbstractTask::class, [], "", false);

        $queueMock = $this->getMockBuilder(QueueDispatcher::class)
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
        $queueMock = $this->getMockBuilder(QueueDispatcher::class)
            ->disableOriginalConstructor()
            ->setMethods(['enqueue'])
            ->getMock();

        $queueMock->expects($this->once())
            ->method('enqueue')
            ->willReturn($this->returnValue(true));

        $this->assertInstanceOf(CallbackTaskInterface::class, $queueMock->createTask([CallableFixture::class, 'exampleStatic'], []) );
    }
}