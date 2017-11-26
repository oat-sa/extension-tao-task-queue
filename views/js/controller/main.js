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
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA ;
 */

/**
 * @author Sam <sam@taotesting.com>
 */
define([
    'jquery',
    'taoTaskQueue/component/manager/manager',
    'taoTaskQueue/model/taskQueue'
],
function ($, taskQueueManagerFactory, taskQueue) {
    'use strict';

    /**
     * This controller initialize all the task queue component globally for tao backoffice use
     * @exports taoTaskQueue/controller/main
     */
    return {
        start: function () {

            var taskManager = taskQueueManagerFactory({
                    replace: true
                })
                .on('remove', function (taskId) {
                    taskQueue.archive(taskId);
                })
                .on('report', function (taskId) {
                    taskQueue.get(taskId).then(function (task) {
                        //show report in popup
                        taskManager.showDetail(task);
                    });
                })
                .on('download', function (taskId) {
                    taskQueue.download(taskId);
                })
                .render($('#taskqueue').parent())
                .hide();//start hidden to prevent blinking effect

            //listen to events triggered by the task queue model
            taskQueue.on('taskcreated', function(task){
                if(taskManager.list.is('hidden')){
                    taskManager.animateAbsorption().then(function(){
                        taskManager.addNewTask(task);
                    });
                }else{
                    taskManager.addNewTask(task, true);
                }
            }).on('multitaskstatuschange', function(){
                taskManager.animatePulse();
            }).on('pollAll', function (tasks) {
                if(taskManager.is('hidden')){
                    taskManager.show();
                }
                taskManager.loadData(tasks);
            }).pollAll(true);//start polling immediately on load
        }
    };
});