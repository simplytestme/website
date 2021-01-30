# Simplytest Development Environment

This is a Composer project for setting up and running Simplytest for develoment.

It uses DDEV for local development, but is not a requirement.

## Installation

Clone this repo and run `composer install`.

This will install all dependencies, including Drupal core and the Simplytest distribution profile.

The run the following command to install the site locally:

```
ddev si
```

## Tests

To run the tests for Simplytests, you can run the following command:

```
composer run tests
```
