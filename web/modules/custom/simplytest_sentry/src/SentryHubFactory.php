<?php

declare(strict_types=1);

namespace Drupal\simplytest_sentry;

use Sentry\ClientBuilder;
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Integration\ExceptionListenerIntegration;
use Sentry\Integration\IntegrationInterface;
use Sentry\State\Hub;
use Sentry\State\HubInterface;

/**
 * Builds the Sentry hub the logger reports through.
 *
 * Everything comes from the environment, so no DSN is in configuration and no
 * environment reports unless it was given one. Lagoon sets SENTRY_DSN on
 * production only, which is what keeps sandboxes' own noise and every pull
 * request environment out of the project.
 */
final class SentryHubFactory {

  public static function create(): HubInterface {
    $dsn = getenv('SENTRY_DSN');
    if (!is_string($dsn) || $dsn === '') {
      // A hub with no client drops everything it is given, so the logger does
      // not have to know whether Sentry is configured.
      return new Hub();
    }
    $client = ClientBuilder::create([
      'dsn' => $dsn,
      'environment' => self::env('SENTRY_ENVIRONMENT') ?? self::env('LAGOON_ENVIRONMENT'),
      // Which deploy an error came from. Lagoon has the commit to hand.
      'release' => self::env('SENTRY_RELEASE') ?? self::env('LAGOON_GIT_SHA'),
      'integrations' => self::withoutDuplicateHandlers(...),
    ])->getClient();
    return new Hub($client);
  }

  /**
   * Drops the integrations that would report what Drupal already logs.
   *
   * Drupal writes PHP errors and uncaught exceptions to the log itself, and
   * the logger sends those on, so Sentry's own handlers for them would open a
   * second issue for one problem. The fatal handler stays: a request that runs
   * out of memory never reaches the logger, and that is the failure nobody
   * finds out about otherwise.
   *
   * @param list<IntegrationInterface> $integrations
   *   The default integrations.
   *
   * @return list<IntegrationInterface>
   *   The ones to install.
   */
  private static function withoutDuplicateHandlers(array $integrations): array {
    return array_values(array_filter(
      $integrations,
      static fn (IntegrationInterface $integration): bool => !$integration instanceof ErrorListenerIntegration
        && !$integration instanceof ExceptionListenerIntegration,
    ));
  }

  private static function env(string $name): ?string {
    $value = getenv($name);
    return is_string($value) && $value !== '' ? $value : NULL;
  }

}
