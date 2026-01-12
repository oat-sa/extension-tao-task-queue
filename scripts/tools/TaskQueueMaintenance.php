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

use common_persistence_Manager;
use Laminas\ServiceManager\ServiceLocatorAwareInterface;
use Laminas\ServiceManager\ServiceLocatorAwareTrait;
use DateTimeImmutable;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\tao\model\taskQueue\TaskLog\TaskLogFilter;
use oat\tao\model\taskQueue\TaskLog\Broker\TaskLogBrokerInterface;
use PDO;
use RuntimeException;
use Throwable;
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
                'prefix' => 'c',
                'longPrefix' => 'completed-retention',
                'cast' => 'int',
                'required' => false,
                'defaultValue' => 30,
                'description' => 'Retention in days for completed/failed tasks before archiving.',
            ],
            'archivedRetention' => [
                'prefix' => 'r',
                'longPrefix' => 'archived-retention',
                'cast' => 'int',
                'required' => false,
                'defaultValue' => 180,
                'description' => 'Retention in days for archived tasks before deletion.',
            ],
            'stuckRetention' => [
                'prefix' => 's',
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
        $messages = [];

        $completedRetention = (int) $this->getOption('completedRetention');
        $archivedRetention  = (int) $this->getOption('archivedRetention');
        $stuckRetention     = (int) $this->getOption('stuckRetention');

        if ($this->hasOption('archive')) {
            $count = $this->archiveCompletedAndFailed($completedRetention);
            $messages[] = sprintf(
                '[TaskQueueMaintenance] Archive flow finished. Affected tasks: %d',
                $count
            );
        }

        if ($this->hasOption('delete')) {
            $deleted = $this->deleteOldArchived($archivedRetention);
            $messages[] = sprintf(
                '[TaskQueueMaintenance] Delete archived flow finished. Tasks deleted: %d',
                $deleted
            );
        }

        if ($this->hasOption('unblock')) {
            $stats = $this->unblockStuckTasks($stuckRetention);
            $messages[] = sprintf(
                '[TaskQueueMaintenance] Unblock finished. found=%d already_visible=%d unblocked=%d orphan=%d',
                $stats['stuckFound'],
                $stats['alreadyVisible'],
                $stats['unblocked'],
                $stats['orphan']
            );
        }

        if ($this->hasOption('vacuum')) {
            $this->runVacuum();
            $messages[] = '[TaskQueueMaintenance] Vacuum flow finished (VACUUM FULL tq_task_log).';
        }

        if (empty($messages)) {
            return Report::createInfo('No option provided. Use --help to see usage.');
        }

        return Report::createSuccess(implode(PHP_EOL . PHP_EOL, $messages));
    }

    /**
     * Move Completed and Failed tasks older than N days to Archived status in the tq_task_log table.
     */
    private function archiveCompletedAndFailed(int $completedRetention): int
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
            return 0;
        }

        $taskLog->archiveCollection($collection);

        return $collection->count();
    }

    /**
     * Delete archived tasks older than N days.
     */
    private function deleteOldArchived(int $archivedRetention): int
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
            return 0;
        }

        $deleted = 0;

        $broker = $taskLog->getBroker();

        foreach ($collection as $entity) {
            /** @var \oat\tao\model\taskQueue\TaskLog\Entity\TaskLogEntityInterface|\ArrayAccess $entity */
            if ($broker->deleteById($entity->getId())) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Unblock stuck tasks in Running/Enqueued status older than $stuckRetention.
     */
    private function unblockStuckTasks(int $stuckRetention): array
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
            return $stats;
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

        return $stats;
    }

    /**
     * Run VACUUM FULL on the tq_task_log table.
     */
    private function runVacuum(): void
    {
        try {
            /** @var common_persistence_Manager $pm */
            $pm = $this->getServiceLocator()->get(common_persistence_Manager::SERVICE_ID);
            $persistence = $pm->getPersistenceById('default');

            $conn = $persistence->getDriver()->getDbalConnection()->getParams();
            $driver = $conn['driver'] ?? null;

            if ($driver !== 'pdo_pgsql') {
                throw new RuntimeException(
                    sprintf(
                        'VACUUM FULL is only supported on PostgreSQL (pdo_pgsql). Current driver: %s',
                        $driver ?? 'unknown'
                    )
                );
            }

            $persistence->exec('VACUUM FULL tq_task_log;');
        } catch (Throwable $e) {
            throw new RuntimeException('VACUUM failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
