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
 */

define([
    'jquery',
    'lodash',
    'i18n',
    'core/promise',
    'ui/report',
    'layout/loading-bar',
    'ui/loadingButton/loadingButton',
    'tpl!taoTaskQueue/component/taskCreationButton/tpl/report',
    'tpl!taoTaskQueue/component/taskCreationButton/tpl/feedback',
    'tpl!taoTaskQueue/component/taskCreationButton/tpl/overlay',
    'css!taoTaskQueue/component/taskCreationButton/css/style'
], function ($, _, __, Promise, reportFactory, loadingBar, loadingButton, reportTpl, feedbackTpl, overlayTpl) {
    'use strict';

    var defaultConfig = {
    };

    var taskCreationButtonComponent = {
        /**
         *
         * @param requestUrl
         * @param requestData
         */
        createTask : function createTask(taskQueue, requestUrl, requestData){
            var self = this;
            var $container = $(this.config.reportContainer);

            loadingBar.start();
            taskQueue.pollAllStop();
            taskQueue.create(requestUrl, requestData).then(function (result) {
                var task = result.task;

                loadingBar.stop();

                if (result.finished) {
                    if(task.hasFile){
                        //download if its is a export-typed task
                        taskQueue.download(task.id).then(function(){
                            //immediately archive the finished task as there is no need to display this task in the queue list
                            taskQueue.archive(task.id).then(function () {
                                self.trigger('finished', result);
                                taskQueue.pollAll();
                            });
                        });
                    }else{
                        //immediately archive the finished task as there is no need to display this task in the queue list
                        taskQueue.archive(task.id).then(function () {
                            self.trigger('finished', result);
                            taskQueue.pollAll();
                        });
                    }
                } else {
                    //prevent further interactions and inform the user that task will move to the background and
                    $container
                        .css('position', 'relative')
                        .append(overlayTpl());

                    self
                        .terminate()
                        .hide()
                        .getElement().after(feedbackTpl({
                        type : 'info',
                        message : __('<strong> %s </strong> takes a long time so it has been moved to the background. You can continue working elsewhere.', task.taskLabel)
                    }));

                    //leave the user a moment to make the connection between the notification message and the animation
                    taskQueue.trigger('taskcreated', {task : task, sourceDom : self.config.sourceElement || $(document)});
                }
                loadingBar.stop();
            }).catch(function (err) {
                //in case of error display it and continue task queue activity
                taskQueue.pollAll();
                self.trigger('error', err);
            });
        },

        /**
         * Restore the button to its state before the task creation
         * @returns {taskCreationButton}
         */
        restoreButton : function restoreButton(){
            if(this.is('terminated')){
                this.reset()
                    .show()
                    .getElement().siblings('.task-creation-feedback').remove();
            }
            return this;
        },

        /**
         * prepare the given container to display the final report
         * @param {Object} report - the standard report object
         * @param {String} title - the report title
         * @param {String} result - raw result data from the task creation action
         */
        displayReport : function displayReport(report, title, result) {
            var self = this,
                $reportContainer;

            if(this.config.reportContainer instanceof $){
                $reportContainer = $(reportTpl({
                    title: title
                }));

                this.config.reportContainer.html($reportContainer);

                return reportFactory({
                        actions: [{
                            id: 'continue',
                            icon: 'right',
                            title: 'continue',
                            label: __('Continue')
                        }]
                    }, report)
                    .on('action-continue', function () {
                        self.trigger('continue', result);
                    }).render($reportContainer.find('.report'));
            }
        }
    };

    /**
     * Builds the task queue manager
     * @param {Object} config - the component config
     * @param {Array} data - the initial task data to be loaded from the server REST api call
     * @returns {taskQueueManager} the component
     *
     * @event remove - Emitted when a list element is removed
     * @event download - Emitted when a list element requests the file download associated to a completed task
     * @event report - Emitted when a list element requests a task report to be displayed
     */
    return function taskCreationButtonFactory(config) {

        var component;

        //prepare the config and create the base loading button
        config = _.defaults(config || {}, defaultConfig);
        component = loadingButton(config);
        _.assign(component, taskCreationButtonComponent);

        /**
         * The component
         * @typedef {ui/component} taskCreationButton
         */
        return component.on('started', function(){

            var data = {};
            //prepare the request parameter if applicable
            if(_.isFunction(this.config.getRequestData)){
                data = this.config.getRequestData.call(this);
            }

            if(!this.config.requestUrl){
                return this.trigger('error', 'the request url is required to create a task');
            }

            if(!this.config.taskQueue){
                return this.trigger('error', 'the taskQueue model is required to create a task');
            }

            this.createTask(this.config.taskQueue, this.config.requestUrl, data);
        });
    };
});
