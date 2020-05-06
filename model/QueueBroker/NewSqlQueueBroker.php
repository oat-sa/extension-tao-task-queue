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

namespace oat\taoTaskQueue\model\QueueBroker;

use common_persistence_Manager;
use common_persistence_SqlPersistence;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Exception;
use InvalidArgumentException;
use oat\generis\Helper\UuidPrimaryKeyTrait;
use oat\tao\model\taskQueue\Queue\Broker\AbstractQueueBroker;
use oat\tao\model\taskQueue\Task\TaskInterface;
use oat\taoTaskQueue\model\QueueBroker\storage\NewSqlSchema;
use PDO;
use Throwable;

/**
 * Storing messages/tasks in newSQl DB.
 */
class NewSqlQueueBroker extends AbstractQueueBroker
{
    use UuidPrimaryKeyTrait;

    private $persistenceId;

    /** @var common_persistence_SqlPersistence */
    protected $persistence;

    public function __construct(string $persistenceId, int $receiveTasks = 1)
    {
        parent::__construct($receiveTasks);

        if (empty($persistenceId)) {
            throw new InvalidArgumentException("Persistence id needs to be set for " . __CLASS__);
        }

        $this->persistenceId = $persistenceId;
    }

    public function __toPhpCode()
    {
        return 'new ' . get_called_class() . '('
            . \common_Utils::toHumanReadablePhpString($this->persistenceId)
            . ', '
            . \common_Utils::toHumanReadablePhpString($this->getNumberOfTasksToReceive())
            . ')';
    }

    /**
     * Note: this method can be run multiple times because only the migrate queries
     * (result of getMigrateSchemaSql) will be run.
     *
     * @inheritdoc
     */
    public function createQueue(): void
    {
        $persistence = $this->getPersistence();
        /** @var AbstractSchemaManager $schemaManager */
        $schemaManager = $persistence->getDriver()->getSchemaManager();
        $schema = $schemaManager->createSchema();
        $fromSchema = clone $schema;
        try {
            $schema->dropTable($this->getTableName());
        } catch (Throwable $exception) {
            $this->logDebug('Schema of ' . $this->getTableName() . ' table already up to date.');
        }
        // Create the table
        $schema = $this->getSchemaProvider()
            ->setQueueName($this->getQueueName())
            ->getSchema($schema, $this->getTableName());
        $queries = $persistence->getPlatForm()->getMigrateSchemaSql($fromSchema, $schema);
        foreach ($queries as $query) {
            $persistence->exec($query);
        }
    }

    /**
     * Insert a new task into the queue table.
     */
    public function push(TaskInterface $task): bool
    {
        return (bool)$this->getPersistence()->insert($this->getTableName(), [
            'id' => $this->getUniquePrimaryKey(),
            'message' => $this->serializeTask($task),
            'created_at' => $this->getPersistence()->getPlatForm()->getNowExpression(),
            'visible' => true,
        ]);
    }

    /**
     * Delete the message after being processed by the worker.
     *
     * @param TaskInterface $task
     */
    public function delete(TaskInterface $task): void
    {
        $this->doDelete($task->getMetadata('NewSqlMessageId'), [
            'InternalMessageId' => $task->getId(),
            'NewSqlMessageId' => $task->getMetadata('NewSqlMessageId')
        ]);
    }

    public function count(): int
    {
        try {
            return (int)$this->getQueryBuilder()
                ->select('COUNT(id)')
                ->from($this->getTableName())
                ->andWhere('visible = :visible')
                ->setParameter('visible', true, ParameterType::BOOLEAN)
                ->execute()
                ->fetchColumn();
        } catch (\Exception $e) {
            $this->logError('Counting tasks failed with MSG: ' . $e->getMessage());
        }

        return 0;
    }


    /**
     * @inheritDoc
     */
    protected function doPop(): void
    {
        $this->getPersistence()->getPlatform()->beginTransaction();

        $logContext = $this->getLogContext();

        try {
            $dbResult = $this->fetchVisibleMessages();

            if ($dbResult) {
                // set the received messages to invisible for other workers
                $this->changeMessagesVisibility($dbResult);

                $this->processMessages($dbResult, $logContext);
            } else {
                $this->logDebug('No task in the queue.', $logContext);
            }

            $this->getPersistence()->getPlatform()->commit();
        } catch (Exception $e) {
            $this->getPersistence()->getPlatform()->rollBack();
            $this->logError('Popping tasks failed with MSG: ' . $e->getMessage(), $logContext);
        }
    }

    /**
     * @param string $id
     * @param array $logContext
     * @return int
     */
    protected function doDelete($id, array $logContext = []): void
    {
        try {
            $this->getQueryBuilder()
                ->delete($this->getTableName())
                ->where('id = :id')
                ->andWhere('visible = :visible')
                ->setParameter('id', $id)
                ->setParameter('visible', false, ParameterType::BOOLEAN)
                ->execute();
        } catch (\Exception $e) {
            $this->logError('Deleting task failed with MSG: ' . $e->getMessage(), $logContext);
        }
    }

    private function getSchemaProvider(): NewSqlSchema
    {
        return $this->getServiceLocator()->get(NewSqlSchema::class);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        return $this->getPersistence()->getPlatform()->getQueryBuilder();
    }

    private function getPersistence(): ?common_persistence_SqlPersistence
    {
        if (is_null($this->persistence)) {
            $this->persistence = $this->getServiceLocator()
                ->get(common_persistence_Manager::SERVICE_ID)
                ->getPersistenceById($this->persistenceId);
        }

        return $this->persistence;
    }

    private function getTableName(): string
    {
        return strtolower($this->getQueueNameWithPrefix());
    }

    private function changeMessagesVisibility(array $dbResult): void
    {
        $qb = $this->getQueryBuilder()
            ->update($this->getTableName())
            ->set('visible', ':visible')
            ->where('id IN (:ids)')
            ->setParameter('visible', false, ParameterType::BOOLEAN)
            ->setParameter('ids', array_column($dbResult, 'id'), Connection::PARAM_STR_ARRAY);

        $qb->execute();
    }

    private function processMessages(array $dbResult, array $logContext): void
    {
        foreach ($dbResult as $row) {
            if ($task = $this->unserializeTask($row['message'], $row['id'], $logContext)) {
                $task->setMetadata('NewSqlMessageId', $row['id']);
                $this->pushPreFetchedMessage($task);
            }
        }
    }

    private function fetchVisibleMessages(): array
    {
        $qb = $this->getQueryBuilder()
            ->select('id, message')
            ->from($this->getTableName())
            ->where('visible = :visible')
            ->orderBy('created_at')
            ->setMaxResults($this->getNumberOfTasksToReceive());

        /**
         * SELECT ... FOR UPDATE is used for locking
         */
        $sql = $qb->getSQL() . ' ' . $this->getPersistence()->getPlatForm()->getWriteLockSQL();

        return $this->getPersistence()->query($sql, ['visible' => true])->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getLogContext(): array
    {
        return [
            'Queue' => $this->getQueueNameWithPrefix()
        ];
    }
}
