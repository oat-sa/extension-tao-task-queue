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

use oat\taoTaskQueue\model\Entity\TaskLogEntityInterface;

/**
 * Interface TaskLogEntityInterface
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
abstract class TaskLogEntityDecorator implements TaskLogEntityInterface
{
    /**
     * @var TaskLogEntityInterface
     */
    private $entity;

    /**
     * @param TaskLogEntityInterface $entity
     */
    public function __construct(TaskLogEntityInterface $entity)
    {
        $this->entity = $entity;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * @inheritdoc
     */
    public function getParentId()
    {
        return $this->entity->getId();
    }

    /**
     * @inheritdoc
     */
    public function getTaskName()
    {
        return $this->entity->getTaskName();
    }

    /**
     * @inheritdoc
     */
    public function getParameters()
    {
        return $this->entity->getParameters();
    }

    /**
     * @inheritdoc
     */
    public function getLabel()
    {
        return $this->entity->getLabel();
    }

    /**
     * @inheritdoc
     */
    public function getOwner()
    {
        return $this->entity->getOwner();
    }

    /**
     * @inheritdoc
     */
    public function getReport()
    {
        return $this->entity->getReport();
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt()
    {
        return $this->entity->getCreatedAt();
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt()
    {
        return $this->entity->getUpdatedAt();
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        return $this->entity->getStatus();
    }

    /**
     * @inheritdoc
     */
    public function getFileNameFromReport()
    {
        return $this->entity->getFileNameFromReport();
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->entity->jsonSerialize();
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        return $this->entity->toArray();
    }
}