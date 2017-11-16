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
    'taoTaskQueue/model/taskQueue'
], function($, _, taskQueueModelFactory) {
    'use strict';

    QUnit.module('API');

    QUnit.test('module', function(assert) {
        QUnit.expect(3);

        assert.equal(typeof taskQueueModelFactory, 'function', "The taskQueueModelFactory module exposes a function");
        assert.equal(typeof taskQueueModelFactory(), 'object', "The taskQueueModelFactory produces an object");
        assert.notStrictEqual(taskQueueModelFactory(), taskQueueModelFactory(), "The taskQueueModelFactory provides a different object on each call");
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
    //    var instance = taskQueueModelFactory();
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
    //    var instance = taskQueueModelFactory();
    //    assert.equal(typeof instance[data.title], 'function', 'The resourceList exposes the eventifier method "' + data.title);
    //});
    //
    //QUnit.cases([
    //    { title : 'query' },
    //    { title : 'update' },
    //]).test('Instance API ', function(data, assert) {
    //    var instance = taskQueueModelFactory();
    //    assert.equal(typeof instance[data.title], 'function', 'The resourceList exposes the method "' + data.title);
    //});


    QUnit.module('Api');

    QUnit.asyncTest('getAll', function(assert) {
        QUnit.expect(2);
        taskQueueModelFactory({
            url : {
                all : '/taoTaskQueue/views/js/test/model/taskQueue/samples/getAll.json'
            }
        }).getAll().then(function(tasks){
            assert.ok(_.isArray(tasks), 'the data is an array');
            assert.equal(tasks.length, 3, 'all data fetched');
            QUnit.start();
        });

    });

    QUnit.asyncTest('setEndpoints', function(assert) {
        QUnit.expect(2);
        taskQueueModelFactory()
            .setEndpoints({
                all : '/taoTaskQueue/views/js/test/model/taskQueue/samples/getAll.json'
            })
            .getAll().then(function(tasks){
                assert.ok(_.isArray(tasks), 'the data is an array');
                assert.equal(tasks.length, 3, 'all data fetched');
                QUnit.start();
            });

    });

    QUnit.asyncTest('pollAll', function(assert) {
        QUnit.expect(4);
        taskQueueModelFactory({
            url : {
                all : '/taoTaskQueue/views/js/test/model/taskQueue/samples/getAll.json'
            }
        }).on('pollAllStart', function(){
            assert.ok(true, 'poll all started');
        }).on('pollAll', function(tasks){
            //change url
            assert.ok(_.isArray(tasks), 'the data is an array');
            assert.equal(tasks.length, 3, 'all data fetched');
            this.pollAllStop();

        }).on('pollAllStop', function(){
            assert.ok(true, 'poll all stopped');
            QUnit.start();
        }).pollAll();

    });

    QUnit.asyncTest('get', function(assert) {
        QUnit.expect(2);
        taskQueueModelFactory({
            url : {
                get : '/taoTaskQueue/views/js/test/model/taskQueue/samples/getSingle-inprogress.json'
            }
        }).get('rdf#i15083379701993186432222').then(function(task){
            assert.ok(_.isPlainObject(task), 'the data is an array');
            assert.equal(task.status, 'in_progress', 'the status is correct');
            QUnit.start();
        });

    });

    QUnit.asyncTest('pollSingle', function(assert) {
        var taskId = 'rdf#i15083379701993186432222';
        var i = 3;
        QUnit.expect(20);
        taskQueueModelFactory({
            url : {
                get : '/taoTaskQueue/views/js/test/model/taskQueue/samples/getSingle-inprogress.json'
            }
        }).on('pollSingleStart', function(id){
            assert.ok(true, 'poll single started');
            assert.equal(id, taskId, 'the started task id is correct');
        }).on('pollSingle', function(id, task){

            assert.equal(id, taskId, 'the task id is correct');
            assert.ok(_.isPlainObject(task), 'the data is a plain object');
            assert.equal(task.status, 'in_progress', 'the status is correct');

            if(i > 0){
                i--;
            }else{
                this.setEndpoints({
                    get : '/taoTaskQueue/views/js/test/model/taskQueue/samples/getSingle-completed.json'
                });
            }

        }).on('pollSingleFinished', function(id, task){
            assert.ok(true, 'poll single completed');
            assert.equal(id, taskId, 'the completed task id is correct');
            assert.ok(_.isPlainObject(task), 'the data is a plain object');
        }).pollSingle(taskId).then(function(result){
            assert.equal(result.finished, true, 'task had time to be completed');
            assert.ok(_.isPlainObject(result.task), 'the data is a plain object');
            assert.equal(result.task.status, 'completed', 'the status is completed');
            QUnit.start();
        });

    });
});
