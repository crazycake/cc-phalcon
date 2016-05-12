/**
 * Webpack builder
 */

//required modules
var browserify = require('browserify');
var gulp       = require('gulp');
var source     = require('vinyl-source-stream');
var buffer     = require('vinyl-buffer');
var gutil      = require('gulp-util');
var watchify   = require('watchify');
var assign     = require('lodash.assign');

//++ Browserify
//NOTE: use yargs npm-module for input webpack name core.

var webpack_file = 'webpack_core.js';
var webpack_src  = './src/' + webpack_file;
var webpack_dist = './dist/js/';

// set up the browserify instance on a task basis
var browserify_conf = {
    entries      : [webpack_src],
    cache        : {},
    packageCache : {}
};

//set browserify object
var b = watchify(browserify(assign({}, watchify.args, browserify_conf)));

//require bundle with expose name
b.require([webpack_src], { expose : webpack_file.replace(".js", "") });
//events
b.on('update', bundleApp); //on any dep update, runs the bundler
b.on('log', gutil.log);    //output build logs to terminal

function bundleApp() {
    //browserify js bundler
    return b.bundle()
        .on('error', gutil.log.bind(gutil, 'Browserify Bundle Error'))
        .pipe(source(webpack_file.replace(".js", ".bundle.js")))
        //prepend contents
        .pipe(gulp.dest(webpack_dist));
}

//++ Tasks

//javascript task
gulp.task('js', function() {

    gutil.log(gutil.colors.yellow('Watching ' + webpack_file + ' changes...'));
    //browserify
    bundleApp();
});

//watch or build task
gulp.task('watch', ['js']);
gulp.task('build', ['js']);
//default task
gulp.task('default', ['watch']);
