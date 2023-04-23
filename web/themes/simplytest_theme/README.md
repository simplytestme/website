# Working with the Simplytest Theme

## Updated install using Laravel Mix

Laravel Mix is used to wrap around Webpack and support browser sync whenever files are modified.

```
npm install
npm run watch
```


## Installing the Theme Dependencies
- `cd` to `/themes/simplytest_theme/`
- make sure you are using Node 10. Other versions may work, but the theme has been tested with Node 10. To use this, we have a `.nvmrc` file. Run `nvm use` to use set your version of Node to 10. You must [have NVM installed](https://github.com/nvm-sh/nvm) on your computer to do this.
- run `npm install` - this will get everything we need that is listed in `package.json`

## Running Gulp Tasks
- run `gulp` to start the general task runner - compile PostCSS and JS files.
- run `gulp build` to compile to production-ready CSS.
- run `gulp build:css` to compile just the CSS.

### Available Gulp Tasks
* js-lint
* watch
* build -- One time compilation & linting of CSS & JS files. Included tasks:
  *  js-lint
* default: Running gulp lints files and watches for changes. Included tasks:
  * watch
