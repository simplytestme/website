<?php

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

use Drupal\simplytest_ocd\OneClickDemoInterface;

abstract class Drupal8Base extends OneClickDemoBase  {

  /**
   * {@inheritdoc}
   *
   * This is the same code as PreviewConfigGenerator::getSetupCommands,
   * eventually the D7, D8, D9 launcher will use plugins as well. And OCD will
   * be an extension of that or just specific definitions.
   */
  public function getSetupCommands(array $parameters): array {
    $commands[] = 'cd "${DOCROOT}" && git config core.fileMode false';
    $commands[] = 'cd "${DOCROOT}" && git fetch --all';
    if (substr($parameters['drupal_core_version'], -1) === 'x') {
      $commands[] = sprintf('cd "${DOCROOT}" && git reset --hard origin/%s', $parameters['drupal_core_version']);
    }
    else {
      $commands[] = sprintf('cd "${DOCROOT}" && git reset --hard %s', $parameters['drupal_core_version']);
    }
    return $commands;
  }

}
