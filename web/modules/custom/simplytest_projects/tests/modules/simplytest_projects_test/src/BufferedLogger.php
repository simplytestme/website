<?php

declare(strict_types=1);

namespace Drupal\simplytest_projects_test;

use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

/**
 * Keeps log messages in memory so a test can assert on them.
 *
 * Registered against the `logger` tag, so it receives everything written to any
 * channel for the duration of the test.
 */
final class BufferedLogger implements LoggerInterface {

  use RfcLoggerTrait;

  /**
   * @var list<string>
   */
  private array $messages = [];

  /**
   * {@inheritdoc}
   *
   * @param mixed $level
   * @param string|\Stringable $message
   * @param array<string, mixed> $context
   */
  #[\Override]
  public function log($level, $message, array $context = []): void {
    // Placeholders are substituted so assertions can match the rendered text.
    $replacements = [];
    foreach ($context as $key => $value) {
      if (!is_scalar($value) && !$value instanceof \Stringable) {
        continue;
      }
      if (str_starts_with($key, '@') || str_starts_with($key, '%') || str_starts_with($key, ':')) {
        $replacements[$key] = (string) $value;
      }
    }
    $this->messages[] = strtr((string) $message, $replacements);
  }

  /**
   * @return list<string>
   */
  public function getMessages(): array {
    return $this->messages;
  }

  public function hasMessageContaining(string $needle): bool {
    foreach ($this->messages as $message) {
      if (str_contains($message, $needle)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
