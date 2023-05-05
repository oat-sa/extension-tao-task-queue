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

namespace oat\taoTaskQueue\scripts\tools;

use common_cache_Cache;
use InvalidArgumentException;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\taskQueue\Queue\Broker\InMemoryQueueBroker;
use oat\tao\model\taskQueue\Queue\Broker\QueueBrokerInterface;
use oat\taoTaskQueue\model\QueueBroker\NewSqlQueueBroker;
use oat\taoTaskQueue\model\QueueBroker\RdsQueueBroker;
use oat\taoTaskQueue\model\QueueBroker\SqsQueueBroker;

class BrokerFactory extends ConfigurableService
{
    public const BROKER_MEMORY = InMemoryQueueBroker::ID;
    public const BROKER_RDS = RdsQueueBroker::ID;
    public const BROKER_NEW_SQL = NewSqlQueueBroker::ID;
    public const BROKER_SQS = SqsQueueBroker::ID;

    public function create(string $brokerId, string $persistenceId = null, int $capacity = 1): QueueBrokerInterface
    {
        $this->validateBrokersWithPersistence($brokerId, $persistenceId);

        switch ($brokerId) {
            case self::BROKER_MEMORY:
                return new InMemoryQueueBroker();
            case self::BROKER_RDS:
                return new RdsQueueBroker($persistenceId, $capacity);
            case self::BROKER_NEW_SQL:
                return new NewSqlQueueBroker($persistenceId, $capacity);
            case self::BROKER_SQS:
                return new SqsQueueBroker(common_cache_Cache::SERVICE_ID, $capacity);
        }

        throw new InvalidArgumentException(sprintf('Broker %s is not supported', $brokerId));
    }

    private function validateBrokersWithPersistence(string $brokerId, string $persistenceId = null): void
    {
        if (
            in_array($brokerId, [self::BROKER_RDS, self::BROKER_NEW_SQL], true)
            && empty($persistenceId)
        ) {
            throw new InvalidArgumentException('Persistence id (--persistence=...) needs to be set for SQL.');
        }
    }
}
