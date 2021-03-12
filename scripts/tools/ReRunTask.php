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

declare(strict_types = 1);

namespace oat\taoTaskQueue\scripts\tools;

use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\TaskLog\Broker\TaskLogBrokerInterface;
use oat\tao\model\taskQueue\TaskLog\TaskLogFilter;
use oat\tao\model\taskQueue\TaskLogInterface;

/**
 * This tool may be useful to restart failed or just start again any tasks, after they was once added to queue.
 * It grabs initial parameters of the original tasks and adds it to the queue.
 *
 * Usage:
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\ReRunTask' -t <taskId>
 */
final class ReRunTask extends ScriptAction
{
    public const OPTION_TASK = 'task';

    public function run(): Report
    {
        /** @var TaskLogInterface $taskLog */
        $taskLog = $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);

        $filter = (new TaskLogFilter())
            ->eq(TaskLogBrokerInterface::COLUMN_ID, $this->getOption(self::OPTION_TASK));

        $collection = $taskLog->search($filter);

        if ($collection->isEmpty()) {
            return Report::createError('Task with corresponding id not found.');
        }

        $entity = $collection->first();

        $callback = $entity->getTaskName();

        /** @var QueueDispatcherInterface $queueService */
        $queueService = $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);
        $created = $queueService->createTask(new $callback, $entity->getParameters(), $entity->getLabel());

        return Report::createSuccess(
            sprintf('Original task `%s` was added to the queue under new id %s', $entity->getId(), $created->getId())
        );
    }

    protected function provideOptions(): array
    {
        return [
            self::OPTION_TASK => [
                'prefix'      => substr(self::OPTION_TASK, 0, 1),
                'longPrefix'  => self::OPTION_TASK,
                'cast'        => 'string',
                'required'    => true,
                'description' => 'ID of the task, which should be run again'
            ]
        ];
    }

    protected function provideDescription(): string
    {
        return 'Uses for restarting tasks from the task queue';
    }
}
