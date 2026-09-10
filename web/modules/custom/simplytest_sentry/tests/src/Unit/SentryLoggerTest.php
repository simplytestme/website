<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_sentry\Unit;

use Drupal\Core\Logger\LogMessageParser;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\simplytest_sentry\Logger\SentryLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Severity;
use Sentry\State\Hub;

/**
 * Covers what the logger hands to Sentry.
 */
#[CoversClass(SentryLogger::class)]
#[Group('simplytest')]
#[Group('simplytest_sentry')]
final class SentryLoggerTest extends TestCase {

  private RecordingTransport $transport;

  private SentryLogger $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->transport = new RecordingTransport();
    // No integrations: they register global error handlers, and this is about
    // what the logger puts in the payload.
    $client = ClientBuilder::create([
      'dsn' => 'https://examplePublicKey@o0.ingest.sentry.io/0',
      'default_integrations' => FALSE,
    ])
      ->setTransport($this->transport)
      ->getClient();
    $this->sut = new SentryLogger(new Hub($client), new LogMessageParser());
  }

  /**
   * A message is sent rendered, but grouped on the template behind it.
   */
  public function testSendsRenderedMessagesGroupedOnTheirTemplate(): void {
    $this->sut->log(RfcLogLevel::ERROR, 'The latest build of base preview @name failed after @age.', [
      'channel' => 'simplytest_tugboat',
      '@name' => 'starshot',
      '@age' => '2 hours',
      'uid' => 0,
    ]);

    $event = $this->lastEvent();
    self::assertEquals('The latest build of base preview starshot failed after 2 hours.', $event->getMessage());
    self::assertEquals(Severity::error(), $event->getLevel());
    // Grouped on the message before substitution, so reporting the same base
    // again tomorrow lands in the same issue instead of opening a new one.
    self::assertEquals(
      ['simplytest_tugboat', 'The latest build of base preview @name failed after @age.'],
      $event->getFingerprint(),
    );
    // What an alert rule can be written against.
    self::assertEquals(['channel' => 'simplytest_tugboat'], $event->getTags());
    // The values that made the message are kept, so the issue says which base.
    self::assertEquals('starshot', $event->getExtra()['@name']);
    self::assertEquals(0, $event->getExtra()['uid']);
  }

  /**
   * An exception is sent as one, with a trace Sentry can read.
   */
  public function testSendsExceptionsWithTheirTrace(): void {
    $this->sut->log(RfcLogLevel::ERROR, '%type: @message', [
      'channel' => 'php',
      '%type' => 'RuntimeException',
      '@message' => 'Tugboat is unreachable',
      'exception' => new \RuntimeException('Tugboat is unreachable'),
    ]);

    $event = $this->lastEvent();
    $exceptions = $event->getExceptions();
    self::assertCount(1, $exceptions);
    self::assertEquals(\RuntimeException::class, $exceptions[0]->getType());
    self::assertEquals('Tugboat is unreachable', $exceptions[0]->getValue());
    // The rendered message is kept alongside it, since Drupal's is the one
    // that says which operation failed.
    self::assertEquals('RuntimeException: Tugboat is unreachable', $event->getExtra()['drupal_message']);
    // The exception itself is never serialized into the payload: it holds
    // whatever it was thrown with, which can be the whole HTTP client.
    self::assertArrayNotHasKey('exception', $event->getExtra());
  }

  /**
   * The worst levels are reported as fatal.
   */
  public function testReportsTheWorstLevelsAsFatal(): void {
    $this->sut->log(RfcLogLevel::CRITICAL, 'The database is gone.', ['channel' => 'php']);
    self::assertEquals(Severity::fatal(), $this->lastEvent()->getLevel());
  }

  /**
   * Anything a person does not have to fix stays out of Sentry.
   */
  public function testIgnoresEverythingBelowError(): void {
    $this->sut->log(RfcLogLevel::WARNING, 'Project @name has no releases.', ['channel' => 'simplytest_projects']);
    $this->sut->log(RfcLogLevel::NOTICE, 'Session opened for @name.', ['channel' => 'user']);
    $this->sut->log(RfcLogLevel::INFO, 'Building base preview @name.', ['channel' => 'simplytest_tugboat']);

    self::assertEquals([], $this->transport->sent());
  }

  /**
   * A message logged outside any channel still reports.
   */
  public function testFallsBackToThePhpChannel(): void {
    $this->sut->log(RfcLogLevel::ERROR, 'Something went wrong.');

    $event = $this->lastEvent();
    self::assertEquals(['channel' => 'php'], $event->getTags());
    self::assertEquals(['php', 'Something went wrong.'], $event->getFingerprint());
  }

  private function lastEvent(): Event {
    $sent = $this->transport->sent();
    self::assertNotEmpty($sent, 'The logger sent nothing to Sentry.');
    return $sent[array_key_last($sent)];
  }

}
