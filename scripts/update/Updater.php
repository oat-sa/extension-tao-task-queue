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
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA;
 *
 *
 */

namespace oat\taoTaskQueue\scripts\update;

use common_ext_ExtensionUpdater;
use oat\oatbox\service\ConfigurableService;
use oat\taoTaskQueue\model\Queue;
use oat\taoTaskQueue\model\QueueBroker\InMemoryQueueBroker;
use oat\taoTaskQueue\model\QueueDispatcher;
use oat\taoTaskQueue\model\QueueDispatcherInterface;
use oat\taoTaskQueue\model\TaskSelector\WeightStrategy;
use oat\taoTaskQueue\model\TaskLogBroker\TaskLogBrokerInterface;
use oat\taoTaskQueue\model\TaskLogInterface;
use oat\tao\model\ClientLibConfigRegistry;

/**
 * Class Updater
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class Updater extends common_ext_ExtensionUpdater
{
    public function update($initialVersion)
    {
        $this->skip('0.1.0', '0.1.2');

        if ($this->isVersion('0.1.2')) {

            $queueService = new QueueDispatcher([
                QueueDispatcherInterface::OPTION_QUEUES       => [
                    new Queue('queue', new InMemoryQueueBroker())
                ],
                QueueDispatcherInterface::OPTION_TASK_TO_QUEUE_ASSOCIATIONS => [],
                QueueDispatcherInterface::OPTION_TASK_LOG     => TaskLogInterface::SERVICE_ID
            ]);

            $this->getServiceManager()->propagate($queueService);

            $this->getServiceManager()->register(QueueDispatcherInterface::SERVICE_ID, $queueService);

            $this->setVersion('0.2.0');
        }

        $this->skip('0.2.0', '0.4.2');

        if ($this->isVersion('0.4.2')) {

            /** @var $taskLogService TaskLogInterface */
            $taskLogService = $this->getServiceManager()->get(TaskLogInterface::SERVICE_ID);

            if ($taskLogService->isRds()) {
                /** @var \common_persistence_SqlPersistence $persistence */
                $persistence = \common_persistence_Manager::getPersistence('default');
                $schemaManager = $persistence->getSchemaManager();
                $fromSchema = $schemaManager->createSchema();
                $toSchema = clone $fromSchema;

                $table = $toSchema->getTable($taskLogService->getBroker()->getTableName());
                if (!$table->hasColumn(TaskLogBrokerInterface::COLUMN_PARAMETERS)) {
                    $table->addColumn(TaskLogBrokerInterface::COLUMN_PARAMETERS, 'text', ["notnull" => false, "default" => null]);
                }

                $queries = $persistence->getPlatform()->getMigrateSchemaSql($fromSchema, $toSchema);
                foreach ($queries as $query) {
                    $persistence->exec($query);
                }
            }

            $this->setVersion('0.5.0');
        }

        $this->skip('0.5.0', '0.8.1');

        if ($this->isVersion('0.8.1')) {
            /** @var QueueDispatcherInterface|ConfigurableService $queueService */
            $queueService = $this->getServiceManager()->get(QueueDispatcherInterface::SERVICE_ID);

            $queueService->setTaskSelector(new WeightStrategy());

            $this->getServiceManager()->register(QueueDispatcherInterface::SERVICE_ID, $queueService);

            $this->setVersion('0.9.0');
        }

        if ($this->isVersion('0.9.0')) {
            //Add an extra controller the backoffice 'controller/main'
            ClientLibConfigRegistry::getRegistry()->register(
                'controller/main', [
                    'extraRoutes' => ['taoTaskQueue/Main/index']
                ]
            );

            $this->setVersion('0.10.0');
        }

        $this->skip('0.10.0', '0.12.0');

        if ($this->isVersion('0.12.0')) {

            /** @var $taskLogService TaskLogInterface */
            $taskLogService = $this->getServiceManager()->get(TaskLogInterface::SERVICE_ID);

            if ($taskLogService->isRds()) {
                /** @var \common_persistence_SqlPersistence $persistence */
                $persistence = \common_persistence_Manager::getPersistence('default');
                $schemaManager = $persistence->getSchemaManager();
                $fromSchema = $schemaManager->createSchema();
                $toSchema = clone $fromSchema;

                $table = $toSchema->getTable($taskLogService->getBroker()->getTableName());
                if (!$table->hasColumn(TaskLogBrokerInterface::COLUMN_PARENT_ID)) {
                    $table->addColumn(TaskLogBrokerInterface::COLUMN_PARENT_ID, 'string', ["notnull" => false, "length" => 255, "default" => null]);
                }

                $queries = $persistence->getPlatform()->getMigrateSchemaSql($fromSchema, $toSchema);
                foreach ($queries as $query) {
                    $persistence->exec($query);
                }
            }

            $this->setVersion('0.13.0');
        }


        $this->skip('0.13.0', '0.13.2');

        if ($this->isVersion('0.13.2')) {

            /** @var $taskLogService TaskLogInterface */
            $taskLogService = $this->getServiceManager()->get(TaskLogInterface::SERVICE_ID);

            if ($taskLogService->isRds()) {
                /** @var \common_persistence_SqlPersistence $persistence */
                $persistence = \common_persistence_Manager::getPersistence('default');
                $schemaManager = $persistence->getSchemaManager();
                $fromSchema = $schemaManager->createSchema();
                $toSchema = clone $fromSchema;

                $table = $toSchema->getTable($taskLogService->getBroker()->getTableName());
                if (!$table->hasColumn(TaskLogBrokerInterface::COLUMN_MASTER_STATUS)) {
                    $table->addColumn(TaskLogBrokerInterface::COLUMN_MASTER_STATUS, 'boolean', ["default" => 0]);
                }

                $queries = $persistence->getPlatform()->getMigrateSchemaSql($fromSchema, $toSchema);
                foreach ($queries as $query) {
                    $persistence->exec($query);
                }
            }

            $this->setVersion('0.14.0');
        }

        $this->skip('0.14.0', '0.17.0');
    }
}
