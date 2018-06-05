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
 */

namespace oat\taoTaskQueue\test\scripts\tools\mock;

use oat\oatbox\extension\script\ScriptAction;
use common_report_Report as Report;

/**
 * Run example:
 *
 * ```
 * sudo -u www-data php index.php 'oat\taoTaskQueue\test\scripts\tools\mock\ScriptActionMock'
 * ```
 */
class ScriptActionMock extends ScriptAction
{
    private static $params;

    protected function provideOptions()
    {
        return [
            'param1' => [
                'prefix' => 'p1',
                'longPrefix' => 'param1',
                'required' => true,
                'description' => 'First parameter'
            ],
            'param2' => [
                'prefix' => 'p2',
                'longPrefix' => 'param2',
                'required' => false,
                'description' => 'Second parameter'
            ],
        ];
    }

    protected function provideDescription()
    {
        return 'Mock script action for unit tests';
    }

    protected function provideUsage()
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
        ];
    }

    protected function run()
    {
        self::$params = [
            '--param1', $this->getOption('param1'),
            '--param2', $this->getOption('param2'),
        ];
        return Report::createSuccess('Mock task finished with params: ' . json_encode(self::$params));
    }

    public static function getParams()
    {
        return self::$params;
    }
}
