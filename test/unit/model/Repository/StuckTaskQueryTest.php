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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoTaskQueue\test\unit\model\Repository;

use InvalidArgumentException;
use oat\generis\test\TestCase;
use oat\tao\model\taskQueue\TaskLog;
use oat\taoTaskQueue\model\Repository\StuckTaskQuery;

class StuckTaskQueryTest extends TestCase
{
    public function testGetters(): void
    {
        $whiteList = [
            'task1',
            'task2',
        ];
        $statuses = [
            TaskLog::STATUS_ENQUEUED,
        ];

        $query = new StuckTaskQuery(
            'query',
            $whiteList,
            $statuses,
            300
        );

        $this->assertSame(300, $query->getAge());
        $this->assertSame($statuses, $query->getStatuses());
        $this->assertSame($whiteList, $query->getWhitelist());
        $this->assertSame('query', $query->getQueryName());
    }

    public function testEmptyListThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stuck tasks names white list cannot be empty');

        new StuckTaskQuery(
            'query',
            [],
            [
                TaskLog::STATUS_ENQUEUED,
            ],
            300
        );
    }

    public function testUnsupportedStatusThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only allowed statuses');

        new StuckTaskQuery(
            'query',
            [
                'task',
            ],
            [
                TaskLog::STATUS_FAILED,
            ],
            300
        );
    }
}
