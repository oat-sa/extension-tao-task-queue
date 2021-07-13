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
use oat\tao\model\taskQueue\TaskLog;
use oat\tao\model\taskQueue\TaskLog\Broker\TaskLogBrokerInterface;
use oat\tao\model\taskQueue\TaskLog\CollectionInterface;
use oat\tao\model\taskQueue\TaskLog\Entity\EntityInterface;
use oat\tao\model\taskQueue\TaskLog\TaskLogFilter;
use oat\tao\model\taskQueue\TaskLogInterface;

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
            'age' => [
                'prefix' => 'w',
                'longPrefix' => 'age',
                'cast' => 'int',
                'required' => true,
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
        $whitelist = $this->getWhitelist();
        $age = $this->getAge();

        /** @var EntityInterface[] $taskLogs */
        $taskLogs = $this->getStuckTaskLogs($whitelist, $age);

        //FIXME Testing...
        foreach ($taskLogs as $taskLog) {
            echo PHP_EOL;
            echo $taskLog->getId();
            echo PHP_EOL;
            echo $taskLog->getLabel();
            echo PHP_EOL;
            echo $taskLog->getTaskName();
            echo PHP_EOL;
            echo '-------------------------------------';
            echo PHP_EOL;
        }

        /* @TODO
         *
         * 1 - Collect stuck tasks.
         * 2 - Check number of retries (added as extra parameters in the task).
         * 3 - If retries < maximum allowed:
         * - Reschedule the task
         * - Remove worker owner of the task
         * 4 - If retries >= maximum allowed:
         * - Mark task as failed
         * - Add extra information in the report.
         */

        return Report::createInfo(
            sprintf(
                'Reschedule tasks for: age <= %s, tasks names IN %s',
                $age->format(DATE_ATOM),
                implode(',', $whitelist)
            )
        );
    }

    private function getStuckTaskLogs(array $whitelist, DateTimeImmutable $age): CollectionInterface
    {
        //@TODO move this to a service:

        /** @var TaskLogInterface $taskLog */
        $taskLog = $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);

        $filter = (new TaskLogFilter())
            ->addFilter(TaskLogBrokerInterface::COLUMN_TASK_NAME, 'IN', $whitelist)
            ->addFilter(TaskLogBrokerInterface::COLUMN_STATUS, 'IN', [TaskLog::STATUS_ENQUEUED])
            ->addFilter(TaskLogBrokerInterface::COLUMN_CREATED_AT, '<=', $age->format(DATE_ATOM));

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
        $age = (int)$this->getOption('age');
        $date = new DateTimeImmutable();
        $date->modify(sprintf('-%s seconds', $age));

        return $date;
    }
}
