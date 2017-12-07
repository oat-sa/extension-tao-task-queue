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

/**
 * A button component used to trigger lengthy action from the tree
 *
 * @example
 * treeTaskButtonFactory({
 *          icon : 'property-advanced',
 *          label : 'Run'
 *     });
 *
 * @author Sam <sam@taotesting.com>
 */
define([
    'jquery',
    'lodash',
    'i18n',
    'ui/feedback',
    'ui/component',
    'layout/loading-bar',
    'taoTaskQueue/component/button/taskable',
    'tpl!taoTaskQueue/component/button/tpl/treeButton',
    'css!taoTaskQueue/component/button/css/treeButton'
], function ($, _, __, feedback, component, loadingBar, makeTaskable, buttonTpl) {
    'use strict';

    var _defaults = {
        icon : 'property-advanced',
        label : 'OK'
    };

    var buttonApi = {
        /**
         * Start the button spinning
         * @returns {treeTaskButton}
         */
        start : function start(){
            this.createTask();
            this.setState('started', true);
            this.trigger('start');
            return this;
        },
        /**
         * Stop the button spinning
         * @returns {treeTaskButton}
         */
        stop : function stop(){
            if(this.is('started')){
                this.setState('started', false);
                this.trigger('stop');
            }
            return this;
        }
    };

    /**
     * Create a button with to create a task
     * @param {Object} config - the component config
     * @param {String} config.icon - the button icon
     * @param {String} config.label - the button's label
     * @return {treeTaskButton} the component
     */
    return function treeTaskButtonFactory(config) {
        var initConfig = _.defaults(config || {}, _defaults);

        /**
         * @typedef {treeTaskButton} the component
         */
        return makeTaskable(component(buttonApi))
            .on('finished', function(){
                this.stop();
            })
            .on('enqueued', function(){
                this.stop();
            })
            .setTemplate(buttonTpl)
            .init(initConfig);
    };

});