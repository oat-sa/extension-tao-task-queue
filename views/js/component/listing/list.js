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

    var badgeApi = {
        setData : function setType(data){
            return this;
        },
        update : function update(data){
            var self = this;
            var $list = this.getElement().find('ul');
            var found = [];

            _.forEach(data, function(entry){
                var listElement, $li;
                var id = entry.id;
                if(self.elements[id]){
                    //update
                    self.elements[id].update(entry).highlight();
                }else{
                    //create
                    $li = $(elementWrapperTpl({
                        id : entry.id
                    }));
                    $list.prepend($li);

                    listElement = listElementFactory({}, entry)
                        .on('render', function(){
                            //console.log('DDD', this);
                        })
                        .on('destroy', function(){
                            $li.remove();
                            self.trigger('archivetask', $li.data('id'));
                            console.log($li.data('id'));
                        })
                        .on('download', function(){
                            $li.remove();
                            self.trigger('download', $li.data('id'));
                            console.log($li.data('id'));
                        })
                        .render($li);

                    listElement.getElement().addClass('new-element');
                    _.delay(function(){
                        listElement.getElement().removeClass('new-element');
                    }, 1000);

                    self.elements[id] = listElement;
                    found.push(id);
                }
            });

            //remove cleared ones:
            console.log(found, _.keys(this.elements));


            this.data = data;

            this.getElement().find('.description').html(__('Running 1/2 background jobs'));

            _.delay(function(){
                //var one = _.values(self.elements)[1];
                //one.update({
                //    status: 'failed',
                //    file: true,
                //    updated_at: Math.floor(Date.now() / 1000)
                //}).highlight();

                //var $placeholder = $('<li class="placeholder">TTT</li>');
                //$list.prepend($placeholder);
                //_.delay(function(){
                //    $placeholder.addClass('grow');
                //}, 1000);

            }, 1000);

            return this;
        },
        empty : function empty(){
            this.getElement().find('ul').empty();
            this.elements = [];
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