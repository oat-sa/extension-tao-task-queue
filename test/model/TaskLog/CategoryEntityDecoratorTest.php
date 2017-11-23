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

namespace oat\taoTaskQueue\test\model\TaskLog;

use oat\taoTaskQueue\model\Entity\TaskLogEntityInterface;
use oat\taoTaskQueue\model\TaskLog;
use oat\taoTaskQueue\model\Entity\CategoryEntityDecorator;

class CategoryEntityDecoratorTest extends \PHPUnit_Framework_TestCase
{
    public function testToArrayCategoryShouldBeInTheResult()
    {
        $category = 'fakeCategory';

        $taskLogMock = $this->getMockBuilder(TaskLog::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCategoryForTask'])
            ->getMock();

        $taskLogMock->expects($this->once())
            ->method('getCategoryForTask')
            ->willReturn($category);

        $entityMock = $this->getMockForAbstractClass(TaskLogEntityInterface::class);

        $decorator = new CategoryEntityDecorator($entityMock, $taskLogMock);

        $rs = $decorator->toArray();

        $this->assertArrayHasKey('category', $rs);
        $this->assertEquals($category, $rs['category']);
    }
}