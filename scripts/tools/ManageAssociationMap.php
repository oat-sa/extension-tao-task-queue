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
use oat\tao\model\taskQueue\Queue;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;
use oat\taoTaskQueue\model\Service\QueueAssociationService;
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
                'description' => 'Define task queue to add',
            ],

            'taskClass' => [
                'prefix' => 't',
                'longPrefix' => 'taskClass',
                'required' => true,
                'cast' => 'string',
                'description' => sprintf(
                    'Define task (must extend "%s")',
                    Action::class
                ),
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
            'description' => 'Prints the help.',
        ];
    }

    protected function run()
    {
        $initializer = $this->getQueueAssociationService()
            ->addTaskQueueAssociations(
                $this->getOption('taskClass'),
                $queue = $this->getOption('queue')
            );

        return $initializer([]);
    }

    private function getQueueAssociationService(): QueueAssociationService
    {
        return $this->getServiceLocator()->get(QueueAssociationService::class);
    }
}
