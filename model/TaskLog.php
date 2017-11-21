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

namespace oat\taoTaskQueue\model;

use common_report_Report as Report;
use oat\oatbox\event\EventManager;
use oat\oatbox\service\ConfigurableService;
use oat\taoTaskQueue\model\Entity\TaskLogEntity;
use oat\taoTaskQueue\model\Event\TaskLogArchivedEvent;
use oat\taoTaskQueue\model\Task\TaskInterface;
use oat\taoTaskQueue\model\TaskLog\DataTablePayload;
use oat\taoTaskQueue\model\TaskLog\TaskLogFilter;
use oat\taoTaskQueue\model\TaskLogBroker\RdsTaskLogBroker;
use oat\taoTaskQueue\model\TaskLogBroker\TaskLogBrokerInterface;
use oat\oatbox\log\LoggerAwareTrait;

/**
 * Managing task logs:
 * - storing every information for a task like dates, status changes, reports etc.
 * - each task has one record in the container identified by its id
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class TaskLog extends ConfigurableService implements TaskLogInterface
{
    use LoggerAwareTrait;

    /**
     * @var TaskLogBrokerInterface
     */
    private $broker;

    /**
     * TaskLog constructor.
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        parent::__construct($options);

        if (!$this->hasOption(self::OPTION_TASK_LOG_BROKER) || empty($this->getOption(self::OPTION_TASK_LOG_BROKER))) {
            throw new \InvalidArgumentException("Task Log Broker service needs to be set.");
        }
    }

    /**
     * Gets the task log broker. It will be created if it has not been initialized.
     *
     * @return TaskLogBrokerInterface
     */
    public function getBroker()
    {
        if (is_null($this->broker)) {
            $this->broker = $this->getOption(self::OPTION_TASK_LOG_BROKER);
            $this->broker->setServiceLocator($this->getServiceLocator());
        }

        return $this->broker;
    }

    /**
     * @inheritdoc
     */
    public function isRds()
    {
        return $this->getBroker() instanceof RdsTaskLogBroker;
    }

    /**
     * @inheritdoc
     */
    public function createContainer()
    {
        $this->getBroker()->createContainer();
    }

    /**
     * @inheritdoc
     */
    public function add(TaskInterface $task, $status, $label = null)
    {
        try {
            $this->validateStatus($status);

            $this->getBroker()->add($task, $status, $label);
        } catch (\Exception $e) {
            $this->logError('Adding result for task '. $task->getId() .' failed with MSG: '. $e->getMessage());
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setStatus($taskId, $newStatus, $prevStatus = null)
    {
        try {
            $this->validateStatus($newStatus);

            if (!is_null($prevStatus)) {
                $this->validateStatus($prevStatus);
            }

            return $this->getBroker()->updateStatus($taskId, $newStatus, $prevStatus);
        } catch (\Exception $e) {
            $this->logError('Setting the status for task '. $taskId .' failed with MSG: '. $e->getMessage());
        }

        return 0;
    }

    /**
     * @inheritdoc
     */
    public function getStatus($taskId)
    {
        try {
            return $this->getBroker()->getStatus($taskId);
        } catch (\Exception $e) {
            $this->logError('Getting status for task '. $taskId .' failed with MSG: '. $e->getMessage());
        }

        return self::STATUS_UNKNOWN;
    }

    /**
     * @inheritdoc
     */
    public function setReport($taskId, Report $report, $newStatus = null)
    {
        try {
            $this->validateStatus($newStatus);

            if (!$this->getBroker()->addReport($taskId, $report, $newStatus)) {
                throw new \RuntimeException("Report is not saved.");
            }
        } catch (\Exception $e) {
            $this->logError('Setting report for item '. $taskId .' failed with MSG: '. $e->getMessage());
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getReport($taskId)
    {
        try {
            return $this->getBroker()->getReport($taskId);
        } catch (\Exception $e) {
            $this->logError('Getting report for task '. $taskId .' failed with MSG: '. $e->getMessage());
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function search(TaskLogFilter $filter)
    {
        return $this->getBroker()->search($filter);
    }

    /**
     * @inheritdoc
     */
    public function getDataTablePayload(TaskLogFilter $filter)
    {
        return new DataTablePayload($filter, $this->getBroker());
    }

    /**
     * @inheritdoc
     */
    public function getById($taskId)
    {
        $filter = (new TaskLogFilter())
            ->eq(TaskLogBrokerInterface::COLUMN_ID, $taskId);

        $collection = $this->search($filter);

        if ($collection->isEmpty()) {
            throw new \common_exception_NotFound('Task log for task "'. $taskId .'" not found');
        }

        return $collection->first();
    }

    /**
     * @inheritdoc
     */
    public function getByIdAndUser($taskId, $userId)
    {
        $filter = (new TaskLogFilter())
            ->addAvailableFilters($userId)
            ->eq(TaskLogBrokerInterface::COLUMN_ID, $taskId);

        $collection = $this->search($filter);

        if ($collection->isEmpty()) {
            throw new \common_exception_NotFound('Task log for task "'. $taskId .'" not found');
        }

        return $collection->first();
    }

    /**
     * @inheritdoc
     */
    public function findAvailableByUser($userId, $limit = null, $offset = null)
    {
        $filter = (new TaskLogFilter())
            ->addAvailableFilters($userId)
            ->setLimit(is_null($limit) ? self::DEFAULT_LIMIT : $limit)
            ->setOffset(is_null($offset) ? 0 : $offset);

        return $this->getBroker()->search($filter);
    }

    /**
     * @inheritdoc
     */
    public function getStats($userId)
    {
        $filter = (new TaskLogFilter())
            ->addAvailableFilters($userId);

        return $this->getBroker()->getStats($filter);
    }

    /**
     * @inheritdoc
     */
    public function archive(TaskLogEntity $entity, $forceArchive = false)
    {
        if ($entity->getStatus()->isInProgress() && $forceArchive === false) {
            throw new \Exception('Task cannot be archived because it is in progress.');
        }

        $isArchived = $this->getBroker()->archive($entity);

        if ($isArchived) {
            $this->getServiceManager()->get(EventManager::SERVICE_ID)
                ->trigger(new TaskLogArchivedEvent($entity, $forceArchive));
        }

        return $isArchived;
    }

    /**
     * @param string $status
     */
    protected function validateStatus($status)
    {
        $statuses = [
            self::STATUS_ENQUEUED,
            self::STATUS_DEQUEUED,
            self::STATUS_RUNNING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_ARCHIVED
        ];

        if (!in_array($status, $statuses)) {
            throw new \InvalidArgumentException('Status "'. $status .'"" is not a valid task queue status.');
        }
    }
}