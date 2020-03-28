<?php

namespace Drupal\simplytest_tugboat;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining an instance status entity type.
 */
interface StmTugboatInstanceStatusInterface extends ContentEntityInterface {

  /**
   * Gets the instance status creation timestamp.
   *
   * @return int
   *   Creation timestamp of the instance status.
   */
  public function getCreatedTime();

  /**
   * Sets the instance status creation timestamp.
   *
   * @param int $timestamp
   *   The instance status creation timestamp.
   *
   * @return \Drupal\simplytest_tugboat\StmTugboatInstanceStatusInterface
   *   The called instance status entity.
   */
  public function setCreatedTime($timestamp);

}
