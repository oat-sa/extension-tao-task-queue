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
    'ui/hider',
    'ui/component',
    'ui/badge/badge',
    'ui/component/alignable',
    'ui/animable/absorbable/absorbable',
    'ui/animable/pulsable/pulsable',
    'taoTaskQueue/component/listing/element',
    'taoTaskQueue/component/listing/report',
    'taoTaskQueue/component/listing/list',
    'tpl!taoTaskQueue/component/manager/tpl/manager',
    'css!taoTaskQueue/component/manager/css/manager'
], function ($, _, __, hider, component, badgeFactory, makeAlignable, makeAbsorbable, makePulsable, listElementFactory, reportElementFactory, taskListFactory, managerTpl) {
    'use strict';

    /**
     * Transform the task log summary into a configuration set for the badge
     * @param {Object} tasksStatuses - the task log summary
     * @returns {Object} the new badge data to be displayed following the format {type, loading, value}
     */
    var getBadgeDataFromStatus = function getBadgeDataFromStatus(tasksStatuses){
        var total =  0;
        var data = {loading : false};
        if(tasksStatuses){
            if(tasksStatuses.numberOfTasksInProgress){
                total += parseInt(tasksStatuses.numberOfTasksInProgress, 10);
                data.type = 'info';
                data.loading = (tasksStatuses.numberOfTasksInProgress > 0);
            }
            if(tasksStatuses.numberOfTasksCompleted){
                total += parseInt(tasksStatuses.numberOfTasksCompleted, 10);
                data.type = 'success';
            }
            if(tasksStatuses.numberOfTasksFailed){
                total += parseInt(tasksStatuses.numberOfTasksFailed, 10);
                if(data.type === 'success'){
                    data.type = 'warning';//if there are both success and failures, the status should be a warning
                }else{
                    data.type = 'error';
                }
            }
            data.value = total;
            return data;
        }
    };

    /**
     * Transform the internal list of elements into a configuration set for the badge
     * @param {Object} elements - internal collection of task elements
     * @returns {Object} the new badge data to be displayed following the format {type, loading, value}
     */
    var getBadgeDataFromElements = function getBadgeDataFromElements(elements){

        var statusMap = {
            in_progress: 'numberOfTasksInProgress',
            created: 'numberOfTasksInProgress',
            failed: 'numberOfTasksFailed',
            completed: 'numberOfTasksCompleted',
        };

        var stats = {
            numberOfTasksFailed : 0,
            numberOfTasksCompleted : 0,
            numberOfTasksInProgress : 0
        };

        _.forEach(elements, function(element){
            var status = element.getStatus();
            if(statusMap[status]){
                //it is a know state, so add to the stats array
                stats[statusMap[status]]++;
            }
        });

        return getBadgeDataFromStatus(stats);
    };

    var taskQueue = {

        /**
         * Get the list of task elements
         * @returns {taskQueueManager} - self for chaining
         */
        getTaskElements : function getTaskElements(){
            return this.taskElements;
        },

        /**
         * Show the details associated to a task
         * @param {Object} tasksData - a single task data to view the report form
         * @returns {taskQueueManager} - self for chaining
         */
        showDetail : function showDetail(taskData){
            var $component = this.getElement();
            var list = this.list;
            var reportElement = reportElementFactory({replace:true}, taskData)
                .on('close', function(){
                    list.hideDetail();
                    list.alignWith($component, {
                        hPos: 'center',
                        hOrigin: 'center',
                        vPos: 'bottom',
                        vOrigin: 'top',
                        hOffset: -156
                    });
                    this.destroy();
                });
            list.setDetail(reportElement, true);
            list.alignWith($component, {
                hPos: 'center',
                hOrigin: 'center',
                vPos: 'bottom',
                vOrigin: 'top',
                hOffset: -156-121
            });
            return this;
        },

        /**
         * Add a new task
         * @param {Object} tasksData - a single task data to be added to the list
         * @param {Boolean} [animate=false] - tells if the new task addition should be made through a smooth transition effect
         * @returns {taskQueueManager} - self for chaining
         */
        addNewTask : function addNewTask(taskData, animate){
            var self = this;
            var taskId = taskData.id;
            var listElement = listElementFactory({}, taskData)
                .on('remove', function(){
                    delete self.taskElements[taskId];
                    self.list.removeElement(listElement);
                    self.trigger('remove', taskId);
                    self.selfUpdateBadge();
                })
                .on('report', function(){
                    self.trigger('report', taskId);
                })
                .on('download', function(){
                    self.trigger('download', taskId);
                });


            if(animate){
                if(this.list.is('hidden')){
                    this.list.show();
                }
                this.list.scrollToTop();
            }

            this.list.insertElement(listElement);
            this.taskElements[taskId] = listElement;
            this.selfUpdateBadge();

            if(animate){
                this.list.animateInsertion(listElement);
            }
            return this;
        },

        /**
         * Update the badge display according to the current status of the tasks in the list
         * @returns {taskQueueManager} - self for chaining
         */
        selfUpdateBadge : function selfUpdateBadge(){
            var badgeData = getBadgeDataFromElements(this.getTaskElements());
            if(!this.badge){
                this.badge = makePulsable(badgeFactory(badgeData)).render(this.getElement());
            }else{
                this.badge.update(badgeData);
            }
            return this;
        },

        /**
         * Load the the array of task element data requested form the server REST API
         * @param {Array} tasksData - the task data to be loaded from the server REST API call
         * @returns {taskQueueManager} - self for chaining
         */
        loadData : function loadData(tasksData){
            var self = this;
            var found = [];
            _.forEach(tasksData, function(entry){
                var id = entry.id;
                if(self.taskElements[id]){
                    //update
                    self.taskElements[id].update(entry);
                    if(self.taskElements[id].getStatus() !== entry.status){
                        //highlight status change only
                        self.taskElements[id].highlight();
                    }
                }else{
                    //create
                    self.addNewTask(entry);
                }
                found.push(id);
            });
            this.selfUpdateBadge();
            return this;
        },

        /**
         * Trigger the pulse animation on the status badge
         * @returns {taskQueueManager} - self for chaining
         */
        pulse : function pulse(){
            if(this.badge){
                this.badge.pulse(3);
            }
            return this;
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
     * @event listshow - Emitted when the list is displayed
     * @event listhide - Emitted when the list is hidden
     */
    return function taskQueueManagerFactory(config, data) {

        data = data || {};

        /**
         * The component
         * @typedef {ui/component} taskQueueManager
         */
        return makeAbsorbable(component(taskQueue))
            .setTemplate(managerTpl)
            .on('destroy', function(){
                $(document).off('click.task-queue-manager');
            })
            .on('init', function() {
                //initialize the task element collection
                this.taskElements = {};
            })
            .on('render', function() {

                var self = this;
                var $trigger = this.getElement();

                //create the list
                this.list = makeAlignable(taskListFactory())
                    .on('show', function(){
                        self.trigger('listshow');
                    })
                    .on('hide', function(){
                        self.trigger('listhide');
                    })
                    .init({
                        title : __('Background tasks'),
                        emptyText : __('There is currently no background task'),
                    });

                //position it
                this.list.render($trigger)
                    .moveBy(0, 0)
                    .alignWith($trigger, {
                        hPos: 'center',
                        hOrigin: 'center',
                        vPos: 'bottom',
                        vOrigin: 'top',
                        hOffset: -156
                    })
                    .hide();//start hidden

                //load initial data
                this.loadData(data);

                //prevent closing the panel when clicking on it
                this.list.getElement()
                    .addClass('overflown-element')
                    .on('click', function(e){
                    e.stopPropagation();
                });

                //close the popup when clicking outside of the component
                $(document).off('click.task-queue-manager').on('click.task-queue-manager', function(e){
                    if($trigger.get(0) !== e.target && !$.contains($trigger.get(0), e.target)){
                        if(!self.list.is('hidden')){
                            self.list.hide();
                        }
                    }
                });

                //toggle list visibility
                $trigger.on('click', function(){
                    if(self.list.is('hidden')){
                        self.list.show();
                    }else{
                        self.list.hide();
                    }
                });

            })
            .init(config || {});
    };

});