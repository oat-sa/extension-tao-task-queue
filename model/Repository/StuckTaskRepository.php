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

namespace oat\taoTaskQueue\model\Repository;

use InvalidArgumentException;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\TaskLog\Broker\TaskLogBrokerInterface;
use oat\tao\model\taskQueue\TaskLog\Entity\EntityInterface;
use oat\tao\model\taskQueue\TaskLog\TaskLogFilter;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;
use oat\taoTaskQueue\model\StuckTask;

class StuckTaskRepository extends ConfigurableService
{
    public const MIN_AGE = 180;

    public function findAll(StuckTaskQuery $query): StuckTaskCollection
    {
        $taskLog = $this->getTaskLog();
        $broker = $this->getQueueDispatcher()
            ->getQueue($query->getQueryName())
            ->getBroker();

        if (!$broker instanceof RdsQueueBroker) {
            throw new InvalidArgumentException(
                sprintf(
                    'Broker %s for queue %s is not supported. Supported only %s',
                    $broker->getBrokerId(),
                    $query->getQueryName(),
                    RdsQueueBroker::class
                )
            );
        }

        $filter = (new TaskLogFilter())
            ->addFilter(TaskLogBrokerInterface::COLUMN_TASK_NAME, 'IN', $query->getWhitelist())
            ->addFilter(TaskLogBrokerInterface::COLUMN_STATUS, 'IN', $query->getStatuses())
            ->addFilter(
                TaskLogBrokerInterface::COLUMN_UPDATED_AT,
                '<=',
                $query->getAgeDateTime()->format('Y-m-d H:i:s')
            );

        $taskLogs = $taskLog->search($filter);

        $tasks = new StuckTaskCollection(...[]);

        foreach ($taskLogs as $taskLogEntity) {
            if (!$this->isAgeConsistent($taskLogEntity, $query)) {
                continue;
            }

            $task = $broker->getTaskByTaskLogId($taskLogEntity->getId());

            $tasks->add(
                new StuckTask(
                    $taskLogEntity,
                    $query->getQueryName(),
                    $task ? $task->getTask() : null,
                    $task ? $task->getTaskId() : null
                )
            );
        }

        return $tasks;
    }

    private function isAgeConsistent(EntityInterface $taskLogEntity, StuckTaskQuery $query): bool
    {
        return ($query->getAgeDateTime()->getTimestamp() - $taskLogEntity->getCreatedAt()->getTimestamp()) >= 0;
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
