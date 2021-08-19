/**
 * @file
 * Task: Gulp.
 * Default gulp task
 * @param gulp
 */

module.exports = function (gulp) {
  'use strict';

  // Watch
  gulp.task('default', gulp.series(
    'postcss',
    'watch'
  ));
};
