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
        keyName : 'id',
        labelName : 'label',
        labelText : __('Label'),
        title : false,
        textNumber : __('Available'),
        textEmpty : __('There is nothing to list!'),
        textLoading : __('Loading'),
        selectable : false
    };

    var taskQueue = {

    };

    var _sampleBadgeData = {
        numberOfTasksCompleted:10,
        numberOfTasksFailed:2,
        numberOfTasksInProgress:5
    };

    var _sampleLogCollection = [
        {
            id: 'rdf#i1508337970199318643',
            task_name: 'Task Name',
            label: 'Task label',
            status: 'completed',
            owner: 'userId',
            created_at: '1510149684',//timezone ?
            updated_at: '1510149694',
            file: false,//suppose
            category: 'import',
            report : {
                type : 'success',
                message : 'completed task rdf#i1508337970199318643',
                data : null,
                children: []
            }
        },
        {
            id: 'rdf#i15083379701993186432222',
            task_name: 'Task Name 2',
            label: 'Task label 2',
            status: 'running',
            owner: 'userId',
            created_at: '1510149584',//timezone ?
            updated_at: '1510149574',
            file: false,
            category: 'publish',//d
            report : {
                type : 'info',
                message : 'running task rdf#i15083379701993186432222',
                data : null,//download url ? task context ?
                children: []
            }
        },
        {
            id: 'rdf#i1508337970190342',
            task_name: 'Task Name 2',
            label: 'Task label 2',
            status: 'failed',
            owner: 'userId',
            created_at: '1510149584',//timezone ?
            updated_at: '1510049574',
            file: true,//suppose
            category: 'export',//d
            report : {
                type : 'error',
                message : 'running task rdf#i1508337970190342',
                data : null,//download url ? task context ?
                children: []
            }
        }
    ];

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
    return function taskQueueManagerFactory(config) {
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

                var badge = badgeFactory({})
                    .on('render', function(){

                        this.setType('error');
                        this.setValue(199);

                        badge.pulse();
                        _.delay(function(){
                            badge.setType('success');
                            badge.setValue(1);
                            badge.pulse();
                        }, 6000);

                    })
                    .render($trigger);

                var list = makeAlignable(taskListFactory({startHidden : true}, _sampleLogCollection))
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
                list.getElement().on('click', function(e){
                    e.stopPropagation();
                });

                //toggle pannel visibility
                $trigger.on('click', function(e){
                    if(list.is('hidden')){
                        list.show();
                    }else{
                        list.hide();
                    }
                });

            })
            .init(initConfig);
    };

});