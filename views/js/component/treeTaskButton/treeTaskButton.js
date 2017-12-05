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
 * A button component used to trigger lengthy action.
 * It has its own lifecycle: render -> started -> terminated [-> reset]
 *
 * @example
 * loadingButtonFactory({
 *          type : 'info',
 *          icon : 'property-advanced',
 *          title : 'Execute my script',
 *          label : 'Run',
 *          terminatedLabel : 'Terminated'
 *     });
 *
 * @author Sam <sam@taotesting.com>
 */
define([
    'jquery',
    'lodash',
    'i18n',
    'ui/feedback',
    'ui/component',
    'layout/loading-bar',
    'tpl!taoTaskQueue/component/treeTaskButton/tpl/button',
    'css!taoTaskQueue/component/treeTaskButton/css/button'
], function ($, _, __, feedback, component, loadingBar, buttonTpl) {
    'use strict';

    var _defaults = {
        icon : 'property-advanced',
        label : 'OK'
    };

    var buttonApi = {
        setConfig : function setConfig(config){
            _.assign(this.config, config);
            return this;
        },
        start : function start(){

            var requestData = {};
            //prepare the request parameter if applicable
            if(_.isFunction(this.config.data)){
                requestData = this.config.data.call(this);
            }else if(_.isPlainObject(this.config.data)){
                requestData = this.config.data;
            }

            if(!this.config.requestUrl){
                return this.trigger('error', 'the request url is required to create a task');
            }

            if(!this.config.taskQueue){
                return this.trigger('error', 'the taskQueue model is required to create a task');
            }
            this.createTask(this.config.taskQueue, this.config.requestUrl, requestData);

            this.setState('started', true);
            this.trigger('start');
        },
        stop : function reset(){
            if(this.is('started')){
                this.setState('started', false);
                this.trigger('stop');
            }
            return this;
        },

        /**
         *
         * @param requestUrl
         * @param requestData
         */
        createTask : function createTask(taskQueue, requestUrl, requestData){
            var self = this;

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
                                self.stop();
                            });
                        });
                    }else{
                        //immediately archive the finished task as there is no need to display this task in the queue list
                        taskQueue.archive(task.id).then(function () {
                            self.trigger('finished', result);
                            taskQueue.pollAll();
                            self.stop();
                        });
                    }
                } else {

                    feedback().info(__('%s takes a long time so has been moved to the background. You can continue working elsewhere.', task.taskLabel));

                    self.stop();

                    //leave the user a moment to make the connection between the notification message and the animation
                    taskQueue.trigger('taskcreated', {task : task, sourceDom : self.config.sourceElement || self.getElement()});
                }
                loadingBar.stop();
            }).catch(function (err) {
                //in case of error display it and continue task queue activity
                taskQueue.pollAll();
                self.trigger('error', err);
            });
        },
    };

    /**
     * Create a button with the lifecycle : render -> started -> terminated [-> reset]
     * @param {Object} config - the component config
     * @param {String} config.type - the icon type (info, success, error)
     * @param {String} config.icon - the button icon
     * @param {String} config.title - the button's title
     * @param {String} config.label - the button's label
     * @param {String} config.terminatedLabel - the button's label when terminated
     * @return {loadingButton} the component
     *
     * @event started - Emitted when the button is clicked and the triggered action supposed to be started
     * @event terminated - Emitted when the button action is stopped, interrupted
     * @event reset - Emitted when the button revert from the terminated stated to the initial one
     */
    return function treeTaskButtonFactory(config) {
        var initConfig = _.defaults(config || {}, _defaults);

        /**
         * @typedef {loadingButton} the component
         */
        return component(buttonApi)
            .setTemplate(buttonTpl)
            .on('render', function() {
                return;
                var self = this;
                this.getElement().on('click', function(){
                    if(!self.is('disabled') && !self.is('started')){
                        self.start();
                    }
                });
            })
            .init(initConfig);
    };

});