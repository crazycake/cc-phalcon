/**
 * Webpack builder
 */

//required modules
import babelify   from 'babelify';
import browserify from 'browserify';
import gulp       from 'gulp';
import gutil      from 'gulp-util';
import assign     from 'lodash.assign';
import source     from 'vinyl-source-stream';
import watchify   from 'watchify';
import yargs      from "yargs";

//++ Browserify

let webpack_name = yargs.argv.w;
//check args
webpack_name = (typeof webpack_name !== "undefined") ? webpack_name : "webpack_core";

//set consts
const webpack_file =  webpack_name + ".js";
const webpack_src  = "./src/" + webpack_file;
const webpack_dist = "./dist/js/";

// set up the browserify instance on a task basis
const browserify_conf = {
    entries      : [webpack_src],
    cache        : {},
    packageCache : {}
};

//set browserify object
var webpack = watchify(browserify(assign({}, watchify.args, browserify_conf)))
                .transform(babelify, {
                    presets : ["es2015"],
                    ignore  : "./src/plugins/"
                });

//require bundle with expose name
webpack.require([webpack_src], { expose : webpack_file.replace(".js", "") });
//events
webpack.on("update", bundleApp); //on any dep update, runs the bundler
webpack.on("log", gutil.log);    //output build logs to terminal

function bundleApp() {
    //browserify js bundler
    return webpack.bundle()
        .on("error", gutil.log.bind(gutil, "Browserify Bundle Error"))
        .pipe(source(webpack_file.replace(".js", ".bundle.js")))
        //prepend contents
        .pipe(gulp.dest(webpack_dist));
}

//++ Tasks

gulp.task("js", bundleApp);
gulp.task("default", ["watch"]);
gulp.task("watch", ["js"]);
gulp.task("build", ["js"]);
