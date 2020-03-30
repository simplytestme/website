---
title: Working with the Simplytest Theme
---

## Installing the Theme Dependencies
- `cd` to `/themes/simplytest_theme/`
- run `npm install` - this will get everything we need that is listed in `package.json`
- `cd` to `/themes/simplytest_theme/react`
- run `npm install` - this will get everything needed from the `package.json` file that is in this directory

## Running Gulp Tasks
- run `gulp` to start the general task runner - compile SASS and JS files.
- run `gulp build` to compile to production-ready CSS.
- run `gulp sass` to compile just the SASS.


## Building the React Section of the Theme
### Development Version
- run `npm start` to start a development server, with a development version of react. This will also include live-reloading for any changes you make. You can see the theme at `localhost:3000`

### Production Version
- run `npm build` - this will build the theme for us, based on the corresponding `build` command in this directories `package.json`, which will build a production-ready version of the theme and add it to a directory called `build`.