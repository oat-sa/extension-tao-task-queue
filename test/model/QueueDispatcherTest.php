<?php

namespace oat\taoTaskQueue\test\model;

use oat\taoTaskQueue\model\Queue;
use oat\taoTaskQueue\model\QueueBroker\InMemoryQueueBroker;
use oat\taoTaskQueue\model\QueueDispatcher;
use oat\taoTaskQueue\model\Task\AbstractTask;
use oat\taoTaskQueue\model\Task\CallbackTaskInterface;
use oat\taoTaskQueue\test\model\Asset\CallableFixture;

/**
 * @deprecated
 */
class QueueDispatcherTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        parent::setUp();

        require_once __DIR__ .'/../../../tao/includes/raw_start.php';
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage  Queues needs to be set
     */
    public function testDispatcherWhenQueuesAreEmptyThenThrowException()
    {
        new QueueDispatcher([]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectExceptionMessageRegExp  /There are duplicated Queue names/
     */
    public function testDispatcherWhenDuplicatedQueuesAreSetThenThrowException()
    {
        new QueueDispatcher([
            QueueDispatcher::OPTION_QUEUES =>[
                new Queue('queueA', new InMemoryQueueBroker()),
                new Queue('queueA', new InMemoryQueueBroker())
            ]
        ]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectExceptionMessageRegExp  There are duplicated Queue names/
     */
    public function testDispatcherWhenNotRegisteredQueueIsUsedForTaskThenThrowException()
    {
        new QueueDispatcher([
            QueueDispatcher::OPTION_QUEUES => [
                new Queue('queueA', new InMemoryQueueBroker()),
                new Queue('queueA', new InMemoryQueueBroker())
            ],
            QueueDispatcher::OPTION_TASK_TO_QUEUE_ASSOCIATIONS => [
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