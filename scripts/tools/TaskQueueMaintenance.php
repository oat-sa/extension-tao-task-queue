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
use oat\oatbox\action\Action;
use oat\oatbox\reporting\Report;
use oat\tao\model\taskQueue\TaskLogInterface;
use oat\tao\model\taskQueue\TaskLog\TaskLogFilter;
use oat\tao\model\taskQueue\TaskLog\Broker\TaskLogBrokerInterface;
use Throwable;

class TaskQueueMaintenance implements Action, ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    private int $completedRetentionDays = 30;   // Completed/Failed -> Archived
    private int $archivedRetentionDays  = 180;  // Archived -> Deleted
    private int $stuckRetentionDays     = 14;   // In Progress / Enqueued -> “stuck”

    /**
     * Flags set from CLI arguments.
     */
    private bool $doArchive = false;
    private bool $doUnblock = false;
    private bool $doVacuum  = false;
    private bool $showHelp  = false;

    /**
     * Entry point when called via:
     *   sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\TaskQueueMaintenance'
     *
     * @param array $params CLI parameters passed by TAO
     */
    public function __invoke($params): Report
    {
        try {
            $this->parseParams((array) $params);

            $messages = [];

            if ($this->doArchive) {
                $count = $this->runArchive();
                $messages[] = sprintf(
                    '[TaskQueueMaintenance] Archive flow finished. Affected tasks: %d',
                    $count
                );
            }

            if ($this->doUnblock) {
                // TODO: implement unblock flow
                $messages[] = '[TaskQueueMaintenance] Unblock flow not implemented yet.';
            }


            if ($this->doVacuum) {
                $this->runVacuum();
                $messages[] = '[TaskQueueMaintenance] Vacuum flow finished (VACUUM FULL tq_task_log).';
            }

            // If no specific action requested, or --help given
            if ($this->showHelp || (!$this->doArchive && !$this->doUnblock && !$this->doVacuum)) {
                $messages[] = $this->printHelp();
            }

            return Report::createSuccess(implode(PHP_EOL . PHP_EOL, $messages));
        } catch (Throwable $e) {
            return Report::createError('[TaskQueueMaintenance] ' . $e->getMessage());
        }
    }

    /**
     * Argument parsing:
     *  --archive
     *  --unblock
     *  --vacuum
     *  --help
     */
    private function parseParams(array $params): void
    {
        foreach ($params as $param) {
            switch ($param) {
                case '--archive':
                    $this->doArchive = true;
                    break;

                case '--unblock':
                    $this->doUnblock = true;
                    break;

                case '--vacuum':
                    $this->doVacuum = true;
                    break;

                case '--help':
                    $this->showHelp = true;
                    break;
            }
        }
    }

    /**
     * Main entry point for the --archive option.
     * Returns number of affected tasks.
     */
    private function runArchive(): int
    {
        $archivedCount = $this->archiveCompletedAndFailed();
        $deletedCount = $this->deleteOldArchived();

        return $archivedCount + $deletedCount;
    }

    /**
     * Move Completed and Failed tasks older than N days to Archived status in the tq_task_log table.
     */
    private function archiveCompletedAndFailed(): int
    {
        /** @var TaskLogInterface $taskLog */
        $taskLog = $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);

        $cutoffDate = (new \DateTimeImmutable())
            ->modify(sprintf('-%d days', $this->completedRetentionDays));

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
    private function deleteOldArchived(): int
    {
        /** @var TaskLogInterface $taskLog */
        $taskLog = $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);

        $cutoffDate = (new \DateTimeImmutable())
            ->modify(sprintf('-%d days', $this->archivedRetentionDays));

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
     * Run VACUUM FULL on the tq_task_log table.
     */
    private function runVacuum(): void
    {
        try {
            /** @var common_persistence_Manager $pm */
            $pm = $this->getServiceLocator()->get(common_persistence_Manager::SERVICE_ID);

            $persistence = $pm->getPersistenceById('default');

            $persistence->exec('VACUUM FULL tq_task_log;');
        } catch (\Throwable $e) {
            throw new \RuntimeException('VACUUM failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Help text printed when no options or --help are used.
     */
    private function printHelp(): string
    {
        return <<<TXT
TaskQueueMaintenance

Cron-oriented maintenance for task queue logs.

Usage examples:
 1) Show help
    php index.php 'oat\\taoTaskQueue\\scripts\\tools\\TaskQueueMaintenance' --help

 2) Archive old tasks (completed/failed -> archived, old archived -> deleted)
    php index.php 'oat\\taoTaskQueue\\scripts\\tools\\TaskQueueMaintenance' --archive

 3) Unblock stuck tasks (running/enqueued for too long)
    php index.php 'oat\\taoTaskQueue\\scripts\\tools\\TaskQueueMaintenance' --unblock

 4) Vacuum task log table
    php index.php 'oat\\taoTaskQueue\\scripts\\tools\\TaskQueueMaintenance' --vacuum

Current internal retention settings (can be changed in this script later):
  completedRetentionDays = {$this->completedRetentionDays}
  archivedRetentionDays  = {$this->archivedRetentionDays}
  stuckRetentionDays     = {$this->stuckRetentionDays}
TXT;
    }
}
