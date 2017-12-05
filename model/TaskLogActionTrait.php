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
use oat\oatbox\service\ServiceManager;
use oat\taoTaskQueue\model\Entity\TaskLogEntity;
use oat\taoTaskQueue\model\TaskLog\TaskLogFilter;
use oat\taoTaskQueue\model\TaskLogBroker\TaskLogBrokerInterface;
use oat\taoTaskQueue\model\ValueObjects\TaskLogCategorizedStatus;

/**
 * Helper trait for legacy REST actions/controllers to operate with task log data for a given task.
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
trait TaskLogActionTrait
{
    /**
     * @return ServiceManager
     */
    abstract protected function getServiceManager();

    /**
     * @param string $taskId
     * @param string $userId
     * @return TaskLogEntity
     */
    protected function getTaskLogEntity($taskId, $userId = null)
    {
        /** @var TaskLogInterface $taskLog */
        $taskLog = $this->getServiceManager()->get(TaskLogInterface::SERVICE_ID);

        if (is_null($userId)) {
            $userId = $this->getUserId();
        }

        return $taskLog->getByIdAndUser((string) $taskId, (string) $userId);
    }

    /**
     * Get default user id.
     *
     * @return string
     */
    protected function getUserId()
    {
        return \common_session_SessionManager::getSession()->getUserUri();
    }

    /**
     * @param string $taskId
     * @param string|null $forcedTaskType
     * @param string|null $userId
     * @return array
     * @throws \common_exception_BadRequest
     */
    protected function getTaskLogReturnData($taskId, $forcedTaskType = null, $userId = null)
    {
        $taskLogEntity = $this->getTaskLogEntity($taskId, $userId);

        if (!is_null($forcedTaskType) && $taskLogEntity->getTaskName() !== $forcedTaskType) {
            throw new \common_exception_BadRequest("Wrong task type");
        }

        $result['id']     = $this->getTaskId($taskLogEntity);
        $result['status'] = $this->getTaskStatus($taskLogEntity);
        $result['report'] = $taskLogEntity->getReport() ? $this->getTaskReport($taskLogEntity) : [];
        $result['status_code'] = $this->taskStatusMapper((string) $taskLogEntity->getStatus());
        $result['remote_environments'] = $this->getChildTasks($taskLogEntity);

        return array_merge($result, (array) $this->addExtraReturnData($taskLogEntity));
    }

    /**
     * Return task identifier
     *
     * @param TaskLogEntity $taskLogEntity
     * @return string
     */
    protected function getTaskId(TaskLogEntity $taskLogEntity)
    {
        return $taskLogEntity->getId();
    }

    /**
     * @param TaskLogEntity $taskLogEntity
     * @return string
     */
    protected function getTaskStatus(TaskLogEntity $taskLogEntity)
    {
        return $taskLogEntity->getStatus()->getLabel();
    }

    /**
     * As default, it returns the reports as an associative array.
     *
     * @param TaskLogEntity $taskLogEntity
     * @return array
     */
    protected function getTaskReport(TaskLogEntity $taskLogEntity)
    {
        return $this->getReportAsAssociativeArray($taskLogEntity->getReport());
    }

    /**
     * @return array
     */
    protected function addExtraReturnData(TaskLogEntity $taskLogEntity)
    {
        return [];
    }

    /**
     * @param Report $report
     * @return Report[]
     */
    protected function getPlainReport(Report $report)
    {
        $reports[] = $report;

        if ($report->hasChildren()) {
            foreach ($report as $r) {
                $reports = array_merge($reports, $this->getPlainReport($r));
            }
        }

        return $reports;
    }

    /**
     * @param Report $report
     * @return array
     */
    protected function getReportAsAssociativeArray(Report $report)
    {
        $reports = [];
        $plainReports = $this->getPlainReport($report);

        foreach ($plainReports as $r) {
            $reports[] = [
                'type'    => $r->getType(),
                'message' => $r->getMessage(),
            ];
        }

        return $reports;
    }

    /**
     * @param $statusCode
     * @return string
     */
    protected function taskStatusMapper($statusCode)
    {
        $status = TaskLogInterface::STATUS_UNKNOWN;
        switch ($statusCode) {
            case TaskLogCategorizedStatus::STATUS_IN_PROGRESS:
                $status = TaskLogInterface::STATUS_RUNNING;
                break;
            case TaskLogCategorizedStatus::STATUS_CREATED:
                $status = TaskLogInterface::STATUS_ENQUEUED;
                break;
            case TaskLogCategorizedStatus::STATUS_COMPLETED:
                $status = TaskLogInterface::STATUS_COMPLETED;
                break;
            case TaskLogCategorizedStatus::STATUS_FAILED:
                $status = TaskLogInterface::STATUS_FAILED;
                break;
        }
        return $status;
    }

    /**
     * @param TaskLogEntity $taskLogEntity
     * @return array
     * @throws \common_exception_NotFound
     */
    protected function getChildTasks(TaskLogEntity $taskLogEntity)
    {
        /** @var TaskLogInterface $taskLog */
        $taskLog = $this->getServiceManager()->get(TaskLogInterface::SERVICE_ID);

        $taskId = $taskLogEntity->getId();
        $filter = (new TaskLogFilter())
            ->eq(TaskLogBrokerInterface::COLUMN_PARENT_ID, $taskId);

        $collection = $taskLog->search($filter);
        $response = [];

        if ($collection->isEmpty()) {
            return $response;
        }

        /** @var TaskLogEntity $item */
        foreach ($collection as $item) {
            $response[] = [
                'id' => $this->getTaskId($item),
                'label' => $item->getLabel(),
                'status' => $this->getTaskStatus($item),
                'status_code' => $this->taskStatusMapper((string) $item->getStatus()),
                'report' => $item->getReport() ? $this->getTaskReport($item) : []
            ];
        }
        return $response;
    }
}