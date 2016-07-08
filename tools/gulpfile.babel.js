/**
 *	Gulp Core App (Task Runner)
 *	@author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

//core libs
import gulp       from "gulp";
import browserify from "browserify";
import babelify   from "babelify";
import source     from "vinyl-source-stream";
import buffer     from "vinyl-buffer";
import fs         from "fs";
import yargs      from "yargs";
import assign     from "lodash.assign";
import cprocess   from "child_process";
import importer   from "sass-importer-npm";
import watchify   from "watchify";
import vueify     from "vueify";
//plugins
import gutil        from "gulp-util";
import gulpif       from "gulp-if";
import chmod        from "gulp-chmod";
import rename       from "gulp-rename";
import insert       from "gulp-insert";
import autoprefixer from "gulp-autoprefixer";
import sass         from "gulp-sass";
import css_minifier from "gulp-clean-css";
import replacer     from "gulp-replace";
import sourcemaps   from "gulp-sourcemaps";
import livereload   from "gulp-livereload";
//libs
import bourbon from "node-bourbon";
import panini  from "panini";
import inky    from "inky";

/* Consts */

//set default app module
const app_module = getModuleArg();

//core files
const core_path = "./core/";

//sass app conf
const sass_app_conf = {
    importer     : importer,
    includePaths : [
        //app common utils
        core_path + "scss/",
        //bourbon path
        bourbon.includePaths,
        //family.scss
        "./node_modules/family.scss/source/src/",
        //foundation sites
        "./node_modules/foundation-sites/scss/"
    ]
};

//sass mailing conf
const sass_mailing_conf = {
    importer     : importer,
    includePaths : [
        //app common utils
        core_path + "scss/",
        //foundation sass files
        "./node_modules/foundation-emails/scss/"
    ]
};

//app paths
const app_paths = {
    assets   : "./" + app_module + "/public/assets/",
    sass     : "./" + app_module + "/dev/scss/",
    js       : "./" + app_module + "/dev/js/",
    vue      : "./" + app_module + "/dev/vue/",
    volt     : "./" + app_module + "/dev/volt/",
    mailing  : "./" + app_module + "/dev/mailing/",
    webpack  : "./" + core_path  + "js/webpack_core.bundle.min.js"
};

// set up the browserify instance on a task basis
const browserify_opts = assign({}, watchify.args, {
    entries      : [app_paths.js + "app.js"],
    cache        : {},
    packageCache : {},
    plugin       : [watchify]
});

//watch & bundle webpack
var b = browserify(browserify_opts)
        //external webpack
        .external("webpack_core")
        //babelify
        .transform(babelify, {
            presets : ["es2015"],
            //plugins : ['transform-runtime'],
            ignore  : core_path //exclude
        })
        //vueify
        .transform(vueify, {
            sass : sass_app_conf
        });

/** Tasks. TODO: implement gulp.series() v 4.x **/

//build & deploy
gulp.task("build", ["prod-node-env", "build-mailing", "minify-js", "minify-css", "rev-assets"], function() {

    gutil.log(gutil.colors.blue("Build complete"));
    //safer exit
    setTimeout(process.exit(), 100);
});
//watch
gulp.task("watch", watchApp);
//build & watch mailing
gulp.task("watch-mailing", watchMailing);
//build mailing
gulp.task("build-mailing", buildMailing);
//JS minify
gulp.task("minify-js", minifyJs);
//CSS minify
gulp.task("minify-css", minifyCss);
//create assets revision (CDN)
gulp.task("rev-assets", revision);
//set node env to produtction
gulp.task("prod-node-env", function() {
    return process.env.NODE_ENV = "production";
});
/**
 * Get Module Argument
 */
function getModuleArg() {

    var mod = yargs.argv.m;

    if (typeof mod == "undefined" || (mod != "frontend" && mod != "backend")) {
        gutil.log(gutil.colors.green("Invalid app module. Options: frontend or backend."));
        process.exit();
    }

    gutil.log(gutil.colors.blue("Module: " + mod));

    return mod;
}

/**
 * Bundle JS package with Browserify
 * @param {boolean} release - Flag for production
 */
function bundleApp(release = false) {

    //browserify js bundler
    return b.bundle()
            .on("error", gutil.log.bind(gutil, "Browserify Error"))
            .pipe(source("app.js"))
            .pipe(buffer())
            //prepend contents
            .pipe(insert.prepend(fs.readFileSync(app_paths.webpack, "utf-8")))
            .pipe(gulpif(release, rename({ suffix : ".min" })))
            .pipe(chmod(775))
            .pipe(gulp.dest(app_paths.assets))
            .pipe(gulpif(!release, livereload()));
}

/**
 * Minifies JS with uglifyify
 */
function minifyJs() {

    //uglify transform
    b.transform({
        global : true
    }, "uglifyify");

    //bundle with minification
    return bundleApp(true);
}

/**
 * CSS Minifier
 */
function minifyCss() {

    return gulp.src([app_paths.assets + "*.css", "!" + app_paths.assets + "*.*.css"])
            .pipe(buffer())
            .pipe(css_minifier())
            .pipe(rename({ suffix : ".min" }))
            .pipe(chmod(775))
            .pipe(gulp.dest(app_paths.assets));
}

/**
 * Assets revision for CDN
 */
function revision() {

    cprocess.exec("php cli/cli.php main revAssets " + app_module);
}

/**
 * Watch App
 */
function watchApp() {

    //setup
    b.on("update", bundleApp); //on any dep update, runs the bundler
    b.on("log", gutil.log);    //output build logs for watchify

    gutil.log(gutil.colors.green("Watching Scss, Js and Volt source files changes..."));

    //live reaload
    livereload.listen();
    //js bundle
    bundleApp();
    //sass files
    gulp.watch([app_paths.sass + "*.scss", app_paths.sass + "**/*.scss"], buildSass);
    //vue files
    gulp.watch(app_paths.vue + "*.vue", function() {
        return gulp.src(app_paths.js + "app.js").pipe(livereload());
    });
    //volt
    gulp.watch(app_paths.volt + "**/*.volt", function() {
        return gulp.src(app_paths.volt + "index.volt").pipe(livereload());
    });
}

/**
 * Sass builder
 */
function buildSass() {

    return gulp.src(app_paths.sass + "[^_]*.scss")
            .pipe(sourcemaps.init())
            //libsass
            .pipe(sass(sass_app_conf)
                .on("error", sass.logError))
            //autoprefixer
            .pipe(autoprefixer({
                browsers : ["last 2 versions"],
                cascade  : false
            }))
            .pipe(sourcemaps.write())
            .pipe(gulp.dest(app_paths.assets))
            .pipe(livereload());
}

/**
 * Build & watch app mailing
 */
function watchMailing() {

    //++ Build process
    buildMailing();

    gutil.log(gutil.colors.green("Watching Scss and HTML source files changes..."));

    //live reaload
    livereload.listen();

    //sass
    gulp.watch(app_paths.mailing + "scss/*.scss").on("change", function() {

        sassMailing();
        bundleMailing();
    });
    //layouts and partials
    gulp.watch([
        app_paths.mailing + "pages/**/*",
        app_paths.mailing + "layouts/**/*",
        app_paths.mailing + "partials/**/*"
    ])
    .on("change", function() {

        bundleMailing();
    });
}

/**
 * Build mailing
 */
function buildMailing() {

    //rerfresh panini
    panini.refresh();
    //compile sass
    sassMailing();
    //bundle mailing
    bundleMailing();
}

/**
 * Sass mailer compiler
 */
function sassMailing() {

    return gulp.src(app_paths.mailing + "scss/[^_]*.scss")
            .pipe(sass(sass_mailing_conf)
                  .on("error", sass.logError))
            .pipe(autoprefixer({
                browsers : ["last 2 versions"],
                cascade  : false
            }))
            .pipe(gulp.dest(app_paths.volt + "mailing/css"))
            .pipe(livereload());
}

/**
 * Bundle Mailing
 * Compile layouts, pages, and partials into flat HTML files
 * Then parse using Inky templates
 */
function bundleMailing() {

    return gulp.src(app_paths.mailing + "pages/*.html")
            .pipe(panini({
                root     : app_paths.mailing + "pages",
                layouts  : app_paths.mailing + "layouts",
                partials : app_paths.mailing + "partials",
                helpers  : app_paths.mailing + "helpers"
            }))
            .pipe(inky())
            //replace special string parse data
            .pipe(replacer("${", "{{"))
            .pipe(replacer("&apos;", "'"))
            .pipe(replacer("&quot;", "\""))
            .pipe(replacer("<trans>", "{{ trans._(\""))
            .pipe(replacer("</trans>", "\") }}"))
            //rename
            .pipe(rename({ extname : ".volt" }))
            .pipe(gulp.dest(app_paths.volt + "mailing"))
            .pipe(livereload());
}
