<?php

declare(strict_types=1);

namespace Drupal\simplytest_tugboat\Exception;

/**
 * Thrown when a client has launched as many sandboxes as the limit allows.
 */
final class LaunchLimitExceededException extends \RuntimeException {

  /**
   * @param int $retryAfter
   *   Seconds until the client can be sure the limit has cleared.
   */
  public function __construct(public readonly int $retryAfter) {
    parent::__construct('Too many sandboxes were launched from your network. Try again later.');
  }

}
