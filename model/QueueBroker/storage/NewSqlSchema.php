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

declare(strict_types=1);

namespace oat\taoTaskQueue\model\QueueBroker\storage;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use oat\oatbox\service\ConfigurableService;

class NewSqlSchema extends ConfigurableService
{
    private const ID = 'id';
    private const MESSAGE = 'message';
    private const VISIBLE = 'visible';
    private const CREATED_AT = 'created_at';

    /** @var string */
    private $queueName;

    public function getSchema(Schema $schema, string $tableName): Schema
    {
        /** @var Table */
        $revisionTable = $schema->createtable($tableName);
        $this->createTable($revisionTable);

        return $schema;
    }

    private function createTable(Table $table): void
    {
        $table->addColumn(self::ID, 'string', ['length' => 36]);
        $table->addColumn(self::MESSAGE, 'text', ["notnull" => true]);
        $table->addColumn(self::VISIBLE, 'boolean', []);
        $table->addColumn(self::CREATED_AT, 'datetime', ['notnull' => true]);
        $table->setPrimaryKey([self::ID]);
        $table->addIndex([self::CREATED_AT, self::VISIBLE], 'IDX_created_at_visible_' . $this->queueName);
    }

    public function setQueueName(string $queueName): self
    {
        $this->queueName = $queueName;

        return $this;
    }
}
