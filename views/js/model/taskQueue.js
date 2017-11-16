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
    'lodash',
    'core/promise',
    'core/eventifier',
    'core/polling',
    'core/dataProvider/request',
    'util/url',
], function (_, Promise, eventifier, polling, request, urlHelper) {
    'use strict';

    var pollManager = function pollManager(id, config){

    };

    var _defaults = {
        url : {
            status: urlHelper.route('get', 'RestTask', 'taoTaskQueue'),
            remove: urlHelper.route('archive', 'RestTask', 'taoTaskQueue'),
            all : urlHelper.route('getAll', 'RestTask', 'taoTaskQueue'),
        }
    };
    return function taskQueueModel(config) {

        var model;

        //store instance of single polling
        var singlePollings = {};


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

                status = request(config.url.get, {taskId: taskId})
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

                status = request(config.url.all, {})
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

                if(!config.url || !config.url.remove){
                    throw new TypeError('config.url.archive is not configured while archive() is being called');
                }

                status = request(config.url.remove, {taskId : taskId})
                    .then(function(taskData){
                        if(taskData && taskData.status === 'archived'){
                            return Promise.resolve(taskData);
                        }else{
                            return Promise.reject(new Error('removed task status should be archived'));
                        }
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
                var pollingIntervals = [
                    {iteration: 10, interval:1000},
                    {iteration: 10, interval:10000},
                    {iteration: 10, interval:30000},
                    {iteration: 0, interval:60000}
                ];

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

                var pollingIntervals = [
                    {iteration: 10, interval:1000},
                ];

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
                                if(taskData.status !== 'in_progress' || !_updateInterval(poll)){
                                    //the status status could be either "completed" or "failed"
                                    poll.stop();
                                    self.trigger('pollSingleFinished', taskId, taskData);
                                    resolve({finished: true, task: taskData});
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
            createTask : function createTask(url, data){
                var self = this;
                var taskCreate = request(url, data)
                    .then(function(taskData){

                        //poll short result:
                        if(taskData && taskData.taskId){
                            return self.pollSingle(taskData.taskId).then(function(result){
                                if(result.finished){
                                    //send to queue
                                    self.trigger('fastFinished', result.taskData);
                                }else{
                                    //send to queue
                                    self.trigger('enqueued', result.taskData);
                                }
                            });
                        }
                        return Promise.reject(new Error('failed to get task data'));
                    });

                taskCreate.catch(function(err){
                    model.trigger('error', err);
                });

                return this;
            }
        });

        return model;
    };

    //taskQueueInstance.createTask('url');
    //taskQueueInstance.archive('taskId');
});