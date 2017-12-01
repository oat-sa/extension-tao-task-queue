module.exports = function(grunt) {
    'use strict';

    var requirejs   = grunt.config('requirejs') || {};
    var clean       = grunt.config('clean') || {};
    var copy        = grunt.config('copy') || {};

    var root        = grunt.option('root');
    var libs        = grunt.option('mainlibs');
    var ext         = require(root + '/tao/views/build/tasks/helpers/extensions')(grunt, root);
    var out         = 'output';

    var paths = {
        taoTaskQueue : root + '/taoTaskQueue/views/js',
    };

    /**
     * Remove bundled and bundling files
     */
    clean.taotaskqueuebundle = [out];

    /**
     * Compile tao files into a bundle
     */
    requirejs.taotaskqueuebundle = {
        options: {
            baseUrl : '../js',
            dir : out,
            mainConfigFile : './config/requirejs.build.js',
            paths : paths,
            modules : [{
                name: 'taoTaskQueue/controller/routes',
                include : ext.getExtensionsControllers(['taoTaskQueue']),
                exclude : ['mathJax'].concat(libs)
            }]
        }
    };

    /**
     * copy the bundles to the right place
     */
    copy.taotaskqueuebundle = {
        files: [
            { src: [out + '/taoTaskQueue/controller/routes.js'],  dest: root + '/taoTaskQueue/views/js/controllers.min.js' },
            { src: [out + '/taoTaskQueue/controller/routes.js.map'],  dest: root + '/taoTaskQueue/views/js/controllers.min.js.map' }
        ]
    };

    grunt.config('clean', clean);
    grunt.config('requirejs', requirejs);
    grunt.config('copy', copy);

    // bundle task
    grunt.registerTask('taotaskqueuebundle', ['clean:taotaskqueuebundle', 'requirejs:taotaskqueuebundle', 'copy:taotaskqueuebundle']);
};
