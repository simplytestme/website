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
   * @var list<array{level: mixed, message: string}>
   */
  private array $records = [];

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
    $this->records[] = [
      'level' => $level,
      'message' => strtr((string) $message, $replacements),
    ];
  }

  /**
   * @return list<string>
   */
  public function getMessages(): array {
    return array_column($this->records, 'message');
  }

  public function hasMessageContaining(string $needle): bool {
    return $this->find($needle) !== NULL;
  }

  /**
   * The severity a message was logged at, or NULL when it was never logged.
   *
   * A logger channel translates a PSR level to its RFC 5424 number before
   * handing it on, so this is a \Drupal\Core\Logger\RfcLogLevel constant.
   */
  public function levelOfMessageContaining(string $needle): mixed {
    return $this->find($needle)['level'] ?? NULL;
  }

  /**
   * @return array{level: mixed, message: string}|null
   */
  private function find(string $needle): ?array {
    foreach ($this->records as $record) {
      if (str_contains($record['message'], $needle)) {
        return $record;
      }
    }
    return NULL;
  }

}
