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

namespace oat\taoTaskQueue\model\QueueBroker;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Exception;
use InvalidArgumentException;
use Google\Cloud\Spanner\Transaction;
use oat\generis\Helper\UuidPrimaryKeyTrait;
use oat\tao\model\taskQueue\Queue\Broker\AbstractQueueBroker;
use oat\tao\model\taskQueue\Task\TaskInterface;
use PDO;

/**
 * Storing messages/tasks in DB.
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class RdsQueueBroker extends AbstractQueueBroker
{
    use UuidPrimaryKeyTrait;

    private $persistenceId;

    /**
     * @var \common_persistence_SqlPersistence
     */
    protected $persistence;

    /**
     * RdsQueueBroker constructor.
     *
     * @param string $persistenceId
     * @param int $receiveTasks
     */
    public function __construct($persistenceId, $receiveTasks = 1)
    {
        parent::__construct($receiveTasks);

        if (empty($persistenceId)) {
            throw new InvalidArgumentException("Persistence id needs to be set for ". __CLASS__);
        }

        $this->persistenceId = $persistenceId;
    }

    public function __toPhpCode()
    {
        return 'new '. get_called_class() .'('
            . \common_Utils::toHumanReadablePhpString($this->persistenceId)
            . ', '
            . \common_Utils::toHumanReadablePhpString($this->getNumberOfTasksToReceive())
            .')';
    }

    /**
     * @return \common_persistence_SqlPersistence
     */
    protected function getPersistence()
    {
        if (is_null($this->persistence)) {
            $this->persistence = $this->getServiceLocator()
                ->get(\common_persistence_Manager::SERVICE_ID)
                ->getPersistenceById($this->persistenceId);
        }

        return $this->persistence;
    }

    /**
     * @return string
     */
    protected function getTableName()
    {
        return strtolower($this->getQueueNameWithPrefix());
    }

    /**
     * Note: this method can be run multiple times because only the migrate queries (result of getMigrateSchemaSql) will be run.
     *
     * @inheritdoc
     */
    public function createQueue()
    {
        $persistence = $this->getPersistence();
        /** @var AbstractSchemaManager $schemaManager */
        $schemaManager = $persistence->getDriver()->getSchemaManager();

        /** @var Schema $schema */
        $schema = $schemaManager->createSchema();
        $fromSchema = clone $schema;

        if (in_array($this->getTableName(), $schemaManager->getTables())) {
            $schema->dropTable($this->getTableName());
        }
        $queries = $persistence->getPlatForm()->getMigrateSchemaSql($fromSchema, $schema);

        foreach ($queries as $query) {
            $persistence->exec($query);
        }

        try {
            $table = $schema->createTable($this->getTableName());
            $table->addOption('engine', 'InnoDB');
            $table->addColumn('id', 'string', ['length' => 36]);
            $table->addColumn('message', 'text', ["notnull" => true]);
            $table->addColumn('visible', 'boolean', []);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['created_at', 'visible'], 'IDX_created_at_visible_'. $this->getQueueName());

        } catch (SchemaException $e) {
            $this->logDebug('Schema of '. $this->getTableName() .' table already up to date.');
        }

        $queries = $persistence->getPlatForm()->getMigrateSchemaSql($fromSchema, $schema);

        foreach ($queries as $query) {
            $persistence->exec($query);
        }

        if ($queries) {
            $this->logDebug('Queue '. $this->getTableName() .' created/updated in RDS.');
        }
    }

    /**
     * Insert a new task into the queue table.
     *
     * @param TaskInterface $task
     * @return bool
     */
    public function push(TaskInterface $task)
    {
        return (bool) $this->getPersistence()->insert($this->getTableName(), [
            'id' => $this->getUniquePrimaryKey(),
            'message' => $this->serializeTask($task),
            'created_at' => $this->getPersistence()->getPlatForm()->getNowExpression(),
            'visible' => true,
        ]);
    }

    /**
     * Does the DBAL specific pop mechanism.
     */
    protected function doPop()
    {
        $logContext = ['Queue' => $this->getQueueNameWithPrefix()];

        try {
            $messages = $this->extractTasksFromQueue();
        } catch (Exception $e) {
            $this->logError('Popping tasks failed with MSG: ' . $e->getMessage(), $logContext);
        }

        // Checks message presence.
        if (count($messages) === 0) {
            $this->logDebug('No task in the queue.', $logContext);
            return;
        }
        $this->logDebug('Received ' . count($messages) . ' messages.', $logContext);
        
        // Pushes messages to prefetch.
        foreach ($messages as $message) {
            $task = $this->unserializeTask($message['message'], $message['id'], $logContext);
            if ($task) {
                $task->setMetadata('RdsMessageId', $message['id']);
                $this->pushPreFetchedMessage($task);
            }
        }
    }

    public function extractTasksFromQueue()
    {
        $selectVisibleTasksQb = $this->getQueryBuilder()
            ->select('id, message')
            ->from($this->getTableName())
            ->andWhere('visible = :visible')
            ->setParameter('visible', true, ParameterType::BOOLEAN)
            ->orderBy('created_at')
            ->setMaxResults($this->getNumberOfTasksToReceive());

        // SELECT ... FOR UPDATE is used for locking
        // @see https://dev.mysql.com/doc/refman/5.6/en/innodb-locking-reads.html
        $selectVisibleTasksSql = $selectVisibleTasksQb->getSQL() .' '. $this->getPersistence()->getPlatForm()->getWriteLockSQL();

        // set the received messages to invisible for other workers
        $consumeTasksQb = $this->getQueryBuilder()
            ->update($this->getTableName())
            ->set('visible', ':visible')
            ->where('id IN (:ids)')
            ->setParameter('visible', false, ParameterType::BOOLEAN);
        
        return $this->getPersistence()->transactional(
            static function(Transaction $transaction) use ($selectVisibleTasksSql, $consumeTasksQb) {
                $messages = $transaction->execute($selectVisibleTasksSql)->fetchAll(PDO::FETCH_ASSOC);
                if ($messages) {
                    $consumeTasksQb->setParameter('ids', array_column($messages, 'id'), Connection::PARAM_STR_ARRAY);
                    $transaction->executeUpdate($consumeTasksQb->getSQL());
                    $transaction->commit();
                }
                return $messages;
            }
        );
    }
        
    /**
     * Delete the message after being processed by the worker.
     *
     * @param TaskInterface $task
     */
    public function delete(TaskInterface $task)
    {
        $this->doDelete($task->getMetadata('RdsMessageId'), [
            'InternalMessageId' => $task->getId(),
            'RdsMessageId' => $task->getMetadata('RdsMessageId')
        ]);
    }

    /**
     * @param string $id
     * @param array  $logContext
     * @return int
     */
    protected function doDelete($id, array $logContext = [])
    {
        try {
            $this->getQueryBuilder()
                ->delete($this->getTableName())
                ->where('id = :id')
                ->andWhere('visible = :visible')
                ->setParameter('id',  $id)
                ->setParameter('visible', false, ParameterType::BOOLEAN)
                ->execute();
        } catch (Exception $e) {
            $this->logError('Deleting task failed with MSG: '. $e->getMessage(), $logContext);
        }
    }

    /**
     * @return int
     */
    public function count()
    {
        try {
            $qb = $this->getQueryBuilder()
                ->select('COUNT(id)')
                ->from($this->getTableName())
                ->andWhere('visible = :visible')
                ->setParameter('visible', true, ParameterType::BOOLEAN);

            return (int) $qb->execute()->fetchColumn();
        } catch (Exception $e) {
            $this->logError('Counting tasks failed with MSG: '. $e->getMessage());
        }

        return 0;
    }

    /**
     * @return QueryBuilder
     */
    private function getQueryBuilder()
    {
        return $this->getPersistence()->getPlatform()->getQueryBuilder();
    }
}
