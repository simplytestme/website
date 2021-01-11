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
- - If you have switched Node verion to Node 10, you might need to rebuild node-sass by running `npm rebuild node-sass`
- run `npm install` - this will get everything we need that is listed in `package.json`

## Running Gulp Tasks
- run `gulp` to start the general task runner - compile SASS and JS files.
- run `gulp build` to compile to production-ready CSS.
- run `gulp sass` to compile just the SASS.

### Available Gulp Tasks
* sass
* sass-lint
* js-lint
* watch
* build -- One time compilation & linting of sass & JS files. Included tasks:
  *  js-lint
  *  sass-lint
  *  sass
* default: Running gulp lints files and watches for changes. Included tasks:
  * sass-lint
  * sass
  * watch

## Building the React Section of the Theme

### Development Version
- run `npm start` to start a development server, with a development version of react. This will also include live-reloading for any changes you make. You can see the theme at `localhost:3000`

### Production Version
- run `npm build` - this will build the theme for us, based on the corresponding `build` command in this directories `package.json`, which will build a production-ready version of the theme and add it to a directory called `build`.
