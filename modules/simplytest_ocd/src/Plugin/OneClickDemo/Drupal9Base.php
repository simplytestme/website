<?php

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

use Drupal\simplytest_ocd\OneClickDemoInterface;

abstract class Drupal9Base implements OneClickDemoInterface {

  /**
   * {@inheritdoc}
   *
   * This is the same code as PreviewConfigGenerator::getSetupCommands,
   * eventually the D7, D8, D9 launcher will use plugins as well. And OCD will
   * be an extension of that or just specific definitions.
   */
  public function getSetupCommands(array $parameters): array {
    $commands[] = 'rm -rf "${DOCROOT}"';
    // @todo drupal/recommended-project minimum stability is now stable
    //    add composer config minimum-stability dev and prefer-stable true
    $commands[] = 'composer -n create-project drupal/recommended-project:^9.0 stm --no-install';
    $commands[] = 'cd stm && composer require --no-update drupal/core-recommended:^9.0';
    $commands[] = 'cd stm && composer require --no-update drupal/core-composer-scaffold:^9.0';
    $commands[] = 'cd stm && composer require --dev --no-update drupal/dev-dependencies:dev-default';
    $commands[] = 'cd stm && composer require --no-update drush/drush:^10.0';
    $commands[] = 'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"';
    return $commands;
  }

}
