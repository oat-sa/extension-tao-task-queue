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
    'ui/feedback',
    'ui/report',
    'taoTaskQueue/model/taskQueue',
    'taoTaskQueue/component/spinnerButton/spinnerButton',
    'layout/loading-bar',
    'tpl!taoTaskQueue/creator/tpl/reportContainer'
], function ($, _, __, feedback, reportFactory, taskQueue, spinnerButtonFactory, loadingBar, reportContainerTpl) {
    'use strict';

    /**
     * prepare the given container to display the final report
     * @param {Object} report - the standard report object
     * @param {String} type - the report type to be displayed in the title
     * @param {JQuery} $container - the container that will contain the report
     */
    function displayReport(report, type, $container, selectNode) {
        var $reportContainer = $(reportContainerTpl({
            title: type
        }));
        $container.html($reportContainer);
        return reportFactory({
            actions: [{
                id: 'continue',
                icon: 'right',
                title: 'continue',
                label: __('Continue')
            }]
        }, report)
            .on('action-continue', function () {
                $('.tree').trigger('refresh.taotree', [{
                    uri: selectNode
                }]);
            }).render($reportContainer.find('.report'));
    }

    /**
     * Manage task creation from a standard backoffice form and handle results in a consistent way
     * (this is but an early attempt to standardize task queue creation process)
     */
    return function formTaskCreator($form, $container) {

        var $oldSubmitter = $form.find('.form-submitter');
        var button = spinnerButtonFactory({
                type: 'info',
                icon: 'delivery',
                title: 'Publish the test',
                label: 'Publish',
                terminatedLabel: 'Moved to background'
            }).on('started', function () {
                loadingBar.start();
                taskQueue.pollAllStop();
                taskQueue.create($form.prop('action'), $form.serializeArray()).then(function (result) {
                    var task = result.task;
                    var selectNode;
                    if (result.extra && result.extra.selectNode) {
                        selectNode = result.extra.selectNode;
                    }

                    loadingBar.stop();

                    if (result.finished) {
                        //the task finished quickly -> display report
                        displayReport(
                            task.report.children[0],
                            (task.report.type === 'error') ? __('Error') : __('Success'),
                            $container,
                            selectNode);

                        //immediately archive the finished task as there is no need to display this task in the queue list
                        taskQueue.archive(task.id).then(function () {
                            taskQueue.pollAll();
                        });
                    } else {
                        //prevent further interactions and inform the user that task will move to the background and
                        $container
                            .css('position', 'relative')
                            .append($('<div class="overlay-screen">').css({
                                width: '100%',
                                height: '100%',
                                position: 'absolute',
                                background: 'gray',
                                opacity: 0.1,
                                top: 0,
                                left: 0
                            }));
                        button.terminate().hide();
                        var $info = $('<div class="small feedback-info">')
                            .css({
                                //marginTop : 40,
                                textAlign : 'left',
                                padding: '8px 20px 8px 20px'
                            })
                            .html(__('<strong> %s </strong> takes a long time to execute so it has been moved to the background. You can continue working elsewhere.', task.taskLabel));
                        button.getElement().after($info);

                        //leave the user a moment to make the connection between the notification message and the animation
                        taskQueue.trigger('taskcreated', task);
                        _.delay(function () {
                            taskQueue.pollAll();
                        }, 1500);
                    }
                    loadingBar.stop();
                }).catch(function (err) {
                    taskQueue.pollAll();
                    feedback().error(err);
                });
            })
            .render($oldSubmitter.closest('.form-toolbar'));

        $oldSubmitter.replaceWith(button.getElement().css({float: 'right'}));
    };
});