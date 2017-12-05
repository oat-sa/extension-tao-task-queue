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

namespace oat\taoTaskQueue\model\Entity;

use common_report_Report as Report;
use oat\taoTaskQueue\model\ValueObjects\TaskLogCategorizedStatus;

/**
 * Interface TaskLogEntityInterface
 *
 * @author Gyula Szucs <gyula@taotesting.com>
 */
interface TaskLogEntityInterface extends \JsonSerializable
{
    /**
     * @return string
     */
    public function getId();

    /**
     * @return string
     */
    public function getParent();

    /**
     * @return string
     */
    public function getTaskName();

    /**
     * @return array
     */
    public function getParameters();

    /**
     * @return string
     */
    public function getLabel();

    /**
     * @return string
     */
    public function getOwner();

    /**
     * @return Report|null
     */
    public function getReport();

    /**
     * @return \DateTime|null
     */
    public function getCreatedAt();

    /**
     * @return \DateTime|null
     */
    public function getUpdatedAt();

    /**
     * @return TaskLogCategorizedStatus
     */
    public function getStatus();

    /**
     * @return string
     */
    public function getFileNameFromReport();

    /**
     * @return array
     */
    public function toArray();
}