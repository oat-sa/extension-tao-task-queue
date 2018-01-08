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

namespace oat\taoTaskQueue\model\TaskLog;

use oat\generis\model\fileReference\FileSerializerException;
use oat\generis\model\fileReference\UrlFileSerializer;
use oat\oatbox\filesystem\Directory;
use oat\oatbox\filesystem\File;
use oat\oatbox\filesystem\FileSystemService;
use oat\taoTaskQueue\model\Entity\TaskLogEntityInterface;
use oat\taoTaskQueue\model\QueueDispatcherInterface;

/**
 * GeneratedFileLocator
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
class GeneratedFileLocator
{
    /**
     * @var TaskLogEntityInterface
     */
    private $entity;

    /**
     * @var UrlFileSerializer
     */
    private $serializer;
    /**
     * @var FileSystemService
     */
    private $fileSystemService;

    public function __construct(TaskLogEntityInterface $entity, FileSystemService $fileSystemService, UrlFileSerializer $serializer)
    {
        $this->entity = $entity;
        $this->serializer = $serializer;
        $this->fileSystemService = $fileSystemService;
    }

    /**
     * @return null|File
     * @throws FileSerializerException
     */
    public function getFile()
    {
        $file = null;

        if ($fileName = $this->entity->getFileNameFromReport()) {

            if (filter_var($fileName, FILTER_VALIDATE_URL)) {
                $file = $this->serializer->unserialize($fileName);
            } else {
                // using the default storage
                /** @var Directory $queueStorage */
                $queueStorage = $this->fileSystemService
                    ->getDirectory(QueueDispatcherInterface::FILE_SYSTEM_ID);

                $file = $queueStorage->getFile($fileName);
            }
        }

        return $file instanceof File ? $file : null;
    }
}