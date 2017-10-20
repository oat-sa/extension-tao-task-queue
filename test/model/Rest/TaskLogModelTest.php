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

namespace oat\taoTaskQueue\test\model\Rest;

use oat\taoTaskQueue\model\Entity\TaskLogEntity;
use oat\taoTaskQueue\model\Entity\TasksLogsStats;
use oat\taoTaskQueue\model\Rest\TaskLogModel;
use oat\taoTaskQueue\model\TaskLogBroker\TaskLogCollection;
use oat\taoTaskQueue\model\TaskLogInterface;

class TaskLogModelTest extends \PHPUnit_Framework_TestCase
{
    public function testFindAvailableByUser()
    {
        $model = $this->getModelMocked();

        $this->assertInstanceOf(TaskLogCollection::class, $model->findAvailableByUser('userId'));
    }

    public function testGetByIdAndUser()
    {
        $model = $this->getModelMocked();

        $this->assertInstanceOf(TaskLogEntity::class, $model->getByIdAndUser('taskId', 'userId'));
    }

    /**
     * @expectedException  \common_exception_NotFound
     */
    public function testGetByIdAndUserNotFound()
    {
        $model = $this->getModelMocked(true);

        $this->assertInstanceOf(TaskLogEntity::class, $model->getByIdAndUser('some task id not found', 'userId'));
    }

    public function testGetStats()
    {
        $model = $this->getModelMocked();

        $this->assertInstanceOf(TasksLogsStats::class, $model->getStats('userId'));
    }

    public function testDelete()
    {
        $model = $this->getModelMocked();

        $this->assertTrue($model->delete('taskId' ,'userId'));
    }

    /**
     * @expectedException  \common_exception_NotFound
     */
    public function testDeleteTaskNotFound()
    {
        $model = $this->getModelMocked(true);

        $this->assertTrue($model->delete('taskId' ,'userId'));
    }

    /**
     * @expectedException  \Exception
     */
    public function testDeleteNotPossibleTaskIsRunning()
    {
        $model = $this->getModelMocked(false, false, true);

        $this->assertTrue($model->delete('taskId' ,'userId'));
    }

    protected function getModelMocked($notFound = false, $shouldArchive = true, $taskRunning = false)
    {
        $repositoryMock = $this->getMock(TaskLogInterface::class);
        $collectionMock = $this->getMockBuilder(TaskLogCollection::class)->disableOriginalConstructor()->getMock();
        $entity         = $this->getMockBuilder(TaskLogEntity::class)->disableOriginalConstructor()->getMock();

        $repositoryMock
            ->method('findAvailableByUser')
            ->willReturn($collectionMock);

        if ($taskRunning) {
            $repositoryMock
                ->method('getByIdAndUser')
                ->willThrowException(new \Exception());
        }else {
            $repositoryMock
                ->method('archive')
                ->willReturn($shouldArchive);
        }

        if ($notFound) {
            $repositoryMock
                ->method('getByIdAndUser')
                ->willThrowException(new \common_exception_NotFound());
        } else {
            $repositoryMock
                ->method('getByIdAndUser')
                ->willReturn($entity);
        }

        return new TaskLogModel($repositoryMock);
    }
}
