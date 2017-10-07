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

namespace oat\taoTaskQueue\model\TaskLogBroker;

use oat\oatbox\service\ConfigurableService;
use common_report_Report as Report;
use Doctrine\DBAL\Query\QueryBuilder;
use oat\taoTaskQueue\model\Task\CallbackTaskInterface;
use oat\taoTaskQueue\model\QueueInterface;
use oat\taoTaskQueue\model\Task\TaskInterface;

/**
 * Storing message logs in RDS.
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class RdsTaskLogBroker extends ConfigurableService implements TaskLogBrokerInterface
{
    const CONFIG_PERSISTENCE = 'persistence';

    /**
     * @var \common_persistence_SqlPersistence
     */
    protected $persistence;

    /**
     * RdsTaskLogBroker constructor.
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        parent::__construct($options);

        if (!$this->hasOption(self::OPTION_PERSISTENCE) || empty($this->getOption(self::OPTION_PERSISTENCE))) {
            throw new \InvalidArgumentException("Persistence id needs to be set for ". __CLASS__);
        }

        if (!$this->hasOption(self::OPTION_CONTAINER_NAME) || empty($this->getOption(self::OPTION_CONTAINER_NAME))) {
            $this->setOption(self::OPTION_CONTAINER_NAME, self::DEFAULT_CONTAINER_NAME);
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
        return strtolower(QueueInterface::QUEUE_PREFIX .'_'. $this->getOption(self::OPTION_CONTAINER_NAME));
    }

    /**
     * @inheritdoc
     */
    public function createContainer()
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
            $table->addColumn('id', 'string', ["notnull" => true, "length" => 255]);
            $table->addColumn('task_name', 'string', ["notnull" => true, "length" => 255]);
            $table->addColumn('label', 'string', ["notnull" => false, "length" => 255]);
            $table->addColumn('status', 'string', ["notnull" => true, "length" => 50]);
            $table->addColumn('owner', 'string', ["notnull" => false, "length" => 255, "default" => null]);
            $table->addColumn('report', 'text', ["notnull" => false, "default" => null]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['task_name', 'owner'], 'IDX_task_name_owner');
            $table->addIndex(['status'], 'IDX_status');

            $queries = $this->getPersistence()->getPlatForm()->getMigrateSchemaSql($fromSchema, $toSchema);
            foreach ($queries as $query) {
                $this->getPersistence()->exec($query);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function add(TaskInterface $task, $status, $label = null)
    {
        $this->getPersistence()->insert($this->getTableName(), [
            'id'   => (string) $task->getId(),
            'task_name' => $task instanceof CallbackTaskInterface && is_object($task->getCallable()) ? get_class($task->getCallable()) : get_class($task),
            'label' => (string) $label,
            'status' => (string) $status,
            'owner' => (string) $task->getOwner(),
            'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $this->getPersistence()->getPlatForm()->getNowExpression()
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getStatus($taskId)
    {
        $qb = $this->getQueryBuilder()
            ->select('status')
            ->from($this->getTableName())
            ->andWhere('id = :id')
            ->setParameter('id', $taskId);

        return $qb->execute()->fetchColumn();
    }

    /**
     * @inheritdoc
     */
    public function updateStatus($taskId, $newStatus, $prevStatus = null)
    {
        $qb = $this->getQueryBuilder()
            ->update($this->getTableName())
            ->set('status', ':status_new')
            ->set('updated_at', ':updated_at')
            ->where('id = :id')
            ->setParameter('id', (string) $taskId)
            ->setParameter('status_new', (string) $newStatus)
            ->setParameter('updated_at', $this->getPersistence()->getPlatForm()->getNowExpression());

        if ($prevStatus) {
            $qb->andWhere('status = :status_prev')
                ->setParameter('status_prev', (string) $prevStatus);
        }

        return $qb->execute();
    }

    /**
     * @inheritdoc
     */
    public function addReport($taskId, Report $report, $newStatus = null)
    {
        $qb = $this->getQueryBuilder()
            ->update($this->getTableName())
            ->set('report', ':report')
            ->set('status', ':status_new')
            ->set('updated_at', ':updated_at')
            ->andWhere('id = :id')
            ->setParameter('id', (string) $taskId)
            ->setParameter('report', json_encode($report))
            ->setParameter('status_new', (string) $newStatus)
            ->setParameter('updated_at', $this->getPersistence()->getPlatForm()->getNowExpression());

        return $qb->execute();
    }

    /**
     * @inheritdoc
     */
    public function getReport($taskId)
    {
        $qb = $this->getQueryBuilder()
            ->select('report')
            ->from($this->getTableName())
            ->andWhere('id = :id')
            ->setParameter('id', (string) $taskId);

        if (($reportJson = $qb->execute()->fetchColumn())
            && ($reportData = json_decode($reportJson, true)) !== null
            && json_last_error() === JSON_ERROR_NONE
        ) {
            // if we have a valid JSON string and no JSON error, let's restore the report object
            return Report::jsonUnserialize($reportData);
        }

        return null;
    }

    /**
     * @return QueryBuilder
     */
    private function getQueryBuilder()
    {
        /**@var \common_persistence_sql_pdo_mysql_Driver $driver */
        return $this->getPersistence()->getPlatform()->getQueryBuilder();
    }
}