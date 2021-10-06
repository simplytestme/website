# Simplytest Development Environment

This is a Composer project for setting up and running Simplytest for develoment.

For convenience, this project is configured to use either DDEV or Lando for
local development. You can use any local development environment you prefer.

## Installation with DDEV

Clone this repo and run `composer install`.

This will install all dependencies, including Drupal core and the Simplytest distribution profile.

The run the following command to install the site locally:

```
ddev si
```

## Installation with Lando

Clone this repo and run `lando start`.

This will download and prepare all dependencies, including Drupal core and the Simplytest distribution profile.

Run `lando si` to install and configure the SimplyTest distribution profile.

If you are working on the theme, then you can run `lando compile` to generate
the CSS and JavaScript assets.

## Tests

To run the tests for Simplytest, you can run the following command:

```
composer run tests
```
