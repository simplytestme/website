/**
 * @file
 * Task: Build task for running frontend build. Also used to compile postcss & PL without watching
 * Usage: gulp build
 * @param gulp
 */

module.exports = function (gulp) {
  'use strict';

  // Frontend build
  gulp.task('build', gulp.series(
    'js-lint',
    'postcss'
  ));

};
