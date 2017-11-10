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
    'ui/hider',
    'tpl!taoTaskQueue/component/listing/tpl/element'
], function ($, _, __, moment, component, hider, elementTpl) {
    'use strict';

    var _defaults = {
    };

    var _allowedStatus = ['in_progress', 'failed', 'completed'];

    var _categoryMap = {
        import : 'import',
        export : 'export',
        publish : 'delivery',
        transfer : 'connect',
        create : 'magicwand',
        update : 'edit',
        delete : 'bin'
    };

    var _statusIcon = {
        running : 'property-advanced',//TODO find a better one
        complete: 'result-ok',
        failure: 'result-nok',
    };

    var getLabelString = function getLabelString(data){
        return data.label;
    };

    var getFormattedTime = function getFormattedTime(timestamp){
        return  moment.unix(timestamp).fromNow();
    };

    var getTimeString = function getTimeString(data){
        switch(data.status){
            case 'in_progress':
                return __('Started %s', getFormattedTime(data.created_at));
            case 'completed':
                return __('Completed %s', getFormattedTime(data.updated_at));
            case 'failed':
                return __('Failed %s', getFormattedTime(data.updated_at));
        }
    };

    var getIcon = function getIcon(data){
        var icon;
        if(!_.isPlainObject(data)){
            throw new Error('invalid data');
        }
        if(data.category && _categoryMap[data.category]){
            icon = _categoryMap[data.category];
        }else if(data.status && _statusIcon[data.status]){
            icon = _statusIcon[data.status];
        }else {
            icon = _statusIcon.running;
        }
        return 'icon-'+icon;
    };

    var badgeApi = {
        update : function update(data){
            var $container = this.getElement();

            this.data = _.assign(this.data || {}, data);

            $container.find('.shape > span').removeAttr('class').addClass(getIcon(this.data));
            $container.find('.label').html(getLabelString(this.data));
            $container.find('.time').html(getTimeString(this.data));

            this.setStatus(this.data.status);

            hider.toggle($container.find('.action-bottom [data-role="download"]'), this.data.file);

            return this;
        },

        /**
         * Adding transition to highlight the element after an update
         * @returns {badgeApi}
         */
        highlight : function highlight(){
            var $container = this.getElement();
            $container.addClass('highlight');
            _.delay(function(){
                $container.removeClass('highlight');
            }, 500);
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

    return function taskElementFactory(config, data) {
        var initConfig = _.defaults(config || {}, _defaults);

        return component(badgeApi)
            .setTemplate(elementTpl)
            .on('init', function() {

            })

            // uninstalls the component
            .on('destroy', function() {
                this.getElement().remove();
            })

            // renders the component
            .on('render', function() {

                var self = this;
                var $component = this.getElement();

                this.update(data);

                $component.find('[data-role="download"]').click(function(){
                    self.trigger('download');
                });
                $component.find('[data-role="delete"]').click(function(){
                    self.destroy();
                });
                $component.find('[data-role="report"]').click(function(){
                    self.trigger('report');
                });

            })
            .init(initConfig);
    };

});