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
 * Copyright (c) 2025 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */

declare(strict_types=1);

namespace oat\taoTaskQueue\scripts\tools;

use common_persistence_Driver;
use common_persistence_Manager;
use common_persistence_sql_Driver;
use Laminas\ServiceManager\ServiceLocatorAwareInterface;
use Laminas\ServiceManager\ServiceLocatorAwareTrait;
use DateTimeImmutable;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\tao\model\taskQueue\TaskLog\TaskLogFilter;
use oat\tao\model\taskQueue\TaskLog\Broker\TaskLogBrokerInterface;
use PDO;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;

class TaskQueueMaintenance extends ScriptAction implements ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    protected function provideDescription(): string
    {
        return 'Cron-oriented maintenance script for task queue.';
    }

    protected function provideUsage(): array
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Display this help message.'
        ];
    }

    protected function provideOptions(): array
    {
        return [
            'archive' => [
                'prefix' => 'a',
                'longPrefix' => 'archive',
                'flag' => true,
                'required' => false,
                'description' => 'Archive completed/failed tasks older than retention.'
            ],
            'delete' => [
                'prefix' => 'd',
                'longPrefix' => 'delete',
                'flag' => true,
                'required' => false,
                'description' => 'Delete archived tasks older than retention.'
            ],
            'vacuum' => [
                'prefix' => 'v',
                'longPrefix' => 'vacuum',
                'flag' => true,
                'required' => false,
                'description' => 'Run VACUUM FULL on tq_task_log.'
            ],
            'unblock' => [
                'prefix' => 'u',
                'longPrefix' => 'unblock',
                'flag' => true,
                'required' => false,
                'description' => 'Unblock stuck tasks older than retention.'
            ],
            'completedRetention' => [
                'prefix' => 'cr',
                'longPrefix' => 'completed-retention',
                'cast' => 'int',
                'required' => false,
                'defaultValue' => 30,
                'description' => 'Retention in days for completed/failed tasks before archiving.',
            ],
            'archivedRetention' => [
                'prefix' => 'ar',
                'longPrefix' => 'archived-retention',
                'cast' => 'int',
                'required' => false,
                'defaultValue' => 180,
                'description' => 'Retention in days for archived tasks before deletion.',
            ],
            'stuckRetention' => [
                'prefix' => 'sr',
                'longPrefix' => 'stuck-retention',
                'cast' => 'int',
                'required' => false,
                'defaultValue' => 2,
                'description' => 'Age in days for running/enqueued tasks to be considered stuck.',
            ],
        ];
    }

    /**
     * Entry point when called via:
     *   sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\TaskQueueMaintenance'
     */
    protected function run()
    {
        $report = Report::createInfo('[TaskQueueMaintenance] Maintenance run summary.');
        $hasAction = false;

        $completedRetention = (int) $this->getOption('completedRetention');
        $archivedRetention  = (int) $this->getOption('archivedRetention');
        $stuckRetention     = (int) $this->getOption('stuckRetention');

        if ($this->getOption('archive')) {
            $hasAction = true;
            $report->add($this->archiveCompletedAndFailed($completedRetention));
        }

        if ($this->getOption('delete')) {
            $hasAction = true;
            $report->add($this->deleteOldArchived($archivedRetention));
        }

        if ($this->getOption('unblock')) {
            $hasAction = true;
            $report->add($this->unblockStuckTasks($stuckRetention));
        }

        if ($this->getOption('vacuum')) {
            $hasAction = true;
            $report->add($this->runVacuum());
        }

        if (!$hasAction) {
            return Report::createInfo('No option provided. Use --help to see usage.');
        }

        return $report;
    }

    /**
     * Move Completed and Failed tasks older than N days to Archived status in the tq_task_log table.
     */
    private function archiveCompletedAndFailed(int $completedRetention): Report
    {
        /** @var TaskLogInterface $taskLog */
        $taskLog = $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);

        $cutoffDate = (new DateTimeImmutable())
            ->modify(sprintf('-%d days', $completedRetention));

        $cutoffDateString = $cutoffDate->format('Y-m-d H:i:s');

        $filter = new TaskLogFilter();

        $filter
            ->in(
                TaskLogBrokerInterface::COLUMN_STATUS,
                [
                    TaskLogInterface::STATUS_COMPLETED,
                    TaskLogInterface::STATUS_FAILED,
                ]
            )
            ->lt(
                TaskLogBrokerInterface::COLUMN_UPDATED_AT,
                $cutoffDateString
            );

        $collection = $taskLog->search($filter);

        if ($collection->isEmpty()) {
            return Report::createSuccess('[TaskQueueMaintenance] Archive: nothing to archive.');
        }

        $taskLog->archiveCollection($collection);

        return Report::createSuccess(sprintf(
            '[TaskQueueMaintenance] Archive flow finished. Affected tasks: %d',
            $collection->count()
        ));
    }

    /**
     * Delete archived tasks older than N days.
     */
    private function deleteOldArchived(int $archivedRetention): Report
    {
        /** @var TaskLogInterface $taskLog */
        $taskLog = $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);

        $cutoffDate = (new DateTimeImmutable())
            ->modify(sprintf('-%d days', $archivedRetention));

        $cutoffDateString = $cutoffDate->format('Y-m-d H:i:s');

        $filter = new TaskLogFilter();

        $filter
            ->eq(
                TaskLogBrokerInterface::COLUMN_STATUS,
                TaskLogInterface::STATUS_ARCHIVED
            )
            ->lt(
                TaskLogBrokerInterface::COLUMN_UPDATED_AT,
                $cutoffDateString
            );

        $collection = $taskLog->search($filter);

        if ($collection->isEmpty()) {
            return Report::createSuccess('[TaskQueueMaintenance] Delete: nothing to delete.');
        }

        $deleted = 0;

        $broker = $taskLog->getBroker();

        foreach ($collection as $entity) {
            /** @var \oat\tao\model\taskQueue\TaskLog\Entity\TaskLogEntityInterface|\ArrayAccess $entity */
            if ($broker->deleteById($entity->getId())) {
                $deleted++;
            }
        }

        return Report::createSuccess(sprintf(
            '[TaskQueueMaintenance] Delete archived flow finished. Tasks deleted: %d',
            $deleted
        ));
    }

    /**
     * Unblock stuck tasks in Running/Enqueued status older than $stuckRetention.
     */
    private function unblockStuckTasks(int $stuckRetention): Report
    {
        /** @var TaskLogInterface $taskLog */
        $taskLog = $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);

        /** @var QueueDispatcherInterface $dispatcher */
        $dispatcher = $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);

        /** @var \common_persistence_SqlPersistence $persistence */
        $persistence = $this->getServiceLocator()
            ->get(common_persistence_Manager::SERVICE_ID)
            ->getPersistenceById('default');

        $cutoff = (new DateTimeImmutable())
            ->modify(sprintf('-%d days', $stuckRetention))
            ->format('Y-m-d H:i:s');

        $filter = (new TaskLogFilter())
            ->in(
                TaskLogBrokerInterface::COLUMN_STATUS,
                [
                    TaskLogInterface::STATUS_RUNNING,
                    TaskLogInterface::STATUS_ENQUEUED,
                ]
            )
            ->lte(
                TaskLogBrokerInterface::COLUMN_UPDATED_AT,
                $cutoff
            );

        $collection = $taskLog->search($filter);

        $stats = [
            'stuckFound'     => $collection->count(),
            'unblocked'      => 0,
            'alreadyVisible' => 0,
            'orphan'         => 0,
        ];

        if ($collection->isEmpty()) {
            return Report::createSuccess('[TaskQueueMaintenance] Unblock: nothing to unblock.');
        }

        /** @var \oat\tao\model\taskQueue\Queue[] $queues */
        $queues = $dispatcher->getQueues();

        foreach ($collection as $taskLogEntity) {
            $taskLogId = $taskLogEntity->getId();
            $foundInQueue = false;

            foreach ($queues as $queue) {
                $broker = $queue->getBroker();

                /** @var RdsQueueBroker $broker */
                $decorator = $broker->getTaskByTaskLogId($taskLogId);
                if ($decorator === null) {
                    continue;
                }

                $foundInQueue = true;

                $queueRowId = (int) $decorator->getTaskId();
                $tableName = 'tq_' . $queue->getName();

                $row = $persistence->query(
                    'SELECT visible FROM ' . $tableName . ' WHERE id = ?',
                    [$queueRowId]
                )->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    continue;
                }

                if ((bool) $row['visible'] === false) {
                    $broker->changeTaskVisibility((string) $queueRowId, true);
                    $stats['unblocked']++;
                } else {
                    $stats['alreadyVisible']++;
                }

                break;
            }

            if (!$foundInQueue) {
                $stats['orphan']++;
            }
        }

        return Report::createSuccess(sprintf(
            '[TaskQueueMaintenance] Unblock finished. found=%d already_visible=%d unblocked=%d orphan=%d',
            $stats['stuckFound'],
            $stats['alreadyVisible'],
            $stats['unblocked'],
            $stats['orphan']
        ));
    }

    /**
     * Run VACUUM FULL on the tq_task_log table.
     */
    private function runVacuum(): Report
    {
        /** @var common_persistence_Manager $pm */
        $pm = $this->getServiceLocator()->get(common_persistence_Manager::SERVICE_ID);
        $persistence = $pm->getPersistenceById('default');

        /** @var common_persistence_Driver $driver */
        $driver = $persistence->getDriver();

        if (!($driver instanceof common_persistence_sql_Driver)) {
            return Report::createError('VACUUM FULL is only supported on PostgreSQL (pdo_pgsql)');
        }

        $conn = $driver->getDbalConnection()->getParams();
        $driverType = $conn['driver'] ?? null;

        if ($driverType !== 'pdo_pgsql') {
            return Report::createError('VACUUM FULL is only supported on PostgreSQL (pdo_pgsql)');
        }

        $persistence->exec('VACUUM FULL tq_task_log;');

        return Report::createSuccess('[TaskQueueMaintenance] Vacuum flow finished (VACUUM FULL tq_task_log).');
    }
}
