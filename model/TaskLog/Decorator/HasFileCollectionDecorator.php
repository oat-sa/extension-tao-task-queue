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

use oat\oatbox\filesystem\FileSystemService;
use oat\taoTaskQueue\model\Entity\Decorator\HasFileEntityDecorator;
use oat\taoTaskQueue\model\TaskLog\TaskLogCollectionInterface;

/**
 * HasFileCollectionDecorator
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class HasFileCollectionDecorator extends TaskLogCollectionDecorator
{
    /**
     * @var FileSystemService
     */
    private $fileSystemService;

    public function __construct(TaskLogCollectionInterface $collection, FileSystemService $fileSystemService)
    {
        parent::__construct($collection);

        $this->fileSystemService = $fileSystemService;
    }

    /**
     * CAUTION: Parent not used, so this decorator needs to be the first one in the queue.
     *
     * @return array
     */
    public function toArray()
    {
        $data = [];

        foreach ($this->getIterator() as $entity) {
            $data[] = (new HasFileEntityDecorator($entity, $this->fileSystemService))->toArray();
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