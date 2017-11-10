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

use oat\oatbox\PhpSerializable;
use common_report_Report as Report;
use Doctrine\DBAL\Query\QueryBuilder;
use oat\taoTaskQueue\model\Entity\TaskLogEntity;
use oat\taoTaskQueue\model\Entity\TasksLogsStats;
use oat\taoTaskQueue\model\QueueDispatcherInterface;
use oat\taoTaskQueue\model\Task\CallbackTaskInterface;
use oat\taoTaskQueue\model\Task\TaskInterface;
use oat\taoTaskQueue\model\TaskLogInterface;
use oat\taoTaskQueue\model\ValueObjects\TaskLogCategorizedStatus;
use Psr\Log\LoggerAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use oat\oatbox\log\LoggerAwareTrait;

/**
 * Storing message logs in RDS.
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class RdsTaskLogBroker implements TaskLogBrokerInterface, PhpSerializable, ServiceLocatorAwareInterface, LoggerAwareInterface
{
    use ServiceLocatorAwareTrait;
    use LoggerAwareTrait;

    private $persistenceId;

    /**
     * @var \common_persistence_SqlPersistence
     */
    protected $persistence;

    private $containerName;

    /**
     * RdsTaskLogBroker constructor.
     *
     * @param string $persistenceId
     * @param null $containerName
     */
    public function __construct($persistenceId, $containerName = null)
    {
        if (empty($persistenceId)) {
            throw new \InvalidArgumentException("Persistence id needs to be set for ". __CLASS__);
        }

        $this->persistenceId = $persistenceId;
        $this->containerName = empty($containerName) ? self::DEFAULT_CONTAINER_NAME : $containerName;
    }

    public function __toPhpCode()
    {
        return 'new '. get_called_class() .'('
            . \common_Utils::toHumanReadablePhpString($this->persistenceId)
            . ', '
            . \common_Utils::toHumanReadablePhpString($this->containerName)
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
        return strtolower(QueueDispatcherInterface::QUEUE_PREFIX .'_'. $this->containerName);
    }

    /**
     * @inheritdoc
     */
    public function createContainer()
    {
        /** @var \common_persistence_sql_pdo_mysql_SchemaManager $schemaManager */
        $schemaManager = $this->getPersistence()->getSchemaManager();

        $fromSchema = $schemaManager->createSchema();
        $toSchema = clone $fromSchema;

        // if our table does not exist, let's create it
        if(false === $fromSchema->hasTable($this->getTableName())) {
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
            $table->addIndex(['task_name', 'owner'], $this->getTableName() .'_IDX_task_name_owner');
            $table->addIndex(['status'], $this->getTableName() .'_IDX_status');

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
     * @inheritdoc
     */
    public function findAvailableByUser($userId, $limit, $offset)
    {
        try {
            $filters = $this->getAvailableFilters($userId);

            $qb = $this->getQueryBuilder()
                ->select('*')
                ->from($this->getTableName());

            $qb->setMaxResults($limit);
            $qb->setFirstResult($offset);

            foreach ($filters as $filter) {
                $qb->andWhere($filter['column'] . $filter['operator'] . $filter['columnSqlTranslate'])
                    ->setParameter($filter['column'], $filter['value'])
                ;
            }

            $rows = $qb->execute()->fetchAll();
            $collection = TaskLogCollection::createFromArray($rows);

        } catch (\Exception $exception) {
            $this->logWarning('Something went wrong getting task logs for user "'. $userId .'"; MSG: ' . $exception->getMessage());

            $collection = TaskLogCollection::createEmptyCollection();
        }

        return $collection;
    }

    /**
     * @inheritdoc
     */
    public function getStats($userId)
    {
        $filters = $this->getAvailableFilters($userId);
        $qb = $this->getQueryBuilder();

        $qb->select(
            $this->buildCounterStatusSql('inProgressTasks', TaskLogCategorizedStatus::getMappedStatuses(TaskLogCategorizedStatus::STATUS_IN_PROGRESS)) . ', ' .
            $this->buildCounterStatusSql('completedTasks', TaskLogCategorizedStatus::getMappedStatuses(TaskLogCategorizedStatus::STATUS_COMPLETED)) . ', ' .
            $this->buildCounterStatusSql('failedTasks', TaskLogCategorizedStatus::getMappedStatuses(TaskLogCategorizedStatus::STATUS_FAILED))
        );
        $qb->from($this->getTableName());

        foreach ($filters as $filter) {
            $qb->andWhere($filter['column'] . $filter['operator'] . $filter['columnSqlTranslate'])
                ->setParameter($filter['column'], $filter['value'])
            ;
        }

        $row = $qb->execute()->fetch();

        return TasksLogsStats::buildFromArray($row);
    }

    /**
     * @inheritdoc
     */
    public function getByIdAndUser($taskId, $userId)
    {
        $filters = $this->getAvailableFilters($userId);

        $qb = $this->getQueryBuilder()
            ->select('*')
            ->from($this->getTableName())
            ->andWhere('id = :id')
            ->setParameter('id', (string) $taskId)
        ;

        foreach ($filters as $filter) {
            $qb->andWhere($filter['column'] . $filter['operator'] . $filter['columnSqlTranslate'])
                ->setParameter($filter['column'], $filter['value'])
            ;
        }

        $row = $qb->execute()->fetch();

        if ($row === false) {
            throw new \common_exception_NotFound('Task log for task "'. $taskId .'" not found');
        }

        return TaskLogEntity::createFromArray($row);
    }

    /**
     * @inheritdoc
     */
    public function archive(TaskLogEntity $entity)
    {
        $this->getPersistence()->getPlatform()->beginTransaction();

        try {
            $qb = $this->getQueryBuilder()
                ->update($this->getTableName())
                ->set('status', ':status_new')
                ->set('updated_at', ':updated_at')
                ->where('id = :id')
                ->setParameter('id', (string) $entity->getId())
                ->setParameter('status_new', (string) TaskLogInterface::STATUS_ARCHIVED)
                ->setParameter('updated_at', $this->getPersistence()->getPlatForm()->getNowExpression());

            $qb->execute();
            $this->getPersistence()->getPlatform()->commit();

        } catch (\Exception $e) {
            $this->getPersistence()->getPlatform()->rollBack();

            return false;
        }

        return true;
    }

    /**
     * @return QueryBuilder
     */
    private function getQueryBuilder()
    {
        /**@var \common_persistence_sql_pdo_mysql_Driver $driver */
        return $this->getPersistence()->getPlatform()->getQueryBuilder();
    }

    /**
     * @param $userId
     * @return array
     */
    private function getAvailableFilters($userId)
    {
        $filters = [
            [
                'column' => 'status',
                'columnSqlTranslate' => ':status',
                'operator' => '!=',
                'value' => TaskLogInterface::STATUS_ARCHIVED
            ]
        ];

        if ($userId !== TaskLogInterface::SUPER_USER) {
            $filters[] =  [
                'column' => 'owner',
                'columnSqlTranslate' => ':owner',
                'operator' => '=',
                'value' => $userId
            ];
        }

        return $filters;
    }

    /**
     * @param string $statusColumn
     * @param array $inStatuses
     * @return string
     */
    private function buildCounterStatusSql($statusColumn, array $inStatuses)
    {
        if (empty($inStatuses)) {
            return '';
        }

        $sql =  "COUNT( CASE WHEN ";
        foreach ($inStatuses as $status)
        {

            if ($status !== reset($inStatuses)) {
                $sql .= " OR status = '". $status ."'";
            } else {
                $sql .= " status = '". $status."'";
            }
        }

        $sql .= " THEN 0 END ) AS $statusColumn";


        return $sql;
    }
}