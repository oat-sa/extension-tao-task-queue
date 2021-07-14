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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */

declare(strict_types=1);

namespace oat\taoTaskQueue\scripts\tools;

use DateTimeImmutable;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\TaskLog;
use oat\tao\model\taskQueue\TaskLog\Broker\TaskLogBrokerInterface;
use oat\tao\model\taskQueue\TaskLog\CollectionInterface;
use oat\tao\model\taskQueue\TaskLog\Entity\EntityInterface;
use oat\tao\model\taskQueue\TaskLog\TaskLogFilter;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;

class RescheduleStuckTasks extends ScriptAction
{
    protected function provideOptions(): array
    {
        return [
            'whitelist' => [
                'prefix' => 'w',
                'longPrefix' => 'whitelist',
                'cast' => 'string',
                'required' => true,
                'description' => 'The whitelist of task_name that can be reschedule.'
            ],
            'queue' => [
                'prefix' => 'q',
                'longPrefix' => 'queue',
                'cast' => 'string',
                'required' => true,
                'description' => 'The queue to consider in the scope.'
            ],
            'age' => [
                'prefix' => 'w',
                'longPrefix' => 'age',
                'cast' => 'int',
                'required' => false,
                'default' => 300,
                'description' => 'Age in seconds of a task log.'
            ]
        ];
    }

    protected function provideDescription(): string
    {
        return 'Reschedule stuck tasks in the queue';
    }

    protected function run(): Report
    {
        /**
         * 1 - Collect stuck tasks.
         * 2 - Check number of retries (added as extra parameters in the task).
         * 3 - If retries < maximum allowed:
         * - GET the task based on task.report and task_log_id
         * - CANCEL the task_log, so it will not be reexecuted
         * - Set the task to visible = t
         * - Update the updated_at field with current date.
         * - Remove lock Queue::createLock for reference
         *
         * tq_indexation_queue.id == tq_task_log.id
         * 'oat\\tao\\model\\taskQueue\\Queue' <======== Is the queue task
         *
         * 4 - If retries >= maximum allowed:
         * - Mark task as failed
         * - Add extra information in the report.
         */

        $whitelist = $this->getWhitelist();
        $age = $this->getAge();

        /** @var EntityInterface[] $taskLogs */
        $taskLogs = $this->getStuckTaskLogs($whitelist, $age);

        $taskLog = $this->getTaskLog();

        //FIXME How to get the queue based on the task log?
        /** @var QueueDispatcherInterface $queueService */
        $queueService = $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);
        $queueName = $this->getOption('queue');
        $queue = $queueService->getQueue($queueName);
        $broker = $queue->getBroker();

        if (!$broker instanceof RdsQueueBroker) {
            return Report::createError(
                sprintf(
                    'Broker %s is not supported',
                    $broker->getBrokerId()
                )
            );
        }

        $report = Report::createInfo(
            sprintf(
                'Rescheduling tasks for: age <= %s, tasks names IN %s',
                $age->format(DATE_ATOM),
                implode(',', $whitelist)
            )
        );

        foreach ($taskLogs as $taskLogEntity) {
            $task = $broker->getTaskByTaskLogId($taskLogEntity->getId());

            if (!$task) {
                $report->add(
                    Report::createError(
                        sprintf(
                            'Task not found for taskLog %s and queue %s',
                            $taskLogEntity->getId(),
                            $queueName
                        )
                    )
                );

                continue;
            }

            $broker->changeTaskVisibility($task->getId(), true);
            $taskLog->setStatus($task->getId(), TaskLogInterface::STATUS_ENQUEUED); // Necessary to change updated_at field

            $report->add(
                Report::createSuccess(
                    sprintf(
                        'Rescheduling taskLog id %s, label %s, taskName %s,',
                        $taskLogEntity->getId(),
                        $taskLogEntity->getLabel(),
                        $taskLogEntity->getTaskName()
                    )
                )
            );
        }

        return $report;
    }

    private function getStuckTaskLogs(array $whitelist, DateTimeImmutable $age): CollectionInterface
    {
        //@TODO move this to a service:

        $taskLog = $this->getTaskLog();

        $filter = (new TaskLogFilter())
            ->addFilter(TaskLogBrokerInterface::COLUMN_TASK_NAME, 'IN', $whitelist)
            ->addFilter(TaskLogBrokerInterface::COLUMN_STATUS, 'IN', [TaskLog::STATUS_ENQUEUED])
            ->addFilter(TaskLogBrokerInterface::COLUMN_UPDATED_AT, '<=', $age->format(DATE_ATOM));

        return $taskLog->search($filter);
    }

    private function getWhitelist(): array
    {
        $whitelist = explode(',', (string)$this->getOption('whitelist'));
        array_walk(
            $whitelist,
            function (&$value) {
                $value = trim($value);
            }
        );

        return $whitelist;
    }

    private function getAge(): DateTimeImmutable
    {
        $age = max((int)$this->getOption('age'), 300); // Min 300 sec old... Older than that is too soon
        $date = new DateTimeImmutable();
        $date->modify(sprintf('-%s seconds', $age));

        return $date;
    }

    private function getTaskLog(): TaskLogInterface
    {
        return $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);
    }
}
