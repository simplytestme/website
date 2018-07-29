<?php

namespace Drupal\simplytest_projects\Entity;

interface SimplytestProjectInterface {

  /**
   * @return string
   */
  public function getShortname();

  /**
   * @return boolean
   */
  public function getSandbox();

  /**
   * @return string
   */
  public function getCreator();

  /**
   * @return string
   */
  public function getType();

  /**
   * @return array
   */
  public function getVersions();

  /**
   * @return int
   */
  public function getTimestamp();

}
