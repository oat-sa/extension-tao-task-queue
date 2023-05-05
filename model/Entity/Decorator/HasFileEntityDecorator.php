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

namespace oat\taoTaskQueue\model\Entity\Decorator;

use oat\oatbox\filesystem\Directory;
use oat\oatbox\filesystem\FileSystemService;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\tao\model\taskQueue\TaskLog\Decorator\TaskLogEntityDecorator;
use oat\tao\model\taskQueue\TaskLog\Entity\EntityInterface;

/**
 * @deprecated Use \oat\tao\model\taskQueue\TaskLog\Decorator\HasFileEntityDecorator
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class HasFileEntityDecorator extends TaskLogEntityDecorator
{
    /**
     * @var FileSystemService
     */
    private $fileSystemService;

    public function __construct(EntityInterface $entity, FileSystemService $fileSystemService)
    {
        parent::__construct($entity);

        $this->fileSystemService = $fileSystemService;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Add 'hasFile' to the result. Required by our frontend.
     *
     * @return array
     */
    public function toArray()
    {
        $result = parent::toArray();

        $result['hasFile'] = false;

        if ($this->getFileNameFromReport()) {
            /** @var Directory $queueStorage */
            $queueStorage = $this->fileSystemService
                ->getDirectory(QueueDispatcherInterface::FILE_SYSTEM_ID);

            if ($queueStorage->getFile($this->getFileNameFromReport())->exists()) {
                $result['hasFile'] = true;
            }
        }

        return $result;
    }
}
