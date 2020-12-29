const mix = require('laravel-mix');
const tailwindcss = require('tailwindcss');
require('laravel-mix-purgecss');

mix.disableNotifications()
  .react('lib/app.js', 'dist')
  .postCss('lib/tailwind.pcss', 'dist')
  .options({
    processCssUrls: false,
    postCss: [
      tailwindcss('./tailwind.config.js'),
    ],
  })
  .purgeCss({
    enabled: mix.inProduction(),
    content: [
      'templates/**/*.twig',
      'lib/**/*.js',
    ]
  })
  .sourceMaps()
  .webpackConfig({
    devtool: "source-map",
    externals: {
      jquery: "jQuery",
      drupal: "Drupal",
    }
  })
  .browserSync({
    proxy: 'simplytestme.ddev.site:80',
    files: [
      'dist/tailwind.css',
      'dist/app.js',
      'simplytest.theme',
      'templates/**/*',
      'lib/**/*',
      '../../modules/**/*',
    ]
  });
