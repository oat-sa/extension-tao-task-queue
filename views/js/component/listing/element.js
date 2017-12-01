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
    'tpl!taoTaskQueue/component/listing/tpl/element',
    'css!taoTaskQueue/component/listing/css/element'
], function ($, _, __, moment, component, hider, elementTpl) {
    'use strict';

    var _defaults = {
    };

    var _allowedStatus = ['in_progress', 'failed', 'completed'];

    var _categoryMap = {
        import : 'import',
        export : 'export',
        delivery_comp : 'delivery',
        transfer : 'connect',
        create : 'magicwand',
        update : 'edit',
        delete : 'bin'
    };

    var _statusIcon = {
        in_progress : 'property-advanced',//TODO find a better one
        completed: 'result-ok',
        failed: 'result-nok',
    };

    var getLabelString = function getLabelString(data){
        return data.taskLabel;
    };

    var getFormattedTime = function getFormattedTime(from, elapsed){
        return moment.unix(from).from(moment.unix(parseInt(from, 10)+parseInt(elapsed, 10)));
    };

    var getTimeString = function getTimeString(data){
        switch(data.status){
            case 'created':
            case 'in_progress':
                return __('Started %s', getFormattedTime(data.createdAt, data.createdAtElapsed));
            case 'completed':
                return __('Completed %s', getFormattedTime(data.updatedAt, data.updatedAtElapsed));
            case 'failed':
                return __('Failed %s', getFormattedTime(data.updatedAt, data.updatedAtElapsed));
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
            icon = _statusIcon.in_progress;
        }
        return 'icon-'+icon;
    };

    var taskElementApi = {
        getId : function getId(){
            if(this.data && this.data.id){
                return this.data.id;
            }
        },
        getStatus : function getStatus(){
            if(this.data && this.data.status){
                return this.data.status;
            }
        },
        getData : function getData(){
            return this.data;
        },
        update : function update(data){
            var $container = this.getElement();

            this.data = _.assign(this.data || {}, data);

            $container.find('.shape > span').removeAttr('class').addClass(getIcon(this.data));
            $container.find('.label').html(getLabelString(this.data));
            $container.find('.time').html(getTimeString(this.data));

            this.setStatus(this.data.status);
            //bonus: check if there is any report and display the report button only when needed

            hider.toggle($container.find('.action-bottom [data-role="download"]'), this.data.hasFile);

            this.trigger('update');
            return this;
        },

        /**
         * Adding transition to highlight the element after an update
         * @returns {taskElementApi}
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

            if(['created'].indexOf(status) !== -1){
                status = 'in_progress';
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

        return component(taskElementApi)
            .setTemplate(elementTpl)
            .on('render', function() {

                var self = this;
                var $component = this.getElement();

                this.update(data);

                $component.find('[data-role="download"]').click(function(){
                    self.trigger('download');
                });
                $component.find('[data-role="remove"]').click(function(){
                    self.trigger('remove');
                });
                $component.find('[data-role="report"]').click(function(){
                    self.trigger('report');
                });

            })
            .init(initConfig);
    };

});