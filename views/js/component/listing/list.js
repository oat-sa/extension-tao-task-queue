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
            this.data = data;
            return this;
        },
        update : function update(){
            var self = this;
            var $list = this.getElement().find('ul').empty();
            this.elements = [];
            _.forEach(this.data, function(entry){
                var listElement;
                var $li = $(elementWrapperTpl({
                    id : entry.id
                }));
                $list.append($li);

                listElement = listElementFactory(entry)
                    .on('render', function(){
                        console.log('DDD', this);
                    })
                    .render($li);


                self.elements.push(listElement);
            });

            this.getElement().find('.description').html(__('Running 1/2 background jobs'));


            return this;
        },
    };

    return function taskListFactory(config, data) {
        var initConfig = _.defaults(config || {}, _defaults);

        return component(badgeApi)
            .setTemplate(listTpl)
            .on('init', function() {
                //this.render($container);
                if(_.isArray(data)){
                    this.setData(data);
                }
            })

            // uninstalls the component
            .on('destroy', function() {
            })

            // renders the component
            .on('render', function() {

                if(this.config.startHidden){
                    this.hide();
                }

                this.update();

            })
            .init(initConfig);
    };

});