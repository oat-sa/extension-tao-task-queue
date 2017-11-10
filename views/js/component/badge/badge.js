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
    'tpl!taoTaskQueue/component/badge/tpl/badge',
    'tpl!taoTaskQueue/component/badge/tpl/info',
    'tpl!taoTaskQueue/component/badge/tpl/success',
    'tpl!taoTaskQueue/component/badge/tpl/error'
], function ($, _, __, component, badgeTpl, infoTpl, successTpl, errorTpl) {
    'use strict';

    var _defaults = {
        type : 'info',
        value : 0
    };

    var _templates = {
        info : infoTpl,
        success : successTpl,
        error : errorTpl
    };


    var badgeApi = {
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
        update : function update(){
            this.getElement().html(_templates[this.config.type].call(null, {value : this.config.value}));
            return this;
        },
        pulse : function pulse(){
            var $component = this.getElement();
            $component.addClass('pulse');
            _.delay(function(){
                $component.removeClass('pulse');
            }, 5000);
            return this;
        }
    };

    return function badgeFactory(config) {
        var initConfig = _.defaults(config || {}, _defaults);
        return component(badgeApi)
            .setTemplate(badgeTpl)

            .on('init', function() {
                //this.render($container);
            })

            // uninstalls the component
            .on('destroy', function() {
            })

            // renders the component
            .on('render', function() {

                this.update();

            })
            .init(initConfig);
    };

});