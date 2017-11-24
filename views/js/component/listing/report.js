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
    'ui/report',
    'tpl!taoTaskQueue/component/listing/tpl/report'
], function ($, _, __, moment, component, hider, reportFactory, elementTpl) {
    'use strict';

    var _defaults = {
    };

    var reportElementApi = {
        update : function update(data){

            var $component = this.getElement();
            $component.find('.label').html(data.taskLabel);

            //set report here
            if(data.report && _.isArray(data.report.children) && data.report.children.length){
                this.setState('noreport', false);
                reportFactory({replace:true}, data.report.children[0]).render($component.find('.detail-body'));
            }else{
                this.setState('noreport', true);
            }
        }
    };

    return function taskReportFactory(config, data) {
        var initConfig = _.defaults(config || {}, _defaults);

        return component(reportElementApi)
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

                $component.find('[data-role="close"]').click(function(){
                    self.trigger('close');
                });

            })
            .init(initConfig);
    };

});