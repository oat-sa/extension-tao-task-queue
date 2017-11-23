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
define([
    'jquery',
    'lodash',
    'i18n',
    'ui/feedback',
    'ui/report',
    'taoTaskQueue/model/taskQueue',
    'layout/loading-bar',
    'tpl!taoTaskQueue/creator/tpl/reportContainer'
], function($, _, __, feedback, reportFactory, taskQueue, loadingBar, reportContainerTpl){
    'use strict';

    /**
     * prepare the given container to display the final report
     * @param {Object} report - the standard report object
     * @param {JQuery} $container - the container that will contain the report
     */
    function displayReport(report, $container){
        var $reportContainer = $(reportContainerTpl({
            title: report.type === 'error' ? __('Error') : __('Success')
        }));
        $container.html($reportContainer);
        reportFactory({}, report).render($reportContainer.find('.report'));
    }

    /**
     * Manage task creation from a standard backoffice form and handle results in a consistent way
     * (this is but an early attempt to standardize task queue creation process)
     */
    return function formTaskCreator($form, $container){
        $form.on('submit', function(e){
            e.preventDefault();
            e.stopImmediatePropagation();
            loadingBar.start();

            //pause polling all status during creation process to prevent concurrency issue
            taskQueue.pollAllStop();
            taskQueue.create($form.prop('action'), $form.serializeArray()).then(function(result){
                var task = result.task;
                if(result.finished){
                    //the task finished quickly -> display report
                    displayReport(task.report.children[0], $container);

                    //immediately archive the finished task as there is no need to display this task in the queue list
                    taskQueue.archive(task.id).then(function(){
                        taskQueue.pollAll();
                    });
                }else{
                    //inform the user that task will move to the background
                    displayReport({
                        type: 'info',
                        message : __('<strong> %s </strong> takes a long time to execute so it has been moved to the background.', task.taskLabel)
                    }, $container);

                    //leave the user a moment to make the connection between the notification message and the animation
                    _.delay(function(){
                        taskQueue.trigger('taskcreated', task);
                        taskQueue.pollAll(true);
                    }, 1000);
                }
                loadingBar.stop();
            }).catch(function(err){
                taskQueue.pollAll();
                feedback().error(err);
            });
        });
    };
});