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
    'taoTaskQueue/component/spinnerButton/spinnerButton'
], function($, _, spinnerButtonFactory) {
    'use strict';

    QUnit.module('API');

    QUnit.test('module', function(assert) {
        QUnit.expect(3);

        assert.equal(typeof spinnerButtonFactory, 'function', "The spinnerButtonFactory module exposes a function");
        assert.equal(typeof spinnerButtonFactory(), 'object', "The spinnerButtonFactory produces an object");
        assert.notStrictEqual(spinnerButtonFactory(), spinnerButtonFactory(), "The spinnerButtonFactory provides a different object on each call");
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

        var $container = $('#visual');
        var button = spinnerButtonFactory({})
            .on('report', function(){
                //fetch report
            })
            .on('render', function(){
                var self = this;
                assert.ok(true);

                QUnit.start();
            })
            .on('started', function(){
                _.delay(function(){
                    button.terminate();
                }, 2000);
            })
            .render($container);
    });
});
