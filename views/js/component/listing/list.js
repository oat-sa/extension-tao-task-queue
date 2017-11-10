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
    'taoTaskQueue/component/listing/element',
    'tpl!taoTaskQueue/component/listing/tpl/list',
    'tpl!taoTaskQueue/component/listing/tpl/elementWrapper'
], function ($, _, __, component, listElementFactory, listTpl, elementWrapperTpl) {
    'use strict';

    var _defaults = {
        type : 'info',
        value : 0
    };

    var animateIntersion = function animateIntersion(listElement){
        var $listElement = listElement.getElement();
        var $container = $listElement.parent();
        $container.addClass('inserting');
        $listElement.addClass('new-element');
        _.delay(function(){
            $container.removeClass('inserting');
            _.delay(function(){
                //$listElement.addClass('new-element');
                //_.delay(function(){
                    $listElement.removeClass('new-element');
                //}, 100);
            }, 400);
        },10);
    };

    var badgeApi = {
        getElements : function getElements(){
            return this.elements;
        },
        addNewTask : function addNewTask(taskData, animate){
            var taskElement;
            var $container = this.getElement();
            //$container.find('.task-list').scrollTop(0);
            $container.find('.task-list').get(0).scrollTo(0, 0);

            taskElement = this.createElement(this.getElement().find('ul'), taskData);
            this.elements[taskData.id] = taskElement;

            animateIntersion(taskElement);
        },
        createElement : function createElement($appendTo, taskData){
            var self = this;
            var listElement;
            var $li = $(elementWrapperTpl({
                id : taskData.id
            }));
            $appendTo.prepend($li);

            listElement = listElementFactory({}, taskData)
                .on('render', function(){
                    //console.log('DDD', this);
                })
                .on('destroy', function(){
                    var taskId = $li.data('id');
                    $li.remove();
                    delete self.elements[taskId];
                    self.trigger('delete', taskId);
                })
                .on('download', function(){
                    $li.remove();
                    self.trigger('download', $li.data('id'));
                })
                .render($li);

            return listElement;
        },
        update : function update(data){//TODO rename load data
            var self = this;
            var $list = this.getElement().find('ul');
            var found = [];

            _.forEach(data, function(entry){
                var id = entry.id;
                if(self.elements[id]){
                    //update
                    self.elements[id].update(entry).highlight();
                }else{
                    //create
                    self.elements[id] = self.createElement($list, entry);
                    found.push(id);
                }
            });

            //remove cleared ones:
            console.log('DIFF', found, _.keys(this.elements));

            this.getElement().find('.description').html(__('Running 1/2 background jobs'));

            //console.log(this.elements);

            return this;
        },
        empty : function empty(){
            this.getElement().find('ul').empty();
            this.elements = {};
        }

    };

    return function taskListFactory(config, data) {
        var initConfig = _.defaults(config || {}, _defaults);

        return component(badgeApi)
            .setTemplate(listTpl)
            .on('init', function() {
            })

            // uninstalls the component
            .on('destroy', function() {
            })

            // renders the component
            .on('render', function() {

                this.empty();

                if(this.config.startHidden){
                    this.hide();
                }

                this.update(data);

            })
            .init(initConfig);
    };

});