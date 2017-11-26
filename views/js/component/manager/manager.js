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
    'ui/component/alignable',
    'taoTaskQueue/component/listing/element',
    'taoTaskQueue/component/listing/report',
    'taoTaskQueue/component/listing/list',
    'tpl!taoTaskQueue/component/manager/trigger',
    'css!taoTaskQueue/component/manager/css/manager'
], function ($, _, __, hider, component, makeAlignable, listElementFactory, reportElementFactory, taskListFactory, triggerTpl) {
    'use strict';

    var _defaults = {
    };

    var getBadgeDataFromStatus = function getBadgeDataFromStatus(tasksStatuses){
        var running = (tasksStatuses.numberOfTasksInProgress > 0);
        if(tasksStatuses){
            if(tasksStatuses.numberOfTasksFailed){
                return {
                    type : 'error',
                    running : running,
                    value : parseInt(tasksStatuses.numberOfTasksFailed, 10),
                };
            }
            if(tasksStatuses.numberOfTasksCompleted){
                return {
                    type : 'success',
                    running : running,
                    value : parseInt(tasksStatuses.numberOfTasksCompleted, 10),
                };
            }
            if(tasksStatuses.numberOfTasksInProgress){
                return {
                    type : 'info',
                    running : running,
                    value : parseInt(tasksStatuses.numberOfTasksInProgress, 10),
                };
            }
            //hide badge in this case
            return null;
        }
    };

    var getBadgeDataFromFullLog = function getBadgeDataFromFullLog(tasksLogs){
        var logCollection = _(tasksLogs);
        var count = logCollection.filter({status: 'failed'}).size();
        if(count){
            return {
                type : 'error',
                value: count
            };
        }
        count = logCollection.filter({status: 'completed'}).size();
        if(count){
            return {
                type : 'success',
                value: count
            };
        }
        count = logCollection.filter({status: 'in_progress'}).size();
        if(count){
            return {
                type : 'info',
                value: count
            };
        }
    };

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

    function pulseBadge($badgeContainer){
        $badgeContainer.addClass('pulse');
        _.delay(function(){
            $badgeContainer.removeClass('pulse');
        }, 5000);
    }

    var taskQueue = {
        getTaskElements : function getTaskElements(){
            return this.taskElements;
        },
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
                });
            list.setDetail(reportElement, true);
            list.alignWith($component, {
                hPos: 'center',
                hOrigin: 'center',
                vPos: 'bottom',
                vOrigin: 'top',
                hOffset: -156-121
            });
        },
        addNewTask : function addNewTask(taskData, animate){
            var self = this;
            var taskId = taskData.id;
            var listElement = listElementFactory({}, taskData)
                .on('render', function(){
                    //console.log('DDD', this);
                })
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
        },
        selfUpdateBadge : function selfUpdateBadge(){
            var badgeData = getBadgeDataFromElements(this.getTaskElements());
            //var $badgeContainer = this.getElement().find('.task-manager-trigger');
            var $badgeBorder = this.getElement().find('.badge-border');
            var $badge = this.getElement().find('.badge').removeClass('badge-info badge-success badge-error icon-result-ok');
            var $loader = this.getElement().find('.loader');
            var displayValue;
            if(badgeData && badgeData.value){
                displayValue = parseInt(badgeData.value, 10);
                displayValue = (displayValue > 99) ? '99+' : displayValue;

                //set status
                $badge.addClass('badge-'+badgeData.type).html(displayValue);

                //if any is running
                if(badgeData.running){
                    hider.show($loader);
                    hider.hide($badgeBorder);
                }else{
                    hider.hide($loader);
                    hider.show($badgeBorder);
                }

                //if(this.badge){
                //    if(this.badge.type !== badgeData.type){
                //        this.trigger('badgetypechange', badgeData.type);
                //        //pulseBadge($badgeContainer)
                //        this.animatePulse();
                //    }
                //    if(this.badge.value !== badgeData.value){
                //        this.trigger('badgevaluechange', badgeData.value);
                //        //pulseBadge($badgeContainer)
                //        this.animatePulse();
                //    }
                //}else{
                //    //pulseBadge($badgeContainer)
                //    this.animatePulse();
                //}
                //
                //this.badge = {
                //    type : badgeData.type,
                //    value : badgeData.value
                //};

            }else{
                //idle state:
                hider.hide($loader);
                hider.hide($badgeBorder);
                $badge.addClass('icon-result-ok').empty();
                this.badge = null;
            }



        },
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

            //console.log('DIFF', found, _.keys(this.taskElements));

            this.selfUpdateBadge();
        },
        animateAbsorption : function animateAbsorption(){
            var self = this;
            return new Promise(function(resolve){
                var $target = self.getElement().find('.pulser');
                //$target.show();
                $target.addClass('animate-absorb');
                _.delay(function(){
                    $target.removeClass('animate-absorb');
                    //$target.hide();
                    resolve.call(self);
                },1500);
            });
        },
        animatePulse : function animatePulse(){
            var $target = this.getElement().find('.pulser');
            $target.addClass('animate-pulse');
            _.delay(function(){
                $target.removeClass('animate-pulse');
            },3100);
        },
        repositionList : function repositionList(){
            var $trigger = this.getElement();
            this.list.alignWith($trigger, {
                    hPos: 'center',
                    hOrigin: 'center',
                    vPos: 'bottom',
                    vOrigin: 'top',
                    hOffset: -156-122
                });
        }
    };

    /**
     * Builds an instance of the datalist component
     * @param {Object} config
     * @param {String} [config.keyName] - Sets the name of the attribute containing the identifier for each data line (default: 'id')
     * @param {String} [config.labelName] - Sets the name of the attribute containing the label for each data line (default: 'label')
     * @param {String|Boolean} [config.labelText] - Sets the displayed title for the column containing the labels. If the value is false no title is displayed (default: 'Label')
     * @param {String|Boolean} [config.title] - Sets the title of the list. If the value is false no title is displayed (default: false)
     * @param {String|Boolean} [config.textNumber] - Sets the label of the number of data lines. If the value is false no label is displayed (default: 'Available')
     * @param {String|Boolean} [config.textEmpty] - Sets the label displayed when there no data available. If the value is false no label is displayed (default: 'There is nothing to list!')
     * @param {String|Boolean} [config.textLoading] - Sets the label displayed when the list is loading. If the value is false no label is displayed (default: 'Loading')
     * @param {jQuery|HTMLElement|String} [config.renderTo] - An optional container in which renders the component
     * @param {Boolean} [config.selectable] - Append a checkbox on each displayed line to allow selection (default: false)
     * @param {Boolean} [config.replace] - When the component is appended to its container, clears the place before
     * @param {Function} [config.labelTransform] - Optional renderer applied on each displayed label.
     * @param {Function} [config.countRenderer] - An optional callback applied on the list count before display
     * @param {Array} [config.tools] - An optional list of buttons to add on top of the list. Each buttons provides a mass action on the selected lines. If selectable is not enabled, all lines are selected.
     * @param {Array} [config.actions] - An optional list of buttons to add on each line.
     * @param {Array} [data] - The data to display
     * @returns {datalist}
     *
     * @event init - Emitted when the component is initialized
     * @event destroy - Emitted when the component is destroying
     * @event render - Emitted when the component is rendered
     * @event update - Emitted when the component is updated
     * @event tool - Emitted when a tool button is clicked
     * @event action - Emitted when an action button is clicked
     * @event select - Emitted when a selection is made
     * @event show - Emitted when the component is shown
     * @event hide - Emitted when the component is hidden
     * @event enable - Emitted when the component is enabled
     * @event disable - Emitted when the component is disabled
     * @event template - Emitted when the template is changed
     */
    return function taskQueueManagerFactory(config, data) {
        var initConfig = config || {
                badgeClass : 'badge-info'
            };

        data = data || {};

        return component(taskQueue)
            .setTemplate(triggerTpl)

            .on('init', function() {
                //this.render($container);
                this.taskElements = {};
            })

            // uninstalls the component
            .on('destroy', function() {
            })

            // renders the component
            .on('render', function() {

                var self = this;
                var $trigger = this.getElement();

                this.list = makeAlignable(taskListFactory())
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

                this.loadData(data);

                //prevent closing the panel when clicking on it
                this.list.getElement()
                    .addClass('overflown-element')
                    .on('click', function(e){
                    e.stopPropagation();
                });

                //toggle pannel visibility
                $trigger.on('click', function(){
                    if(self.list.is('hidden')){
                        self.list.show();
                    }else{
                        self.list.hide();
                    }
                    //self.animateReduction();//for animation testing purpose nly
                });

            })
            .init(initConfig);
    };

});