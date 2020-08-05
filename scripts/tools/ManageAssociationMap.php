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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA
 */

declare(strict_types=1);

namespace oat\taoTaskQueue\scripts\tools;

use common_Exception;
use common_exception_Error;
use InvalidArgumentException;
use oat\oatbox\action\Action;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\service\exception\InvalidServiceManagerException;
use oat\tao\model\taskQueue\Queue;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class ManageAssociationMap extends ScriptAction
{
    use ServiceLocatorAwareTrait;

    protected function provideOptions()
    {
        return [
            'queue' => [
                'prefix' => 'q',
                'longPrefix' => 'queue',
                'required' => false,
                'cast' => 'string',
                'description' => 'Define task queue to add'
            ],

            'taskClass' => [
                'prefix' => 't',
                'longPrefix' => 'taskClass',
                'required' => true,
                'cast' => 'string',
                'description' => sprintf(
                    'Define task (must extend "%s")',
                    Action::class
                )
            ],
        ];
    }

    protected function provideDescription()
    {
        return 'Command will define association for a task to specific queue.';
    }

    protected function provideUsage()
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Prints the help.'
        ];
    }

    protected function run()
    {
        $initializer = $this->addTaskQueueAssociations();
        return $initializer([]);
    }

    private function getTargetClass()
    {
        $taskClass = $this->getOption('taskClass');

        if (class_exists($taskClass) && is_a($taskClass, Action::class, true)) {
            return $taskClass;
        }
        throw new InvalidArgumentException(
            sprintf('Task must extend %s', Action::class)
        );
    }

    /**
     * @throws common_Exception
     * @throws common_exception_Error
     * @throws InvalidServiceManagerException
     */
    private function addTaskQueueAssociations(): InitializeQueue
    {
        $queue = $this->getOption('queue');
        $targetClass = $this->getTargetClass();

        $existingQueues = $this->getQueueDispatcher()->getOption(QueueDispatcherInterface::OPTION_QUEUES);
        $newQueue = new Queue($queue, new RdsQueueBroker('default', 1), 30);
        $existingOptions = $this->getQueueDispatcher()->getOptions();
        $existingOptions[QueueDispatcherInterface::OPTION_QUEUES] = array_unique(
            array_merge($existingQueues, [$newQueue])
        );
        $existingAssociations = $this->getQueueDispatcher()->getOption(QueueDispatcherInterface::OPTION_TASK_TO_QUEUE_ASSOCIATIONS);
        $existingOptions[QueueDispatcherInterface::OPTION_TASK_TO_QUEUE_ASSOCIATIONS] = array_merge(
            $existingAssociations,
            [$targetClass => $queue]
        );
        $this->getQueueDispatcher()->setOptions($existingOptions);

        $this->getServiceManager()->register(QueueDispatcherInterface::SERVICE_ID, $this->getQueueDispatcher());

        $initializer = new InitializeQueue();
        $this->propagate($initializer);
        return $initializer;
    }

    protected function getQueueDispatcher(): QueueDispatcher
    {
        return $this->getServiceLocator()->get(QueueDispatcher::SERVICE_ID);
    }
}
