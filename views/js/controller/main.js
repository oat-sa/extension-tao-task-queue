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
     * This controller initialize all the layout components used by the backend : sections, actions, tree, loader, etc.
     * @exports tao/controller/main
     */
    return {
        start: function () {

            var taskManager = taskQueueManagerFactory({
                replace: true
            })
                .on('render', function () {
                    var self = this;
                })
                .on('remove', function (taskId) {
                    taskQueue.archive(taskId);
                })
                .on('report', function (taskId) {
                    taskQueue.get(taskId).then(function (task) {
                        //show report in popup ???
                        console.log('show report', task);
                    });
                })
                .on('download', function (taskId) {
                    taskQueue.download(taskId);
                })
                .render($('#taskqueue').parent());


            taskQueue.on('pollAll', function (tasks) {
                taskManager.loadData(tasks);
            }).pollAll();

            return;

            taskQueueInstance = taskQueueModel()
                .on('completed failed archived', function () {
                    //update the view manager
                    taskManager.update(this.getAllData());
                }).on('enqueued', function (taskData) {
                    //update the view manager + animation
                    feedback('task created');
                    taskManager.animateInsertion(taskData);
                }).pollAll();//smart management of poll interval
        }
    };
});