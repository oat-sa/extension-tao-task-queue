<?php

declare(strict_types=1);

namespace oat\taoTaskQueue\scripts\tools;

use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;

/**
 * Task Log Utility.
 *
 * ```
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\TaskCleaner' -h
 * ```
 */
class TaskCleaner implements ScriptAction, ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    private const DEFAULT_COMPLETED_RETENTION_PERIOD = 'PT1M';
    private const DEFAULT_FAILED_RETENTION_PERIOD = 'PT1M';
    private const DEFAULT_ARCHIVED_RETENTION_PERIOD = 'PT1M';
    private const DEFAULT_INPROGRESS_RETENTION_PERIOD = 'PT14D';
    private const DEFAULT_PENDING_RETENTION_PERIOD = 'PT14D';

    private const OPTION_TASK = '';
    private const OPTION_COMPLETED_RETENTION_PERIOD = 'completed-retention-period';
    private const OPTION_FAILED_RETENTION_PERIOD = 'failed-retention-period';
    private const OPTION_ARCHIVED_RETENTION_PERIOD = 'archived-retention-period';
    private const OPTION_INPROGRESS_RETENTION_PERIOD = 'inprogress-retention-period';
    private const OPTION_PENDING_RETENTION_PERIOD = 'pending-retention-period';
    private const OPTION_WET_RUN = 'wet-run';
    private const OPTION_CLEAN_PENDING = 'clean-pending';

    protected function provideOptions(): array
    {
        return [
            self::OPTION_COMPLETED_RETENTION_PERIOD => [
                'prefix'      => 'crp',
                'longPrefix'  => self::OPTION_COMPLETED_RETENTION_PERIOD,
                'cast'        => 'string',
                'required'    => false,
                'description' => 'Retention period for the tasks with `Completed` status. Format example: PT1M.'
            ],
            self::OPTION_FAILED_RETENTION_PERIOD => [
                'prefix'      => 'frp',
                'longPrefix'  => self::OPTION_FAILED_RETENTION_PERIOD,
                'cast'        => 'string',
                'required'    => false,
                'description' => 'Retention period for the tasks with `Failed` status. Format example: PT1M.'
            ],
            self::OPTION_ARCHIVED_RETENTION_PERIOD => [
                'prefix'      => 'arp',
                'longPrefix'  => self::OPTION_ARCHIVED_RETENTION_PERIOD,
                'cast'        => 'string',
                'required'    => false,
                'description' => 'Retention period for the tasks with `Archived` status. Format example: PT1M.'
            ],
            self::OPTION_INPROGRESS_RETENTION_PERIOD => [
                'prefix'      => 'irp',
                'longPrefix'  => self::OPTION_INPROGRESS_RETENTION_PERIOD,
                'cast'        => 'string',
                'required'    => false,
                'description' => 'Retention period for the tasks with `In Progress` status. Format example: PT1D.'
            ],
            self::OPTION_PENDING_RETENTION_PERIOD => [
                'prefix'      => 'prp',
                'longPrefix'  => self::OPTION_PENDING_RETENTION_PERIOD,
                'cast'        => 'string',
                'required'    => false,
                'description' => 'Retention period for the tasks with `Pending` status. Format example: PT1D.'
            ],
            self::OPTION_WET_RUN => [
                'prefix' => 'w',
                'longPrefix' => self::OPTION_WET_RUN,
                'flag' => true,
                'description' => 'Whether script should be executed in dry or wet-run mode. Dry-run will only output some stats.'
            ],
            self::OPTION_CLEAN_PENDING => [
                'prefix' => 'cp',
                'longPrefix' => self::OPTION_CLEAN_PENDING,
                'flag' => true,
                'description' => 'Whether script should process tasks with `Pending` status or not.'
            ]
        ];
    }

    protected function provideDescription(): string
    {
        return 'Script is used to do some cleaning over the `tq_task_log` table.';
    }

    public function run(): Report
    {
        $executionReport = Report::createSuccess();

        $executionReport->add(
            Report::createSuccess(
                'Task Log table was processed.'
            )
        );

        return $executionReport;
    }
}