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

namespace oat\taoTaskQueue\model\Repository;

use InvalidArgumentException;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\TaskLog;
use oat\tao\model\taskQueue\TaskLog\Broker\TaskLogBrokerInterface;
use oat\tao\model\taskQueue\TaskLog\TaskLogFilter;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;
use oat\taoTaskQueue\model\StuckTask;

class StuckTasksRepository extends ConfigurableService
{
    public function findAll(StuckTasksQuery $query): StuckTasksCollection
    {
        $taskLog = $this->getTaskLog();

        /** @var RdsQueueBroker $broker */
        $broker = $this->getQueueDispatcher()
            ->getQueue($query->getQueryName())
            ->getBroker();

        if ($broker instanceof RdsQueueBroker) {
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
            ->addFilter(TaskLogBrokerInterface::COLUMN_STATUS, 'IN', [TaskLog::STATUS_ENQUEUED])
            ->addFilter(TaskLogBrokerInterface::COLUMN_UPDATED_AT, '<=', $query->getAgeDateTime()->format(DATE_ATOM));

        $taskLogs = $taskLog->search($filter);

        $tasks = new StuckTasksCollection(...[]);

        foreach ($taskLogs as $taskLogEntity) {
            $task = $broker->getTaskByTaskLogId($taskLogEntity->getId());

            $tasks->add(
                new StuckTask(
                    $taskLogEntity,
                    $query->getQueryName(),
                    $task
                )
            );
        }

        return $tasks;
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
