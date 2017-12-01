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

namespace oat\taoTaskQueue\model\TaskLog\Decorator;

use oat\taoTaskQueue\model\Entity\Decorator\CategoryEntityDecorator;
use oat\taoTaskQueue\model\TaskLog\TaskLogCollectionInterface;
use oat\taoTaskQueue\model\TaskLogBroker\TaskLogBrokerInterface;
use oat\taoTaskQueue\model\TaskLogInterface;

/**
 * CategoryCollectionDecorator
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class CategoryCollectionDecorator extends TaskLogCollectionDecorator
{
    /**
     * @var TaskLogInterface
     */
    private $taskLogService;

    /**
     * CategoryCollectionDecorator constructor.
     *
     * @param TaskLogCollectionInterface $collection
     * @param TaskLogInterface           $taskLogService
     */
    public function __construct(TaskLogCollectionInterface $collection, TaskLogInterface $taskLogService)
    {
        parent::__construct($collection);

        $this->taskLogService = $taskLogService;
    }

    /**
     * Use CategoryEntityDecorator on each entity to add category to the result.
     *
     * @return array
     */
    public function toArray()
    {
        $data = parent::toArray();

        foreach ($data as &$row) {
            $row['category'] = $this->taskLogService->getCategoryForTask($row['taskName']);
        }

        return $data;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}