# Simplytest

This is a Composer project for setting up and running Simplytest.

It uses DDEV or Lando for local development, but neither are a requirement.

## Installation

Clone this repo and run `composer install`.

This will install all dependencies, including Drupal core and the Simplytest distribution profile.

The run the following command to install the site locally:

### DDEV install

```
ddev si
```

### Lando install

TBD

## Tests

To run the tests for Simplytests, you can run the following command:

```
composer run tests
```

## Managing configuration

The SimplyTest project leverages Drupal's ability to have a site's configuration
live within the distribution. When making configuration changes, be sure to run Drush
config export to export config to the `config/sync` directory.

## Time limit
Each SimplyTest sandbox lasts 2 hours, via [Tugboat setting](https://git.drupalcode.org/project/tugboat/-/blob/7ffc758424cc0d1ad6d1d4c41ce75f58f075bd5f/src/Cron.php#L32). The lifespan starts when
the sandbox is created, not when it becomes inactive.
