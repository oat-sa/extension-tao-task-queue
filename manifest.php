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

/**
 * Generated using taoDevTools 3.1.1
 */
return array(
    'name' => 'taoTaskQueue',
    'label' => 'Task Queue',
    'description' => 'TAO specific Task Queue with custom GUI',
    'license' => 'GPL-2.0',
    'version' => '0.1.1',
    'author' => 'Open Assessment Technologies SA',
    'requires' => array(
        'generis' => '>=4.4.3',
        'tao' => '>=13.1.2'
    ),
    'managementRole' => 'http://www.tao.lu/Ontologies/generis.rdf#taoTaskQueueManager',
    'acl' => array(
        array('grant', 'http://www.tao.lu/Ontologies/generis.rdf#taoTaskQueueManager', array('ext'=>'taoTaskQueue')),
    ),
    'install' => array(
        'php' => [
            RegisterTaskLogService::class,
            RegisterTaskQueueService::class
        ]
    ),
    'uninstall' => array(
    ),
    'update' => oat\taoTaskQueue\scripts\update\Updater::class,
    'routes' => array(
        '/taoTaskQueue' => 'oat\\taoTaskQueue\\controller'
    ),    
    'constants' => array(
        # views directory
        "DIR_VIEWS" => dirname(__FILE__).DIRECTORY_SEPARATOR."views".DIRECTORY_SEPARATOR,
        
        #BASE URL (usually the domain root)
        'BASE_URL' => ROOT_URL.'taoTaskQueue/',
    ),
    'extra' => array(
    )
);