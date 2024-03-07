<?php

namespace Drupal\simplytest_ocd;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for simplytest_ocd plugins.
 */
interface OneClickDemoInterface extends PluginInspectionInterface {

  /**
   * @param array<string, mixed> $parameters
   *
   * @return string[]
   */
  public function getSetupCommands(array $parameters): array;

  /**
   * @param array<string, mixed> $parameters
   *
   * @return string[]
   */
  public function getDownloadCommands(array $parameters): array;

  /**
   * @param array<string, mixed> $parameters
   *
   * @return string[]
   */
  public function getPatchingCommands(array $parameters): array;

  /**
   * @param array<string, mixed> $parameters
   *
   * @return string[]
   */
  public function getInstallingCommands(array $parameters): array;

}
