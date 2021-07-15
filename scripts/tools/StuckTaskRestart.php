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

use oat\oatbox\reporting\Report;
use oat\taoTaskQueue\model\Service\RestartStuckTaskService;
use Throwable;

class StuckTaskRestart extends AbstractStuckTask
{
    protected function provideDescription(): string
    {
        return 'Reschedule stuck tasks in the queue';
    }

    protected function run(): Report
    {
        $query = $this->getQuery();
        $stuckTasks = $this->findStuckTasks();

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
            try {
                $restartService->restart($stuckTask);

                $successMessage = sprintf(
                    'Restarting: TaskLogId = %s, Label = %s, TaskName = %s',
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
                    'Error restarting: TaskLogId %s, Label %s, TaskName = %s, Error: %s',
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
                    'Total: %s, Restarted: %s, Errors: %s',
                    $stuckTasks->count(),
                    $totalRestarted,
                    $totalErrors
                )
            )
        );

        return $report;
    }

    private function getRestartStuckTaskService(): RestartStuckTaskService
    {
        return $this->getServiceLocator()->get(RestartStuckTaskService::class);
    }
}
