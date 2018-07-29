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
  public function isSandbox();

  /**
   * @return string
   */
  public function getCreator();

  /**
   * The project creator's name with special characters removed.
   *
   * @return string
   */
  public function getCreatorEscaped();

  /**
   * @return string
   */
  public function getType();

  /**
   * @return array
   */
  public function getVersions();

  /**
   * @param array $tags
   * @param array $heads
   */
  public function setVersions($tags, $heads);

  /**
   * @return int
   */
  public function getTimestamp();

  /**
   * The project's code repository Url.
   *
   * @return string
   */
  public function getGitUrl();

  /**
   * The project's cgit url.
   *
   * @return string
   */
  public function getGitWebUrl();

  /**
   * The project's drupal.org url.
   *
   * @return string
   */
  public function getProjectUrl();

}
