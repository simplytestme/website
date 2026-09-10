<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_sentry\Unit;

use Drupal\simplytest_sentry\SentryHubFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Integration\ExceptionListenerIntegration;
use Sentry\Integration\FatalErrorListenerIntegration;

/**
 * Covers the hub built from the environment.
 */
#[CoversClass(SentryHubFactory::class)]
#[Group('simplytest')]
#[Group('simplytest_sentry')]
final class SentryHubFactoryTest extends TestCase {

  private const string DSN = 'https://examplePublicKey@o0.ingest.sentry.io/0';

  private const array VARIABLES = [
    'SENTRY_DSN',
    'SENTRY_ENVIRONMENT',
    'SENTRY_RELEASE',
    'LAGOON_ENVIRONMENT',
    'LAGOON_GIT_SHA',
  ];

  protected function setUp(): void {
    parent::setUp();
    foreach (self::VARIABLES as $variable) {
      putenv($variable);
    }
  }

  protected function tearDown(): void {
    foreach (self::VARIABLES as $variable) {
      putenv($variable);
    }
    parent::tearDown();
  }

  /**
   * Without a DSN nothing is reported, which is every environment but one.
   */
  public function testReportsNothingWithoutADsn(): void {
    self::assertNull(SentryHubFactory::create()->getClient());

    // An empty variable is the same as no variable: Lagoon hands one over on
    // environments the value was never set for.
    putenv('SENTRY_DSN=');
    self::assertNull(SentryHubFactory::create()->getClient());
  }

  /**
   * The environment and release come from the environment.
   */
  public function testTakesTheEnvironmentAndReleaseFromTheEnvironment(): void {
    putenv('SENTRY_DSN=' . self::DSN);
    putenv('SENTRY_ENVIRONMENT=production');
    putenv('SENTRY_RELEASE=0123abc');

    $client = SentryHubFactory::create()->getClient();

    self::assertNotNull($client);
    self::assertEquals('production', $client->getOptions()->getEnvironment());
    self::assertEquals('0123abc', $client->getOptions()->getRelease());
  }

  /**
   * Lagoon already says which environment and which deploy this is.
   */
  public function testFallsBackToWhatLagoonSets(): void {
    putenv('SENTRY_DSN=' . self::DSN);
    putenv('LAGOON_ENVIRONMENT=main');
    putenv('LAGOON_GIT_SHA=0123abc');

    $client = SentryHubFactory::create()->getClient();

    self::assertNotNull($client);
    self::assertEquals('main', $client->getOptions()->getEnvironment());
    self::assertEquals('0123abc', $client->getOptions()->getRelease());
  }

  /**
   * Sentry does not listen for what Drupal already logs.
   */
  public function testLeavesTheHandlersDrupalAlreadyCoversAlone(): void {
    putenv('SENTRY_DSN=' . self::DSN);

    $client = SentryHubFactory::create()->getClient();

    self::assertNotNull($client);
    // Both of these end up in the log, and the logger sends them on, so
    // listening for them too would open a second issue for one problem.
    self::assertNull($client->getIntegration(ErrorListenerIntegration::class));
    self::assertNull($client->getIntegration(ExceptionListenerIntegration::class));
    // A request that runs out of memory never reaches the log at all.
    self::assertNotNull($client->getIntegration(FatalErrorListenerIntegration::class));
  }

}
