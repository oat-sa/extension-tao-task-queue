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
    'moment',
    'ui/component',
    'tpl!taoTaskQueue/component/listing/tpl/element'
], function ($, _, __, moment, component, elementTpl) {
    'use strict';

    var _defaults = {
    };

    var _allowedStatus = ['running', 'failed', 'completed'];

    var badgeApi = {
        setData : function setType(data){
            this.data = data;
            return this;
        },
        update : function update(){
            return this;
        },
        setStatus : function setStatus(status){
            var self = this;
            if(!status){
                throw new Error('status should not be empty');
            }
            if(_allowedStatus.indexOf(status) === -1){
                throw new Error('unknown status '+status);
            }
            if(!this.is(status)){
                _.forEach(_.without(_allowedStatus, status), function(st){
                    self.setState(st, false);
                });
                this.setState(status, true);
            }
        }
    };

    var getFormattedTime = function getFormattedTime(timestamp){
        return  moment.unix(timestamp).fromNow();
    };

    var getTimeString = function getTimeString(data){
        switch(data.status){
            case 'running':
                return __('Started %s', getFormattedTime(data.created_at));
            case 'completed':
                return __('Completed %s', getFormattedTime(data.updated_at));
            case 'failed':
                return __('Failed %s', getFormattedTime(data.updated_at));
        }
    };

    return function taskElementFactory(config, data) {
        var initConfig = _.defaults(config || {}, _defaults);

        initConfig.time = getTimeString(config);

        return component(badgeApi)
            .setTemplate(elementTpl)
            .on('init', function() {
                if(_.isArray(data)){
                    this.setData(data);
                }
                console.log('this.config', this.config);
                if(this.config.status){
                    this.setStatus(this.config.status);
                }
            })

            // uninstalls the component
            .on('destroy', function() {
            })

            // renders the component
            .on('render', function() {

                var self = this;
                var $component = this.getElement();
                var config = this.config;
                $component.find('#icon-download').click(function(){
                    self.trigger('download', config.id);
                });
                this.update();

            })
            .init(initConfig);
    };

});