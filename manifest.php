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
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA;
 *
 *
 */

use oat\taoTaskQueue\scripts\install\RegisterTaskLogService;
use oat\taoTaskQueue\scripts\install\RegisterTaskQueueService;
use oat\taoTaskQueue\scripts\install\SetClientRouterConfig;

/**
 * Generated using taoDevTools 3.1.1
 */
return [
    'name' => 'taoTaskQueue',
    'label' => 'Task Queue',
    'description' => 'TAO specific Task Queue with custom GUI',
    'license' => 'GPL-2.0',
    'version' => '0.17.0',
    'author' => 'Open Assessment Technologies SA',
    'requires' => [
        'generis' => '>=5.8.0',
        'tao' => '>=14.10.0'
    ],
    'managementRole' => 'http://www.tao.lu/Ontologies/generis.rdf#taoTaskQueueManager',
    'acl'            => [
        ['grant', 'http://www.tao.lu/Ontologies/generis.rdf#taoTaskQueueManager', ['ext' => 'taoTaskQueue']],
    ],
    'install'        => [
        'php' => [
            RegisterTaskLogService::class,
            RegisterTaskQueueService::class,
            SetClientRouterConfig::class
        ]
    ],
    'uninstall'      => [
    ],
    'update'         => oat\taoTaskQueue\scripts\update\Updater::class,
    'routes'         => [
        '/taoTaskQueue' => 'oat\\taoTaskQueue\\controller'
    ],
    'constants'      => [
        # views directory
        "DIR_VIEWS" => dirname(__FILE__) . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR,

        #BASE URL (usually the domain root)
        'BASE_URL'  => ROOT_URL . 'taoTaskQueue/',
    ],
    'extra'          => [
        'structures' => dirname(__FILE__) . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . 'structures.xml',
    ],
];