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
    'tpl!taoTaskQueue/component/listing/tpl/element'
], function ($, _, __, component, elementTpl) {
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
            return this;
        },
    };

    return function taskElementFactory(config, data) {
        var initConfig = _.defaults(config || {}, _defaults);

        return component(badgeApi)
            .setTemplate(elementTpl)
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

                this.update();

            })
            .init(initConfig);
    };

});