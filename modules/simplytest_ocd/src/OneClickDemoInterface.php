<?php

namespace Drupal\simplytest_ocd;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for simplytest_ocd plugins.
 */
interface OneClickDemoInterface extends PluginInspectionInterface {

  public function getSetupCommands(array $parameters): array;
  public function getDownloadCommands($parameters): array;
  public function getPatchingCommands($parameters): array;
  public function getInstallingCommands($parameters): array;

}
