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
    'ui/component',
    'ui/component/alignable',
    'taoTaskQueue/component/badge/badge',
    'taoTaskQueue/component/listing/list',
    'tpl!taoTaskQueue/component/manager/trigger',
    'css!taoTaskQueue/component/manager/css/manager'
], function ($, _, __, component, makeAlignable, badgeFactory, taskListFactory, triggerTpl) {
    'use strict';

    var _defaults = {
    };


    var getBadgeDataFromStatus = function getBadgeDataFromStatus(tasksStatuses){
        if(tasksStatuses){
            if(!_.isUndefined(tasksStatuses.numberOfTasksFailed)){
                return {
                    type : 'error',
                    value : parseInt(tasksStatuses.numberOfTasksFailed, 10),
                };
            }
            if(!_.isUndefined(tasksStatuses.numberOfTasksCompleted)){
                return {
                    type : 'success',
                    value : parseInt(tasksStatuses.numberOfTasksCompleted, 10),
                };
            }
            if(!_.isUndefined(tasksStatuses.numberOfTasksInProgress)){
                return {
                    type : 'info',
                    value : parseInt(tasksStatuses.numberOfTasksInProgress, 10),
                };
            }
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

    var taskQueue = {
        addNewTask : function addNewTask(taskData){
            var badgeData;
            this.data.push(taskData);
            badgeData = getBadgeDataFromFullLog(this.data);
            this.badge.update(badgeData);
            this.list.addNewTask(taskData);
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

        return component(taskQueue)
            .setTemplate(triggerTpl)

            .on('init', function() {
                //this.render($container);
            })

            // uninstalls the component
            .on('destroy', function() {
            })

            // renders the component
            .on('render', function() {

                var self = this;
                var $trigger = this.getElement();

                this.data = data;
                this.badge = badgeFactory(getBadgeDataFromFullLog(this.data))
                    .on('render', function(){
                        //var badge = this;
                        //badge.pulse();
                        //_.delay(function(){
                        //    badge.setType('success');
                        //    badge.setValue(1);
                        //    badge.pulse();
                        //}, 6000);
                    })
                    .render($trigger);

                this.list = makeAlignable(taskListFactory({startHidden : true}, this.data))
                    .show()
                    .init()
                    .render($trigger)
                    .moveBy(0, 0)
                    .alignWith($trigger, {
                        hPos: 'center',
                        hOrigin: 'center',
                        vPos: 'bottom',
                        vOrigin: 'top'
                    });

                //prevent closing the panel when clicking on it
                this.list.getElement().on('click', function(e){
                    e.stopPropagation();
                });

                //toggle pannel visibility
                $trigger.on('click', function(){
                    if(self.list.is('hidden')){
                        self.list.show();
                    }else{
                        self.list.hide();
                    }
                });

            })
            .init(initConfig);
    };

});