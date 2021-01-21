<?php

namespace Drupal\simplytest_ocd;

/**
 * Defines an interface for simplytest_ocd plugins.
 */
interface OneClickDemoInterface {

  public function getSetupCommands(array $parameters): array;
  public function getDownloadCommands($parameters): array;
  public function getPatchingCommands($parameters): array;
  public function getInstallingCommands($parameters): array;

}
