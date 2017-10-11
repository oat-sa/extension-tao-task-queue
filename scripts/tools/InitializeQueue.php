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

namespace oat\taoTaskQueue\scripts\tools;

use oat\oatbox\action\Action;
use oat\taoTaskQueue\model\QueueDispatcherInterface;
use oat\taoTaskQueue\model\TaskLogInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

/**
 * Initialize Queue:
 * - create the queue if not set
 * - create the task log container if not set
 *
 * ```
 * $ sudo -u www-data php index.php 'oat\taoTaskQueue\scripts\tools\InitializeQueue'
 * ```
 */
class InitializeQueue implements Action, ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    public function __invoke($params)
    {
        try {
            // Create the queues
            /** @var QueueDispatcherInterface $queueService */
            $queueService = $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);

            if (!$queueService->isSync()) {
                $queueService->initialize();
            }

            // Create task log container
            /** @var TaskLogInterface $taskLog */
            $taskLog = $this->getServiceLocator()->get(TaskLogInterface::SERVICE_ID);
            $taskLog->createContainer();

            return \common_report_Report::createSuccess('Initialization successful');
        } catch (\Exception $e) {
            return \common_report_Report::createFailure($e->getMessage());
        }
    }
}

