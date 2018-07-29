<?php

namespace Drupal\simplytest_projects\Entity;

/**
 * Interface SimplytestProjectInterface
 *
 * @package Drupal\simplytest_projects\Entity
 */
interface SimplytestProjectInterface {

  /**
   * The project's machine safe short name.
   *
   * @return string
   */
  public function getShortname();

  /**
   * Whether or not this project is a sandbox project.
   *
   * @return boolean
   */
  public function isSandbox();

  /**
   * The project creator's name.
   *
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
   * The type of drupal project this is.
   *
   * @return string
   */
  public function getType();

  /**
   * Array of tag and head versions in the git repo.
   *
   * @return array
   */
  public function getVersions();

  /**
   * @param array $tags
   * @param array $heads
   */
  public function setVersions($tags, $heads);

  /**
   * Last time this entity was updated.
   *
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
