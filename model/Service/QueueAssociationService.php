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

namespace oat\taoTaskQueue\model\Service;

use InvalidArgumentException;
use oat\oatbox\action\Action;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\taskQueue\Queue;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;
use oat\taoTaskQueue\scripts\tools\InitializeQueue;

class QueueAssociationService extends ConfigurableService
{
    public function associate(string $taskClass, string $queue): InitializeQueue
    {
        $targetClass = $this->getTargetClass($taskClass);

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

    private function getTargetClass(string $taskClass): string
    {
        if (class_exists($taskClass) && is_a($taskClass, Action::class, true)) {
            return $taskClass;
        }
        throw new InvalidArgumentException(
            sprintf('%s - Task must extend %s', $taskClass, Action::class)
        );
    }

    private function getQueueDispatcher(): QueueDispatcher
    {
        return $this->getServiceLocator()->get(QueueDispatcher::SERVICE_ID);
    }
}
