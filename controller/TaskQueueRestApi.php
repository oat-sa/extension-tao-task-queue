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

namespace oat\taoTaskQueue\controller;

use oat\taoTaskQueue\model\TaskLogInterface;

/**
 * RestAPI controller to get data from task queue
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class TaskQueueRestApi extends \tao_actions_RestController
{
    const PARAMETER_TASK_ID = 'id';

    /**
     * Get task details by its id, independently from the owner.
     */
    public function get()
    {
        try {
            if (!$this->hasRequestParameter(self::PARAMETER_TASK_ID)) {
                throw new \common_exception_MissingParameter(self::PARAMETER_TASK_ID, $this->getRequestURI());
            }

            /** @var TaskLogInterface $taskLogService */
            $taskLogService = $this->getServiceManager()->get(TaskLogInterface::SERVICE_ID);

            $entity = $taskLogService->getById((string) $this->getRequestParameter(self::PARAMETER_TASK_ID));

            $this->returnSuccess($entity->toArray());
        } catch (\Exception $e) {
            $this->returnFailure($e);
        }
    }
}