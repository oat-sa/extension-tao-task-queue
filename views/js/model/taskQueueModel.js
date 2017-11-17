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
    'core/promise',
    'core/eventifier',
    'core/polling',
    'core/dataProvider/request',
    'util/url',
    'jquery.fileDownload'
], function ($, _, Promise, eventifier, polling, request, urlHelper) {
    'use strict';

    var _defaults = {
        url : {
            get: urlHelper.route('get', 'RestTask', 'taoTaskQueue'),
            archive: urlHelper.route('archive', 'RestTask', 'taoTaskQueue'),
            all : urlHelper.route('getAll', 'RestTask', 'taoTaskQueue'),
            download : urlHelper.route('download', 'RestTask', 'taoTaskQueue'),
        },
        pollSingleIntervals : [
            {iteration: 10, interval:1000},
        ],
        pollAllIntervals : [
            {iteration: 10, interval:1000},
            {iteration: 10, interval:10000},
            {iteration: 10, interval:30000},
            {iteration: 0, interval:60000}
        ]
    };

    return function taskQueueModel(config) {

        var model;

        //store instance of single polling
        var singlePollings = {};

        var getPollSingleIntervals = function getPollSingleIntervals(){
            if(config.pollSingleIntervals && _.isArray(config.pollSingleIntervals)){
                return _.cloneDeep(config.pollSingleIntervals);
            }
        };

        var getPollAllIntervals = function getPollAllIntervals(){
            if(config.pollAllIntervals && _.isArray(config.pollAllIntervals)){
                return _.cloneDeep(config.pollAllIntervals);
            }
        };

        config = _.defaults(config || {}, _defaults);

        model = eventifier({

            setEndpoints : function setEndpoints(urls){
                _.assign(config.url, urls || {});
                return this;
            },

            /**
             * Get the status of a task identified by its unique task id
             *
             * @param {String} taskId - unique task identifier
             * @returns {Promise}
             */
            get : function get(taskId){
                var status;

                if(!config.url || !config.url.get){
                    throw new TypeError('config.url.get is not configured while get() is being called');
                }

                status = request(config.url.get, {taskId: taskId}, 'GET', {}, true)
                    .then(function(taskData){
                        //check taskData
                        if(taskData && taskData.status){
                            return Promise.resolve(taskData);
                        }
                        return Promise.reject(new Error('failed to get task data'));
                    });

                status.catch(function(err){
                    model.trigger('error', err);
                });

                return status;
            },

            /**
             * Get the status of all task identified by its unique task id
             *
             * @returns {Promise}
             */
            getAll : function getAll(){
                var status;

                if(!config.url || !config.url.all){
                    throw new TypeError('config.url.all is not configured while getAll() is being called');
                }

                status = request(config.url.all, {noLoadingBar:true}, 'GET', {}, true)
                    .then(function(taskData){
                        //check taskData
                        if(taskData){
                            return Promise.resolve(taskData);
                        }
                        return Promise.reject(new Error('failed to get all task data'));
                    });

                status.catch(function(err){
                    model.trigger('error', err);
                });

                return status;
            },

            /**
             * Remove a task identified by its unique task id
             *
             * @param {String} taskId - unique task identifier
             * @returns {Promise}
             */
            archive : function archive(taskId){

                var status;

                if(!config.url || !config.url.archive){
                    throw new TypeError('config.url.archive is not configured while archive() is being called');
                }

                status = request(config.url.archive, {taskId : taskId})
                    .then(function(){
                        return Promise.resolve();
                    });

                status.catch(function(res){
                    model.trigger('error', res);
                });

                return status;
            },

            /**
             * Poll status for all tasks
             */
            pollAll : function pollAll(){

                var self = this;
                var loop = 0;
                var pollingIntervals = getPollAllIntervals();

                /**
                 * gradually increase the polling interval to ease server load
                 * @private
                 * @param {Object} pollingInstance - a poll object
                 */
                var _updateInterval = function _updateInterval(pollingInstance){
                    var pollingInterval;
                    if(loop){
                        loop --;
                    }else{
                        pollingInterval = pollingIntervals.shift();
                        if(pollingInterval && pollingInterval.iteration && pollingInterval.interval){
                            loop = pollingInterval.iteration;
                            pollingInstance.setInterval(pollingInterval.interval);
                        }
                    }
                };

                if(!config.url || !config.url.all){
                    throw new TypeError('config.url.all is not configured while pollAll() is being called');
                }

                if(!this.globalPolling){
                    //no global polling yet, create one
                    this.globalPolling = polling({
                        action: function action() {
                            // get into asynchronous mode
                            var done = this.async();
                            model.getAll().then(function(taskDataArray){
                                //TODO compare if any task has changed in status ?
                                model.trigger('pollAll', taskDataArray);
                                _updateInterval(self.globalPolling);
                                done.resolve();
                            }).catch(function(){
                                done.reject();
                            });
                        }
                    });
                    _updateInterval(this.globalPolling);
                    this.globalPolling.start();
                    this.trigger('pollAllStart');
                }else{
                    this.globalPolling.start();
                    this.trigger('pollAllStart');
                }

                return model;
            },
            pollAllStop : function pollAllStop(){
                if(this.globalPolling){
                    this.globalPolling.stop();
                    this.trigger('pollAllStop');
                }
                return this;
            },
            pollSingle : function pollSingle(taskId){

                var self = this;
                var loop = 0;

                var pollingIntervals = getPollSingleIntervals();

                /**
                 * gradually increase the polling interval to ease server load
                 * @private
                 * @param {Object} pollingInstance - a poll object
                 */
                var _updateInterval = function _updateInterval(pollingInstance){
                    var pollingInterval;
                    if(loop){
                        loop --;
                        return true;//continue polling
                    }else{
                        pollingInterval = pollingIntervals.shift();
                        if(pollingInterval && pollingInterval.iteration && pollingInterval.interval){
                            loop = pollingInterval.iteration;
                            pollingInstance.setInterval(pollingInterval.interval);
                            return true;//continue polling
                        }else{
                            //stop polling
                            return false;
                        }
                    }
                };

                if(!config.url || !config.url.get){
                    throw new TypeError('config.url.get is not configured while pollSingle() is being called');
                }

                if(singlePollings[taskId]){
                    singlePollings[taskId].stop();
                }

                return new Promise(function(resolve){
                    var poll = polling({
                        action: function action() {
                            // get into asynchronous mode
                            var done = this.async();
                            self.get(taskId).then(function(taskData){
                                //debugger;
                                //console.log(taskData.status, taskData.status !== 'in_progress');
                                if(taskData.status !== 'in_progress'){
                                    //the status status could be either "completed" or "failed"
                                    poll.stop();
                                    self.trigger('pollSingleFinished', taskId, taskData);
                                    resolve({finished: true, task: taskData});
                                }else if(!_updateInterval(poll)){
                                    //if we have reached the end of the total polling config
                                    self.trigger('pollSingleFinished', taskId, taskData);
                                    resolve({finished: false, task: taskData});
                                }else{
                                    self.trigger('pollSingle', taskId, taskData);
                                    done.resolve();//go to next poll iteration
                                }

                            }).catch(function(){
                                done.reject();
                            });
                        }
                    });
                    _updateInterval(poll);
                    singlePollings[taskId] = poll.start();
                    self.trigger('pollSingleStart', taskId);
                });
            },
            pollSingleStop : function pollSingleStop(taskId){
                if(singlePollings && singlePollings[taskId]){
                    singlePollings[taskId].stop();
                    this.trigger('pollSingleStop', taskId);
                }
                return this;
            },
            create : function create(url, data){
                var self = this;
                var taskCreate = request(url, data)
                    .then(function(taskData){
                        //poll short result:
                        if(taskData && taskData.id){
                            self.trigger('created', taskData);
                            return self.pollSingle(taskData.id).then(function(result){
                                if(result.finished){
                                    //send to queue
                                    self.trigger('fastFinished', result.task);
                                }else{
                                    //send to queue
                                    self.trigger('enqueued', result.task);
                                }
                                return Promise.resolve(result);
                            });
                        }
                        return Promise.reject(new Error('failed to get task data'));
                    });

                taskCreate.catch(function(err){
                    model.trigger('error', err);
                });

                return taskCreate;
            },
            download : function download(taskId){

                if(!config.url || !config.url.download){
                    throw new TypeError('config.url.download is not configured while download() is being called');
                }

                $.fileDownload(config.url.download, {
                    httpMethod: 'POST',
                    data: {taskId : taskId},
                    failCallback: function (error) {
                        console.log(error);
                    }
                });
            }
        });

        return model;
    };
});