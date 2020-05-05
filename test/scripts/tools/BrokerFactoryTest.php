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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */

namespace oat\taoTaskQueue\test\scripts\tools;

use oat\generis\test\TestCase;
use oat\tao\model\taskQueue\Queue\Broker\InMemoryQueueBroker;
use oat\taoTaskQueue\model\QueueBroker\NewSqlQueueBroker;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;
use oat\taoTaskQueue\scripts\tools\BrokerFactory;

class BrokerFactoryTest extends TestCase
{
    /** @var BrokerFactory */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new BrokerFactory();
    }

    public function testCreateInMemory(): void
    {
        $test = $this->subject->create(BrokerFactory::BROKER_MEMORY);
        $this->assertInstanceOf(InMemoryQueueBroker::class, $test);
    }

    public function testCreateRDS(): void
    {
        $test = $this->subject->create(BrokerFactory::BROKER_RDS, 'default');
        $this->assertInstanceOf(RdsQueueBroker::class, $test);
    }

    public function testCreateNewSql(): void
    {
        $test = $this->subject->create(BrokerFactory::BROKER_NEW_SQL, 'default');
        $this->assertInstanceOf(NewSqlQueueBroker::class, $test);
    }

    public function testCreateSqs(): void
    {
        $test = $this->subject->create(BrokerFactory::BROKER_SQS, null, 1);
        $this->assertInstanceOf(NewSqlQueueBroker::class, $test);
    }
}
