const mix = require("laravel-mix");

mix
  .disableNotifications()
  .js("web/themes/simplytest_theme/lib/app.js", "web/themes/simplytest_theme/dist").react()
  // eslint-disable-next-line global-require
  .postCss("web/themes/simplytest_theme/lib/tailwind.pcss", "web/themes/simplytest_theme/dist", [require("tailwindcss")])
  .sourceMaps()
  .webpackConfig({
    devtool: "source-map",
    externals: {
      jquery: "jQuery",
      drupal: "Drupal"
    }
  })
  .browserSync({
    proxy: "simplytestme.ddev.site:80",
    files: [
      "web/themes/simplytest_theme/dist/tailwind.css",
      "web/themes/simplytest_theme/dist/app.js",
      "web/themes/simplytest_theme/simplytest.theme",
      "web/themes/simplytest_theme/templates/**/*",
      "web/themes/simplytest_theme/lib/**/*",
      "web/modules/**/*"
    ]
  });
