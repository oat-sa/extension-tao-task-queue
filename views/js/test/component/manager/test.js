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
    'taoTaskQueue/component/manager/manager'
], function($, taskQueueManagerFactory) {
    'use strict';

    QUnit.module('API');

    QUnit.test('module', function(assert) {
        QUnit.expect(3);

        assert.equal(typeof taskQueueManagerFactory, 'function', "The taskQueueManagerFactory module exposes a function");
        assert.equal(typeof taskQueueManagerFactory(), 'object', "The taskQueueManagerFactory produces an object");
        assert.notStrictEqual(taskQueueManagerFactory(), taskQueueManagerFactory(), "The taskQueueManagerFactory provides a different object on each call");
    });

    //QUnit.cases([
    //    { title : 'init' },
    //    { title : 'destroy' },
    //    { title : 'render' },
    //    { title : 'show' },
    //    { title : 'hide' },
    //    { title : 'enable' },
    //    { title : 'disable' },
    //    { title : 'is' },
    //    { title : 'setState' },
    //    { title : 'getContainer' },
    //    { title : 'getElement' },
    //    { title : 'getTemplate' },
    //    { title : 'setTemplate' },
    //]).test('Component API ', function(data, assert) {
    //    var instance = taskQueueManagerFactory();
    //    assert.equal(typeof instance[data.title], 'function', 'The resourceList exposes the component method "' + data.title);
    //});
    //
    //QUnit.cases([
    //    { title : 'on' },
    //    { title : 'off' },
    //    { title : 'trigger' },
    //    { title : 'before' },
    //    { title : 'after' },
    //]).test('Eventifier API ', function(data, assert) {
    //    var instance = taskQueueManagerFactory();
    //    assert.equal(typeof instance[data.title], 'function', 'The resourceList exposes the eventifier method "' + data.title);
    //});
    //
    //QUnit.cases([
    //    { title : 'query' },
    //    { title : 'update' },
    //]).test('Instance API ', function(data, assert) {
    //    var instance = taskQueueManagerFactory();
    //    assert.equal(typeof instance[data.title], 'function', 'The resourceList exposes the method "' + data.title);
    //});


    QUnit.module('Behavior');

    QUnit.module('Visual');

    QUnit.asyncTest('playground', function(assert) {

        var container = document.getElementById('visual');
        var config = {
        };

        var _sampleBadgeData = {
            numberOfTasksCompleted:10,
            numberOfTasksFailed:2,
            numberOfTasksInProgress:5
        };

        var _sampleLogCollection = [
            {
                id: 'rdf#i1508337970199318643',
                task_name: 'Task Name',
                label: 'Task label',
                status: 'completed',
                owner: 'userId',
                created_at: '1510149684',//timezone ?
                updated_at: '1510149694',
                file: false,//suppose
                category: 'import',
                report : {
                    type : 'success',
                    message : 'completed task rdf#i1508337970199318643',
                    data : null,
                    children: []
                }
            },
            {
                id: 'rdf#i15083379701993186432222',
                task_name: 'Task Name 2',
                label: 'Task label 2',
                status: 'in_progress',
                owner: 'userId',
                created_at: '1510149584',//timezone ?
                updated_at: '1510149574',
                file: false,
                category: 'publish',//d
                report : {
                    type : 'info',
                    message : 'running task rdf#i15083379701993186432222',
                    data : null,//download url ? task context ?
                    children: []
                }
            },
            {
                id: 'rdf#i1508337970190342',
                task_name: 'Task Name 2',
                label: 'Task label 2',
                status: 'failed',
                owner: 'userId',
                created_at: '1510149584',//timezone ?
                updated_at: '1510049574',
                file: true,//suppose
                category: 'export',//d
                report : {
                    type : 'error',
                    message : 'running task rdf#i1508337970190342',
                    data : null,//download url ? task context ?
                    children: []
                }
            }
        ];

        QUnit.expect(1);

        taskQueueManagerFactory(config, _sampleLogCollection)
            .on('render', function(){
                assert.ok(true);
                QUnit.start();
            })
            .render(container);
    });
});
