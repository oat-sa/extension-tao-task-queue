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
 * Copyright (c) 2018 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */

namespace oat\taoTaskQueue\test\scripts\tools;

use oat\taoTaskQueue\scripts\tools\AddTaskToQueue;
use oat\taoTaskQueue\test\scripts\tools\mock\ActionMock;
use oat\taoTaskQueue\test\scripts\tools\mock\ScriptActionMock;
use oat\oatbox\service\ServiceManager;
use oat\taoTaskQueue\model\QueueDispatcherInterface;
use oat\taoTaskQueue\model\QueueDispatcher;
use oat\taoTaskQueue\model\TaskLog;
use common_report_Report as Report;
use oat\taoTaskQueue\test\scripts\tools\mock\TaskLogBrokerMock;
use oat\oatbox\action\ActionService;

class AddTaskToQueueTest extends \PHPUnit_Framework_TestCase
{
    public function testInvoke()
    {
        $serviceLocator = $this->getServiceLocator();
        $action = new AddTaskToQueue();
        $action->setServiceLocator($serviceLocator);

        $result = $action([ActionMock::class, 'foo', 'bar']);
        $this->assertTrue($result instanceof Report);
        $this->assertEquals(['foo', 'bar'], ActionMock::getParams());

        $result = $action([ScriptActionMock::class, '--param1', 'foo', '--param2', 'bar']);
        $this->assertTrue($result instanceof Report);
        $this->assertEquals(['--param1', 'foo', '--param2', 'bar'], ScriptActionMock::getParams());
    }

    /**
     * @return ServiceManager
     * @throws \common_Exception
     * @throws \common_exception_Error
     */
    protected function getServiceLocator()
    {
        $queueDispatcher = new QueueDispatcher([
            'queues' => [
                new \oat\taoTaskQueue\model\Queue('background', new \oat\taoTaskQueue\model\QueueBroker\InMemoryQueueBroker(1), 1)
            ],
            'task_log' => TaskLog::SERVICE_ID,
            'task_selector_strategy' => new \oat\taoTaskQueue\model\TaskSelector\StrictPriorityStrategy(),
            'default_queue' => 'background'
        ]);

        $taskLogBroker = $this->getMockBuilder(\oat\taoTaskQueue\model\TaskLogBroker\TaskLogBrokerInterface::class)->getMock();
        $taskLogBroker->method('updateStatus')->willReturn(1);
        $taskLog = new TaskLog([
            'task_log_broker' => $taskLogBroker
        ]);
        $config = new \common_persistence_KeyValuePersistence([], new \common_persistence_InMemoryKvDriver());
        $config->set(QueueDispatcherInterface::SERVICE_ID, $queueDispatcher);
        $config->set(TaskLog::SERVICE_ID, $taskLog);
        $config->set(ActionService::SERVICE_ID, new ActionService());
        $config->set('generis/log', new \oat\oatbox\log\LoggerService([]));
        $serviceManager = new ServiceManager($config);
        return $serviceManager;
    }
}
