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
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA;
 */

define([
    'jquery',
    'lodash',
    'i18n',
    'core/promise',
    'ui/report',
    'ui/feedback',
    'layout/loading-bar',
    'ui/loadingButton/loadingButton',
    'taoTaskQueue/component/button/taskable'
], function ($, _, __, Promise, reportFactory, feedback, loadingBar, loadingButton, makeTaskable) {
    'use strict';

    var defaultConfig = {
    };

    var standardTaskButtonComponent = {
        /**
         * Restore the button to its state before the task creation
         * @returns {standardTaskButton}
         */
        restoreButton : function restoreButton(){
            if(this.is('terminated')){
                this.reset()
                    .show()
                    .getElement().siblings('.task-creation-feedback').remove();
            }
            return this;
        }
    };

    /**
     * Builds a standard task creation button
     * @param {Object} config - the component config
     * @returns {standardTaskButton} the component
     */
    return function standardTaskButtonFactory(config) {

        var component;

        //prepare the config and
        config = _.defaults(config || {}, defaultConfig);

        //create the base loading button and make it taskable
        component = makeTaskable(loadingButton(config));

        //add specific methods
        _.assign(component, standardTaskButtonComponent);

        /**
         * The component
         * @typedef {ui/component} standardTaskButton
         */
        return component.on('started', function(){
            this.createTask();
        }).on('enqueued', function(){
            this.terminate().reset();
        });
    };
});
