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

class StuckTaskSummary extends AbstractStuckTask
{
    protected function provideDescription(): string
    {
        return 'Summarize stuck tasks in the queue';
    }

    protected function run(): Report
    {
        $query = $this->getQuery();
        $stuckTasks = $this->findStuckTasks();

        $report = Report::createSuccess(
            sprintf(
                'Total = %s, Age <= %s, TasksName = %s',
                $stuckTasks->count(),
                $query->getAgeDateTime()->format(DATE_ATOM),
                implode(',', $query->getWhitelist())
            )
        );

        foreach ($stuckTasks as $stuckTask) {
            $task = $stuckTask->getTask();
            $taskLog = $stuckTask->getTaskLog();

            $report->add(
                Report::createInfo(
                    sprintf(
                        '[%s][%s/%s]: Label = %s, TaskName = %s, IsOrphan = %s, TaskId = %s, TaskCreatedAt = %s',
                        $taskLog->getId(),
                        $taskLog->getCreatedAt()->format(DATE_ATOM),
                        $taskLog->getUpdatedAt()->format(DATE_ATOM),
                        $taskLog->getLabel(),
                        $taskLog->getTaskName(),
                        $stuckTask->isOrphan() ? 'Yes' : 'No',
                        $task ? $task->getId() : 'None',
                        $task ? $task->getCreatedAt()->format(DATE_ATOM) : 'None'
                    )
                )
            );
        }

        return $report;
    }
}
