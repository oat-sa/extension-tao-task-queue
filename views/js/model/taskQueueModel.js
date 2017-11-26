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
    'jquery.fileDownload'
], function ($, _, Promise, eventifier, polling, request) {
    'use strict';

    var _defaults = {
        url : {
            get: '',
            archive: '',
            all : '',
            download : ''
        },
        pollSingleIntervals : [
            {iteration: 4, interval:1000},
        ],
        pollAllIntervals : [
            {iteration: 10, interval:5000},
            {iteration: 0, interval:10000}//infinite
        ]
    };

    function hasSameState(task1, task2){
        if(task1.status === task2.status){
            return true;
        }else if(task1.status === 'created' || task1.status === 'in_progress'){
            return  (task2.status === 'created' || task2.status === 'in_progress');
        }
        return false;
    }

    return function taskQueueModel(config) {

        var model;
        /**
         * cached array of task data
         * @type {Object}
         */
        var _cache;

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
                            if(_cache){
                                //detect change
                                if(!_cache[taskData.id]){
                                    model.trigger('singletaskadded', taskData);
                                }else if(!hasSameState(_cache[taskData.id], taskData)){
                                    //check if the status has changed
                                    model.trigger('singletaskstatuschange', taskData);
                                }
                            }
                            _cache[taskData.id] = taskData;
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

                status = request(config.url.all, {limit: 100}, 'GET', {}, true)
                    .then(function(taskData){
                        var newCache = {};
                        //check taskData
                        if(taskData){
                            if(_cache){
                                //detect change
                                _.forEach(taskData, function(task){
                                    var id = task.id;
                                    if(!_cache[id]){
                                        model.trigger('multitaskadded', task);
                                    }else if(!hasSameState(_cache[id], task)){
                                        //check if the status has changed
                                        model.trigger('multitaskstatuschange', task);
                                    }
                                    newCache[id] = task;
                                });
                                _.forEach(_.difference(_.keys(_cache), _.keys(newCache)), function(id){
                                    model.trigger('taskremoved', _cache[id]);
                                });
                            }else{
                                _.forEach(taskData, function(task){
                                    newCache[task.id] = task;
                                });
                            }
                            //update local cache
                            _cache = newCache;

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

                status = request(config.url.archive, {taskId : taskId}, 'GET', {}, true)
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

            /**
             * Poll status for all tasks
             * @param {Boolean} [immediate] - tells if the polling should immediately start (otherwise, will wait until the next iteration)
             * @returns {*}
             */
            pollAll : function pollAll(immediate){

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
                        if(pollingInterval && typeof pollingInterval.iteration !== 'undefined' && pollingInterval.interval){
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

                if(immediate){
                    //if it is request to immediate start polling, start it now
                    this.globalPolling.next();
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
                                if(taskData.status === 'completed' || taskData.status === 'failed'){
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
                var taskCreate, self = this;
                taskCreate = request(url, data, 'POST', {}, true)
                    .then(function(creationResult){
                        //poll short result:
                        if(creationResult && creationResult.task && creationResult.task.id){
                            self.trigger('created', creationResult);
                            return self.pollSingle(creationResult.task.id).then(function(result){
                                if(creationResult.extra){
                                    result.extra = creationResult.extra;
                                }
                                if(result.finished){
                                    //send to queue
                                    self.trigger('fastFinished', result);
                                }else{
                                    //send to queue
                                    self.trigger('enqueued', result);
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
                    failCallback: function (err) {
                        model.trigger('error', err);
                    }
                });
            }
        });

        return model;
    };
});