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
 * Copyright (c) 2017-2019 (original work) Open Assessment Technologies SA ;
 */

/**
 * @author Sam <sam@taotesting.com>
 */
define([
    'jquery',
    'taoTaskQueue/component/manager/manager',
    'ui/taskQueue/taskQueue'
],
function ($, taskQueueManagerFactory, taskQueue) {
    'use strict';

    /**
     * This controller initialize all the task queue component globally for tao backoffice use
     * @exports taoTaskQueue/controller/main
     */
    return {
        start() {

            const taskManager = taskQueueManagerFactory({
                replace: true
            })
            .on('remove', taskId => taskQueue.archive(taskId) )
            .on('report', taskId => {
                //show report in popup
                taskQueue.get(taskId).then( task => taskManager.showDetail(task) );
            })
            .on('download', taskId => taskQueue.download(taskId) )
            .on('redirect', taskId => taskQueue.redirect(taskId) )
            .on('listclearfinished', () => {
                taskQueue
                    .pollAllStop()
                    .archive('all')
                    .then( () => taskQueue.pollAll() );
            })
            .render($('#taskqueue').parent())
            .hide();//start hidden to prevent blinking effect

            //listen to events started by the task queue model
            taskQueue
                .on('taskcreated', data => {
                    if (taskManager.list.is('hidden')) {
                        taskManager.absorbBurst(data.sourceDom, [0, 300, 600]).then( () => {
                            taskManager.addNewTask(data.task);
                            taskQueue.pollAll();
                        });
                    } else {
                        taskManager.addNewTask(data.task, true);
                        taskQueue.pollAll();
                    }
                })
                .on('multitaskstatuschange', () =>  taskManager.pulse() )
                .on('pollAll', tasks => {
                    if (taskManager.is('hidden')) {
                        taskManager.show();
                    }
                    taskManager.loadData(tasks);
                })
                .pollAll(true);//start polling immediately on load
        }
    };
});
