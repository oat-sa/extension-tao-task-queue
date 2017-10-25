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
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */

namespace oat\taoTaskQueue\test\model\ValueObjects;


use oat\taoTaskQueue\model\ValueObjects\TaskLogCategorizedStatus;

class TaskLogCategorizedStatusTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateWithValidStatus()
    {
        $status = TaskLogCategorizedStatus::create('enqueued');
        $this->assertInstanceOf(TaskLogCategorizedStatus::class, $status);
    }

    /**
     * @expectedException \Exception
     */
    public function testCreateWithInvalidStatus()
    {
        TaskLogCategorizedStatus::create('some invalid status');
    }

    public function testStatusAreMappedCorrectly()
    {
        $status = TaskLogCategorizedStatus::create('enqueued');
        $this->assertSame('in_progress', (string)$status);

        $status = TaskLogCategorizedStatus::create('dequeued');
        $this->assertSame('in_progress', (string)$status);

        $status = TaskLogCategorizedStatus::create('running');
        $this->assertSame('in_progress', (string)$status);

        $status = TaskLogCategorizedStatus::create('completed');
        $this->assertSame('completed', (string)$status);

        $status = TaskLogCategorizedStatus::create('failed');
        $this->assertSame('failed', (string)$status);

        $status = TaskLogCategorizedStatus::create('unknown');
        $this->assertSame('failed', (string)$status);
    }

    public function testStatusEquals()
    {
        $statusRunning = TaskLogCategorizedStatus::create('enqueued');
        $this->assertTrue($statusRunning->equals(TaskLogCategorizedStatus::create('dequeued')));

        $statusCompleted = TaskLogCategorizedStatus::create('completed');
        $this->assertTrue($statusCompleted->equals(TaskLogCategorizedStatus::create('completed')));

        $statusFailed = TaskLogCategorizedStatus::create('failed');
        $this->assertTrue($statusFailed->equals(TaskLogCategorizedStatus::create('unknown')));

        $this->assertFalse($statusRunning->equals($statusCompleted));
        $this->assertFalse($statusCompleted->equals($statusFailed));
    }
}
