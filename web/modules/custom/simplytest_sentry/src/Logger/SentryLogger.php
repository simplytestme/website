<?php

declare(strict_types=1);

namespace Drupal\simplytest_sentry\Logger;

use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * Forwards logged errors to Sentry.
 *
 * Only errors and worse are sent. Everything a sandbox does wrong is a warning
 * or a notice, and Lagoon Logs already keeps all of it; what belongs in Sentry
 * is the handful of things somebody has to go and fix.
 */
final readonly class SentryLogger implements LoggerInterface {

  use RfcLoggerTrait;

  public function __construct(
    private HubInterface $hub,
    private LogMessageParserInterface $parser,
  ) {
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $level
   * @param array<string, mixed> $context
   */
  #[\Override]
  public function log($level, \Stringable|string $message, array $context = []): void {
    // Drupal hands loggers an RFC 5424 severity, where the lower number is the
    // worse problem.
    if (!is_int($level) || $level > RfcLogLevel::ERROR) {
      return;
    }

    $template = (string) $message;
    $rendered = $template;
    // Takes both by reference: the placeholders it returns are the ones it
    // removed from the context.
    $placeholders = $this->parser->parseMessagePlaceholders($rendered, $context);
    $rendered = strtr($rendered, $placeholders);

    $channel = isset($context['channel']) && is_string($context['channel']) ? $context['channel'] : 'php';
    $exception = $context['exception'] ?? NULL;
    $extra = $this->scalars($context + $placeholders);
    $severity = $level <= RfcLogLevel::CRITICAL ? Severity::fatal() : Severity::error();

    $this->hub->withScope(function (Scope $scope) use ($channel, $template, $rendered, $extra, $exception, $severity): void {
      // Sentry groups on what it is given, and a rendered message carries the
      // values that made it: a base preview name, an age, a project. Grouping
      // on the message before substitution keeps one issue per thing that is
      // wrong, rather than a new one every time it is reported.
      $scope->setFingerprint([$channel, $template]);
      // The channel is what an alert rule can be written against.
      $scope->setTag('channel', $channel);
      $scope->setExtras($extra);

      if ($exception instanceof \Throwable) {
        $scope->setExtra('drupal_message', $rendered);
        $this->hub->captureException($exception);
        return;
      }
      $this->hub->captureMessage($rendered, $severity);
    });
  }

  /**
   * Drops everything from a context that is not worth serializing.
   *
   * Drupal's context carries the exception and, through it, whatever the
   * exception holds: an HTTP client error drags in the request, the response,
   * and the handler stack. Sending only scalars keeps one logged error from
   * turning into a fatal inside the reporter.
   *
   * @param array<string, mixed> $values
   *   The context to reduce.
   *
   * @return array<string, scalar>
   *   Every scalar in it.
   */
  private function scalars(array $values): array {
    return array_filter($values, is_scalar(...));
  }

}
