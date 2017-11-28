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
    'tpl!taoTaskQueue/component/spinnerButton/tpl/button',
    'css!taoTaskQueue/component/spinnerButton/css/spinnerButton'
], function ($, _, __, component, buttonTpl) {
    'use strict';

    var _defaults = {
        type : 'info',
        icon : 'property-advanced',
        label : 'GO GO GO',
        title : 'GO GO GO'
    };

    var buttonApi = {
        setType : function setType(type){
            if(_templates[type]){
                this.config.type = type;
                this.update();
            }
            return this;
        },
        setValue : function setType(value){
            value = parseInt(value, 10);
            this.config.value = (value > 99) ? '99+' : value;
            this.update();
            return this;
        },
        update : function update(config){

            return this;
            if(config){
                _.assign(this.config, config);
            }
            this.getElement().html(_templates[this.config.type].call(null, {value : this.config.value}));
            return this;
        },
        terminate : function terminate(){
            this.setState('triggered', false);
            this.setState('terminated', true);
            this.trigger('terminated');
        }
    };

    return function spinnerButtonFactory(config) {
        var initConfig = _.defaults(config || {}, _defaults);
        return component(buttonApi)
            .setTemplate(buttonTpl)

            .on('init', function() {
                //this.render($container);
            })

            // uninstalls the component
            .on('destroy', function() {
            })

            // renders the component
            .on('render', function() {

                var self = this;

                this.update();

                this.getElement().on('click', function(){
                    if(!self.is('triggered') && !self.is('terminated')){
                        self.setState('triggered', true);
                        self.trigger('triggered');
                    }
                });

            })
            .init(initConfig);
    };

});