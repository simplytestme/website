<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_sentry\Unit;

use Sentry\Event;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * Keeps the events a client sends, instead of sending them to Sentry.
 */
final class RecordingTransport implements TransportInterface {

  /**
   * @var list<\Sentry\Event>
   */
  private array $sent = [];

  #[\Override]
  public function send(Event $event): Result {
    $this->sent[] = $event;
    return new Result(ResultStatus::success(), $event);
  }

  #[\Override]
  public function close(?int $timeout = NULL): Result {
    return new Result(ResultStatus::success());
  }

  /**
   * @return list<\Sentry\Event>
   */
  public function sent(): array {
    return $this->sent;
  }

}
