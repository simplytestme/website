/**
 * @file
 * Task: Compile: postcss.
 */


 var path = postcssCustomProperties = require('postcss-custom-properties'),
 using = require('gulp-using'),
 nested = require('postcss-nested'),
 partials = require("postcss-partial-import"),
 cssImport = require('postcss-import'),
 postcssCustomMedia = require('postcss-custom-media'),
 autoprefixer = require('autoprefixer'),
 sourcemaps = require('gulp-sourcemaps'),
 postcss = require('gulp-postcss'),
 cleanCSS = require('gulp-clean-css')



module.exports = function (gulp, options, plugins) {
    'use strict';

    gulp.task('postcss', function () {
        var plugins = [
        partials({
            prefix: '_',
            extension: '.css'
        }),
        postcssCustomMedia(),
        cssImport(),
        postcssCustomProperties({
            preserve: false
        }),
        nested(),
        autoprefixer({
            overrideBrowserslist: ['last 2 version']
        })
        ];
        return gulp
        .pipe(sourcemaps.init())
        .pipe(using({prefix: 'Styles update 👉'}))
        .pipe(postcss(plugins))
        .on('error', function(errorInfo) { // if the error event is triggered, do something
            console.log(errorInfo.toString()); // show the error information
            this.emit('end'); // tell the gulp that the task is ended gracefully and resume
        })
        .pipe(cleanCSS({
            compatibility: 'ie8',
            format: 'beautify'
        }))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(options.css.cssFiles))
    });

};
