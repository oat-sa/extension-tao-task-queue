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
    'ui/component',
    'taoTaskQueue/component/listing/element',
    'tpl!taoTaskQueue/component/listing/tpl/list',
    'tpl!taoTaskQueue/component/listing/tpl/elementWrapper'
], function ($, _, __, component, listElementFactory, listTpl, elementWrapperTpl) {
    'use strict';

    var _defaults = {
    };

    var animateIntersion = function animateIntersion(listElement){
        var $listElement = listElement.getElement();
        var $container = $listElement.parent();
        $container.addClass('inserting');
        $listElement.addClass('new-element');
        _.delay(function(){
            $container.removeClass('inserting');
            _.delay(function(){
                $listElement.removeClass('new-element');
            }, 400);
        },100);
    };

    var listApi = {

        removeElement : function removeElement(listElement){
            listElement.destroy();
            this.getElement().find('ul li[data-id="'+listElement.getId()+'"]').remove();
            return this;
        },

        insertElement : function insertElement(listElement){
            var id = listElement.getId();
            var $li = $(elementWrapperTpl({
                id : id
            }));
            this.getElement().find('ul').prepend($li);
            listElement.render($li);
            return this;
        },
        setTitle : function setTitle(title){
            if(this.is('rendered')){
                this.getElement().find('.description').html(title);
            }else{
                this.config.title = title;
            }
            return this;
        },
        scrollToTop : function scrollToTop(){
            this.getElement().find('.task-list').get(0).scrollTo(0, 0);
            return this;
        },
        animateInsertion : function animateInsertion(listElement){
            animateIntersion(listElement);
            return this;
        }
    };

    return function taskListFactory(config) {
        var initConfig = _.defaults(config || {}, _defaults);

        return component(listApi)
            .setTemplate(listTpl)
            .on('init', function() {

            })

            // uninstalls the component
            .on('destroy', function() {
            })

            // renders the component
            .on('render', function() {

            })
            .init(initConfig);
    };

});