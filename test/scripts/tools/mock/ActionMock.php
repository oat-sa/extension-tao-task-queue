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
 * Copyright (c) 2018 (original work) Open Assessment Technologies SA;
 *
 */

namespace oat\taoTaskQueue\test\scripts\tools\mock;

use oat\oatbox\action\Action;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use common_report_Report as Report;

class ActionMock implements Action, ServiceLocatorAwareInterface
{

    use ServiceLocatorAwareTrait;

    private static $params;

    /**
     * @param array $params
     * @return array
     */
    public function __invoke($params)
    {
        self::$params = $params;
        return Report::createSuccess('Mock task finished with params: ' . json_encode(self::$params));
    }

    public static function getParams()
    {
        return self::$params;
    }
}
