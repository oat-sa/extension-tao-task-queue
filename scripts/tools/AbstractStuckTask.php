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
use oat\tao\model\taskQueue\TaskLog;
use oat\taoTaskQueue\model\Repository\StuckTaskCollection;
use oat\taoTaskQueue\model\Repository\StuckTaskQuery;
use oat\taoTaskQueue\model\Repository\StuckTaskRepository;

abstract class AbstractStuckTask extends ScriptAction
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
            'statuses' => [
                'prefix' => 's',
                'longPrefix' => 'statuses',
                'cast' => 'string',
                'required' => false,
                'description' => 'The task log statuses to consider stuck',
                'defaultValue' => TaskLog::STATUS_ENQUEUED
            ],
            'age' => [
                'prefix' => 'w',
                'longPrefix' => 'age',
                'cast' => 'int',
                'required' => false,
                'defaultValue' => StuckTaskRepository::MIN_AGE,
                'description' => 'Age in seconds of a task log.'
            ]
        ];
    }

    protected function getQuery(): StuckTaskQuery
    {
        return new StuckTaskQuery(
            $this->getOption('queue'),
            explode(',', (string)$this->getOption('whitelist')),
            explode(',', (string)$this->getOption('statuses')),
            $this->getOption('age')
        );
    }

    protected function findStuckTasks(): StuckTaskCollection
    {
        return $this->getStuckTasksRepository()->findAll($this->getQuery());
    }

    private function getStuckTasksRepository(): StuckTaskRepository
    {
        return $this->getServiceLocator()->get(StuckTaskRepository::class);
    }
}
