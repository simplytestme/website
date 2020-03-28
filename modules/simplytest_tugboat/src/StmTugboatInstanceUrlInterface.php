<?php

namespace Drupal\simplytest_tugboat;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining an instanceurl entity type.
 */
interface StmTugboatInstanceUrlInterface extends ContentEntityInterface {

  /**
   * Gets the instanceurl creation timestamp.
   *
   * @return int
   *   Creation timestamp of the instanceurl.
   */
  public function getCreatedTime();

  /**
   * Sets the instanceurl creation timestamp.
   *
   * @param int $timestamp
   *   The instanceurl creation timestamp.
   *
   * @return \Drupal\simplytest_tugboat\StmTugboatInstanceUrlInterface
   *   The called instanceurl entity.
   */
  public function setCreatedTime($timestamp);

}
