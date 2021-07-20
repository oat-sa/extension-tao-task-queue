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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoTaskQueue\model\Service;

use InvalidArgumentException;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;
use oat\taoTaskQueue\model\StuckTask;

class RestartStuckTaskService extends ConfigurableService
{
    public function restart(StuckTask $stuckTask): void
    {
        $taskLogEntity = $stuckTask->getTaskLog();
        $broker = $this->getQueueDispatcher()
            ->getQueue($stuckTask->getQueueName())
            ->getBroker();

        if (!$broker instanceof RdsQueueBroker) {
            throw new InvalidArgumentException(
                sprintf(
                    'Broker %s for queue %s is not supported. Supported only %s',
                    $broker->getBrokerId(),
                    $stuckTask->getQueueName(),
                    RdsQueueBroker::class
                )
            );
        }

        if ($stuckTask->isOrphan()) {
            $callback = $taskLogEntity->getTaskName();

            $this->getTaskLog()->getBroker()->updateStatus(
                $taskLogEntity->getId(),
                TaskLogInterface::STATUS_CANCELLED
            );

            $this->getQueueDispatcher()->createTask(
                new $callback(),
                $taskLogEntity->getParameters(),
                $taskLogEntity->getLabel()
            );

            return;
        }

        $broker->changeTaskVisibility($stuckTask->getTaskId(), true);

        $this->getTaskLog()->setStatus($stuckTask->getTaskId(), TaskLogInterface::STATUS_ENQUEUED);
    }

    private function getTaskLog(): TaskLogInterface
    {
        return $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);
    }

    private function getQueueDispatcher(): QueueDispatcherInterface
    {
        return $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);
    }
}
