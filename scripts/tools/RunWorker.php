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
use oat\taoTaskQueue\model\TaskLog;
use oat\taoTaskQueue\model\Queue;
use oat\taoTaskQueue\model\TaskLogInterface;
use oat\taoTaskQueue\model\QueueInterface;
use oat\taoTaskQueue\model\Worker;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

/**
 * Start a new worker.
 *
 * ```
 * $ sudo -u www-data php index.php 'oat\oatbox\TaskQueue\Action\RunWorker'
 * $ sudo -u www-data php index.php 'oat\oatbox\TaskQueue\Action\RunWorker' 10
 * ```
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class RunWorker implements Action, ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    public function __invoke($params)
    {
        $limit = isset($params[0]) ? (int) $params[0] : 0;

        /** @var QueueInterface $queue */
        $queue = $this->getServiceLocator()->get(Queue::SERVICE_ID);

        if ($queue->isSync()) {
            return \common_report_Report::createInfo('No worker needed because Sync Queue is used.');
        }

        /** @var TaskLogInterface $messageLogManager */
        $messageLogManager = $this->getServiceLocator()->get(TaskLog::SERVICE_ID);

        (new Worker($queue, $messageLogManager))
            ->setMaxIterations($limit)
            ->processQueue();

        return \common_report_Report::createSuccess('Worker finished');
    }
}

