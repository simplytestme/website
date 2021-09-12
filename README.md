# Simplytest Development Environment

This is a Composer project for setting up and running Simplytest for develoment.

It uses Gitpod or DDEV or Lando for local development, but neither are a requirement.

## Easiest - develop Simplytest in your browser

Click on the button below -

[![Open in Gitpod](https://gitpod.io/button/open-in-gitpod.svg)](https://gitpod.io/from-referrer/)

(When using this for the first time, you will be asked to login to Gitpod using your Github account)
<br>

In less than a minute - you will have a full Drupal development environment in your browser.
You can run drush commands, xdebug, and even use PHPStorm. Simplytestme's CSS and JS files are watched and will be compiled when updated.

![Developing Simplytestme with Gitpod](https://user-images.githubusercontent.com/22901/114256346-b36f1100-9986-11eb-81f2-d9bb63864822.jpg)
### Useful commands:

**Open Simplytestme website in a new tab**

```sh
gp preview $(gp url 8080) --external
```

**Use PHPStorm as IDE**
<br>
Run the command below in terminal, click on "Open Browser" message that will pop-up, and follow the steps in the new browser tab.

```sh
phpstorm
```

**xdebug**

```sh
ddev xdebug on
```

**Running drush commands**

```sh
ddev drush cr
```

<br>

To contribute, create a branch, fork Simplytest using Github's CLI (already installed)

```sh
gh repo fork --clone=false --remote=true
```

push your changes, and create a PR against the main repo.

<br>
<hr>

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
