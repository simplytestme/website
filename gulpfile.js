var gulp = require('gulp'),
  using = require('gulp-using'),
  postcss = require('gulp-postcss'),
  sourcemaps = require('gulp-sourcemaps'),
  postcssCustomProperties = require('postcss-custom-properties'),
  nested = require('postcss-nested'),
  partials = require("postcss-partial-import"),
  cssImport = require('postcss-import'),
  postcssCustomMedia = require('postcss-custom-media'),
  autoprefixer = require('autoprefixer'),
  cleanCSS = require('gulp-clean-css'),
  plumber = require('gulp-plumber'),
  gulpStylelint = require('gulp-stylelint'),
  mixin = require('postcss-mixins')

// Variables for folder path.
var paths = {
  styles: {
    source: 'web/themes/simplytest_theme/postcss/',
    destination: 'web/themes/simplytest_theme/dist/css/'
  }
};

// Lint CSS files.
gulp.task('lint:css', function () {
  return gulp.src(paths.styles.source + '/*.css')
    .pipe(plumber())
    .pipe(gulpStylelint({
      reporters: [{
        formatter: 'string',
        console: true
      }]
    }))
    .pipe(plumber.stop());
});

// Lint CSS files and throw an error for a CI to catch.
gulp.task('lint:css-with-fail', function () {
  return gulp.src(paths.styles.source + '/*.css')
    .pipe(plugins.gulpStylelint({
      reporters: [{
        formatter: 'string',
        console: true,
        failAfterError: true
      }]
    }));
});

// Fix CSS linting errors.
gulp.task('lint:css-fix', function () {
  return gulp.src(paths.styles.source + '/*.css')
    .pipe(gulpStylelint({
      fix: true
    }))
    .pipe(gulp.dest(paths.styles.source));
});

// Build CSS files.
gulp.task('build:css', function () {
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
    mixin(),
    autoprefixer({
      overrideBrowserslist: ['last 2 version']
    })
  ];
  return gulp.src(paths.styles.source + 'styles.css')
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
    .pipe(gulp.dest(paths.styles.destination))
});

// Watch CSS.
gulp.task('watch:css', function () {
  gulp.watch(paths.styles.source, gulp.series('build:css'));
});
