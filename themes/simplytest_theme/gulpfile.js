
/* -------------------------------
 Available Tasks
 ------------------------------- */
// gulp                     -- Lints js & sass, and watches sass for changes.
// gulp build               -- Lints JS & Sass, compiles sass and JS.
// gulp sass                -- Compiles Sass Files.
// gulp sass-lint           -- Lints Sass Files.
// gulp watch               -- Lints & Watches sass changes .

var gulp = require('gulp');

var plugins = require('gulp-load-plugins')({
  pattern: ['*', 'gulp-*', '@*/gulp{-,.}*'],
  rename: {
    'gulp-autoprefixer': 'prefix',
    'gulp-group-css-media-queries': 'gcmq',
    'gulp-sass-glob': 'sassglob',
    'gulp-eslint': 'eslint',
    'postcss-clean': 'clean',
    'gulp-sourcemaps': 'sourcemaps'
  }
});

// Directories
var sassFiles = 'sass/**/*.scss';
var cssDir = 'css';
var jsDir = 'js/src/*.js';
var optimizedJSDir = 'js/*.js'

'use strict';
var options = {

  //-------- SASS ---------------
  sass: {
    sassFiles: sassFiles
  },

  //--------- CSS ---------------
  css: {
    cssFiles: cssDir
  },

  //--------- JS ---------------
  js: {
    jsFiles: jsDir,
    optimizedDir: optimizedJSDir,
  }
};


// Tasks
require('./gulp-tasks/sass')(gulp, options, plugins);
require('./gulp-tasks/sass-lint')(gulp, options, plugins);
require('./gulp-tasks/js-lint')(gulp, options, plugins);
require('./gulp-tasks/watch')(gulp, options, plugins);
require('./gulp-tasks/build')(gulp, options, plugins);
require('./gulp-tasks/default')(gulp, options, plugins);