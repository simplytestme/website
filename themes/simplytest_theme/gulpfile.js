
/* -------------------------------
 Available Tasks
 ------------------------------- */
// gulp                     -- Lints js & sass, watches sass, starts up and watches the PL server.
// gulp build               -- Task for frontend:build. Lints JS & Sass, compiles sass & generates patternlab.
// gulp sass                -- Compiles Sass Files.
// gulp sass-lint           -- Lints Sass Files.
// gulp patternlab:watch    -- Starts the PatternLab servers and watches tasks.
// gulp patternlab:generate -- Generates PatternLab.
// gulp watch               -- Lints & Watches sass changes .

var gulp    = require('gulp');

var plugins = require('gulp-load-plugins')({
  pattern: ['*', 'gulp-*', '@*/gulp{-,.}*'],
  rename: {
    'gulp-autoprefixer': 'prefix',
    'css-mqpacker'     : 'mqpacker',
    'gulp-sass-glob'   : 'sassglob'
  }
});

// Directories
var sassFiles       = 'sass/**/*.scss';
var cssDir          = 'css';
var jsDir           = 'js/*.js';

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
    jsFiles: jsDir
  }
};


// Tasks
require('./gulp-tasks/default')(gulp, options, plugins);
require('./gulp-tasks/sass')(gulp, options, plugins);
require('./gulp-tasks/sass-lint')(gulp, options, plugins);
require('./gulp-tasks/js-lint')(gulp, options, plugins);
require('./gulp-tasks/watch')(gulp, options, plugins);
require('./gulp-tasks/build')(gulp, options, plugins);