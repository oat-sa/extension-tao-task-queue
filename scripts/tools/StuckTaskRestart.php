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

use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\taoTaskQueue\model\Repository\StuckTasksQuery;
use oat\taoTaskQueue\model\Repository\StuckTasksRepository;
use oat\taoTaskQueue\model\Service\RestartStuckTaskService;
use Throwable;

class StuckTaskRestart extends ScriptAction
{
    protected function provideOptions(): array
    {
        return [
            'whitelist' => [
                'prefix' => 'w',
                'longPrefix' => 'whitelist',
                'cast' => 'string',
                'required' => true,
                'description' => 'The whitelist of task_name that can be restarted.'
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
        $query = new StuckTasksQuery(
            $this->getOption('queue'),
            explode(',', (string)$this->getOption('whitelist')),
            $this->getOption('age')
        );

        $stuckTasks = $this->getStuckTasksRepository()->findAll($query);

        $report = Report::createSuccess(
            sprintf(
                'Rescheduling tasks for: age <= %s, tasks names IN %s',
                $query->getAgeDateTime()->format(DATE_ATOM),
                implode(',', $query->getWhitelist())
            )
        );

        $restartService = $this->getRestartStuckTaskService();
        $totalRestarted = 0;
        $totalErrors = 0;

        foreach ($stuckTasks as $stuckTask) {
            if ($stuckTask->isOrphan()) {
                $totalErrors++;

                $errorMessage = sprintf(
                    'TaskLog %s for queue %s is orphan',
                    $stuckTask->getTaskLog()->getId(),
                    $stuckTask->getQueueName()
                );

                $report->add(Report::createError($errorMessage));

                $this->logWarning($errorMessage);

                continue;
            }

            try {
                $restartService->restart($stuckTask);

                $successMessage = sprintf(
                    'Rescheduling taskLog id %s, label %s, taskName %s,',
                    $stuckTask->getTaskLog()->getId(),
                    $stuckTask->getTaskLog()->getLabel(),
                    $stuckTask->getTaskLog()->getTaskName()
                );

                $report->add(Report::createSuccess($successMessage));

                $this->logInfo($successMessage);

                $totalRestarted++;
            } catch (Throwable $exception) {
                $totalErrors++;

                $errorMessage = sprintf(
                    'Error rescheduling taskLog id %s, label %s, taskName %s. Error: %s',
                    $stuckTask->getTaskLog()->getId(),
                    $stuckTask->getTaskLog()->getLabel(),
                    $stuckTask->getTaskLog()->getTaskName(),
                    $exception->getMessage()
                );

                $report->add(Report::createError($errorMessage));

                $this->logError($errorMessage);
            }
        }

        $report->add(
            Report::createInfo(
                sprintf(
                    'Total restarted: %s, Total errors: %s',
                    $totalRestarted,
                    $totalErrors
                )
            )
        );

        return $report;
    }

    private function getStuckTasksRepository(): StuckTasksRepository
    {
        return $this->getServiceLocator()->get(StuckTasksRepository::class);
    }

    private function getRestartStuckTaskService(): RestartStuckTaskService
    {
        return $this->getServiceLocator()->get(RestartStuckTaskService::class);
    }
}
