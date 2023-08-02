const mix = require("laravel-mix");

mix
  .disableNotifications()
  .js("lib/app.js", "dist").react()
  // eslint-disable-next-line global-require
  .postCss("lib/tailwind.pcss", "dist", [require("tailwindcss")])
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
      "dist/tailwind.css",
      "dist/app.js",
      "simplytest.theme",
      "templates/**/*",
      "lib/**/*",
      "../../modules/**/*"
    ]
  });
