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

/**
 * Allow to generate an absorbing animation from a target element to the component
 *
 * @example
 * component.absorb($target);//will create an animation
 * component.absorb($target).then(callback);//enables executing the callback after the animation sequence is over
 * component.absorbBurst($target, [0, 500, 1000]).then(callback);//creates 3 successive absorbing animation respectively at 0, 500 and 1000ms
 *
 * @author Sam <sam@taotesting.com>
 */
define([
    'jquery',
    'lodash',
    'i18n',
    'core/promise',
    'ui/report',
    'ui/feedback',
    'layout/loading-bar',
    'tpl!taoTaskQueue/component/button/tpl/report',
    'css!taoTaskQueue/component/button/css/taskable',
], function ($, _, __, Promise, reportFactory, feedback, loadingBar, reportTpl) {
    'use strict';

    var defaultConfig = {
        animationDuration: 1
    };

    var taskableComponent = {

        /**
         * Set configuration for task creation
         * @param config
         * @returns {taskableComponent}
         */
        setTaskConfig : function setTaskConfig(config){
            _.assign(this.config, config);
            return this;
        },

        /**
         * Create a task
         * @param requestUrl
         * @param requestData
         */
        createTask : function createTask(){
            var self = this;
            var taskQueue,
                requestUrl,
                requestData = {};

            //prepare the request parameter if applicable
            if(_.isFunction(this.config.taskCreationData)){
                requestData = this.config.taskCreationData.call(this);
            }else if(_.isPlainObject(this.config.taskCreationData)){
                requestData = this.config.taskCreationData;
            }

            if(!this.config.taskCreationtUrl){
                return this.trigger('error', 'the request url is required to create a task');
            }
            requestUrl = this.config.taskCreationtUrl;

            if(!this.config.taskQueue){
                return this.trigger('error', 'the taskQueue model is required to create a task');
            }
            taskQueue = this.config.taskQueue;

            loadingBar.start();
            taskQueue.pollAllStop();
            taskQueue.create(requestUrl, requestData).then(function (result) {
                var infoBox,
                    message,
                    task = result.task;

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
                    self.trigger('finished', result);
                } else {
                    //enqueuing process:
                    message = __('<strong> %s </strong> has been moved to the background.', task.taskLabel);
                    infoBox = feedback(null, {
                        encodeHtml : false,
                        timeout : {info: 8000}
                    }).info(message);

                    taskQueue.trigger('taskcreated', {task : task, sourceDom : infoBox.getElement()});
                    self.trigger('enqueued', result);
                }
                loadingBar.stop();
            }).catch(function (err) {
                //in case of error display it and continue task queue activity
                taskQueue.pollAll();
                self.trigger('error', err);
            });
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

            if(this.config.taskReportContainer instanceof $){
                $reportContainer = $(reportTpl({
                    title: title
                }));

                this.config.taskReportContainer.html($reportContainer);

                return reportFactory({
                        actions: [{
                            id: 'continue',
                            icon: 'right',
                            title: 'continue',
                            label: __('Continue')
                        }]
                    }, report)
                    .on('action-continue', function(){
                        self.trigger('continue', result);
                    }).render($reportContainer.find('.report'));
            }
        }
    };

    /**
     * @param {Component} component - an instance of ui/component
     * @param {Object} config
     */
    return function makeTaskable(component, config) {
        _.assign(component, taskableComponent);

        return component
            .off('.taskable')
            .on('init.taskable', function() {
                _.defaults(this.config, config || {}, defaultConfig);
            });
    };
});
