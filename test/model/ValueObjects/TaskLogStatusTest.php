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


use oat\taoTaskQueue\model\ValueObjects\TaskLogStatus;

class TaskLogStatusTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateWithValidStatus()
    {
        $status = TaskLogStatus::create('enqueued');
        $this->assertInstanceOf(TaskLogStatus::class, $status);
    }

    /**
     * @expectedException \Exception
     */
    public function testCreateWithInvalidStatus()
    {
        TaskLogStatus::create('some invalid status');
    }

    public function testStatusAreMappedCorrectly()
    {
        $status = TaskLogStatus::create('enqueued');
        $this->assertSame('running', (string)$status);

        $status = TaskLogStatus::create('dequeued');
        $this->assertSame('running', (string)$status);

        $status = TaskLogStatus::create('running');
        $this->assertSame('running', (string)$status);

        $status = TaskLogStatus::create('completed');
        $this->assertSame('completed', (string)$status);

        $status = TaskLogStatus::create('failed');
        $this->assertSame('failed', (string)$status);

        $status = TaskLogStatus::create('unknown');
        $this->assertSame('failed', (string)$status);
    }

    public function testStatusEquals()
    {
        $statusRunning = TaskLogStatus::create('enqueued');
        $this->assertTrue($statusRunning->equals(TaskLogStatus::create('dequeued')));

        $statusCompleted = TaskLogStatus::create('completed');
        $this->assertTrue($statusCompleted->equals(TaskLogStatus::create('completed')));

        $statusFailed = TaskLogStatus::create('failed');
        $this->assertTrue($statusFailed->equals(TaskLogStatus::create('unknown')));

        $this->assertFalse($statusRunning->equals($statusCompleted));
        $this->assertFalse($statusCompleted->equals($statusFailed));
    }
}
