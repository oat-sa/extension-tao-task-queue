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

use Doctrine\DBAL\Query\QueryBuilder;
use oat\taoTaskQueue\model\Task\TaskInterface;

/**
 * Storing messages/tasks in DB.
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class RdsQueueBroker extends AbstractQueueBroker
{
    const OPTION_PERSISTENCE = 'persistence';

    /**
     * @var \common_persistence_SqlPersistence
     */
    protected $persistence;

    public function __construct(array $options)
    {
        parent::__construct($options);

        if (!$this->hasOption(self::OPTION_PERSISTENCE) || empty($this->getOption(self::OPTION_PERSISTENCE))) {
            throw new \InvalidArgumentException("Persistence id needs to be set for ". __CLASS__);
        }
    }

    /**
     * @return \common_persistence_SqlPersistence
     */
    protected function getPersistence()
    {
        if (is_null($this->persistence)) {
            $this->persistence = $this->getServiceManager()
                ->get(\common_persistence_Manager::SERVICE_ID)
                ->getPersistenceById($this->getOption(self::OPTION_PERSISTENCE));
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
     * Create queue table if it does not exist
     */
    public function createQueue()
    {
        /** @var \common_persistence_sql_pdo_mysql_SchemaManager $schemaManager */
        $schemaManager = $this->getPersistence()->getSchemaManager();

        /** @var \Doctrine\DBAL\Schema\MySqlSchemaManager $sm */
        $sm = $schemaManager->getSchemaManager();

        // if our table does not exist, let's create it
        if(false === $sm->tablesExist([$this->getTableName()])) {
            $fromSchema = $schemaManager->createSchema();
            $toSchema = clone $fromSchema;

            $table = $toSchema->createTable($this->getTableName());
            $table->addOption('engine', 'InnoDB');
            $table->addColumn('id', 'integer', ["autoincrement" => true, "notnull" => true, "unsigned" => true]);
            $table->addColumn('message', 'text', ["notnull" => true]);
            $table->addColumn('visible', 'boolean', ["default" => 1]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['created_at', 'visible'], 'IDX_created_at_visible');

            $queries = $this->getPersistence()->getPlatForm()->getMigrateSchemaSql($fromSchema, $toSchema);
            foreach ($queries as $query) {
                $this->getPersistence()->exec($query);
            }
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
            'message' => json_encode($task),
            'created_at' => $this->getPersistence()->getPlatForm()->getNowExpression()
        ]);
    }

    /**
     * Does the DBAL specific pop mechanism.
     */
    protected function doPop()
    {
        $this->getPersistence()->getPlatform()->beginTransaction();

        $logContext = [
            'Queue' => $this->getQueueNameWithPrefix()
        ];

        try {
            $qb = $this->getQueryBuilder()
                ->select('id, message')
                ->from($this->getTableName())
                ->andWhere('visible = :visible')
                ->orderBy('created_at')
                ->setMaxResults($this->getNumberOfTasksToReceive());

            /**
             * SELECT ... FOR UPDATE is used for locking
             *
             * @see https://dev.mysql.com/doc/refman/5.6/en/innodb-locking-reads.html
             */
            $sql = $qb->getSQL() .' '. $this->getPersistence()->getPlatForm()->getWriteLockSQL();

            if ($dbResult = $this->getPersistence()->query($sql, ['visible' => 1])->fetchAll(\PDO::FETCH_ASSOC)) {

                // set the received messages to invisible for other workers
                $qb = $this->getQueryBuilder()
                    ->update($this->getTableName())
                    ->set('visible', ':visible')
                    ->where('id IN ('. implode(',', array_column($dbResult, 'id')) .')')
                    ->setParameter('visible', 0);

                //var_dump($qb->getSQL(), $qb->getParameters());die;

                $qb->execute();

                foreach ($dbResult as $row) {
                    if ($task = $this->unserializeTask($row['message'], $row['id'], $logContext)) {
                        $task->setMetadata('RdsMessageId', $row['id']);
                        $this->pushPreFetchedMessage($task);
                    }
                }
            } else {
                $this->logDebug('No task in the queue.', $logContext);
            }

            $this->getPersistence()->getPlatform()->commit();
        } catch (\Exception $e) {
            $this->getPersistence()->getPlatform()->rollBack();
            $this->logError('Popping tasks failed with MSG: '. $e->getMessage(), $logContext);
        }
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
                ->setParameter('id', (int) $id)
                ->setParameter('visible', 0)
                ->execute();
        } catch (\Exception $e) {
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
                ->setParameter('visible', 1);

            return (int) $qb->execute()->fetchColumn();
        } catch (\Exception $e) {
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