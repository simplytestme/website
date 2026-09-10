<?php

declare(strict_types=1);

namespace Drupal\simplytest_tugboat;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Reports on how the base previews are holding up.
 *
 * The daily rebuild is the only thing that runs a one click demo's install
 * commands end to end, which makes it the site's smoke test for them. Nothing
 * looked at the result: a build that fails is deleted by the pruner and
 * retried the next day, so a demo that stops building degrades quietly until
 * someone launches it and watches the build fail. This turns that into a log
 * entry, which is the only place an operator can be told from.
 *
 * The check runs before the pruner, because deleting a failed build takes the
 * only evidence that it failed with it. What is left after that is still
 * caught: a base nothing has replaced in two cycles is stale, and a base with
 * no usable build at all is missing.
 *
 * @phpstan-import-type Preview from BasePreviewManager
 */
final readonly class BasePreviewHealth {

  /**
   * The state key holding the last report per base name.
   */
  private const string REPORTED = 'simplytest_tugboat.base_preview_health_reported';

  /**
   * Preview states that mean the build did not finish.
   */
  private const array FAILED_STATES = ['failed', 'cancelled'];

  public function __construct(
    private BasePreviewManager $basePreviews,
    private StateInterface $state,
    private TimeInterface $time,
    private DateFormatterInterface $dateFormatter,
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Logs an error for every base that sandboxes cannot rely on.
   *
   * @param int $lifetime
   *   How long a set of bases is used before it is rebuilt. A base that no
   *   newer one has replaced in two of those has stopped rebuilding. A base
   *   is reported at most once per lifetime, unless what is wrong with it
   *   changes, so a base that stays broken does not fill the log.
   *
   * @return array<string, \Drupal\simplytest_tugboat\BasePreviewStatus>
   *   The status of every base, keyed by base name.
   */
  public function report(int $lifetime): array {
    $now = $this->time->getRequestTime();
    /** @var array<string, array{status: string, time: int}> $reported */
    $reported = $this->state->get(self::REPORTED, []);
    $statuses = [];

    foreach ($this->basePreviews->inventory() as $name => $previews) {
      $usable = BasePreviewManager::usableIn($previews);
      $status = $this->statusOf($previews, $usable, $now, $lifetime * 2);
      $statuses[$name] = $status;

      if ($status === BasePreviewStatus::Ok) {
        unset($reported[$name]);
        continue;
      }
      $previous = $reported[$name] ?? NULL;
      if ($previous !== NULL && $previous['status'] === $status->value && $now - $previous['time'] < $lifetime) {
        continue;
      }
      $reported[$name] = ['status' => $status->value, 'time' => $now];
      $this->log($name, $status, $this->ageOf($usable, $now));
    }

    $this->state->set(self::REPORTED, $reported);
    return $statuses;
  }

  /**
   * Judges one base from its previews, newest first.
   *
   * @param list<Preview> $previews
   *   Every preview carrying the base name.
   * @param Preview|null $usable
   *   The newest preview a sandbox could build on, if there is one.
   */
  private function statusOf(array $previews, ?array $usable, int $now, int $stale_after): BasePreviewStatus {
    if ($usable === NULL) {
      return BasePreviewStatus::Missing;
    }
    // The newest build having failed says the rebuild is broken now, even
    // though an older base is keeping launches working.
    if (in_array($previews[0]['state'], self::FAILED_STATES, TRUE)) {
      return BasePreviewStatus::Failed;
    }
    $age = $this->ageOf($usable, $now);
    if ($age !== NULL && $age > $stale_after) {
      return BasePreviewStatus::Stale;
    }
    return BasePreviewStatus::Ok;
  }

  /**
   * The age of a preview in seconds, or NULL without a usable timestamp.
   *
   * @param Preview|null $preview
   *   The preview to measure.
   */
  private function ageOf(?array $preview, int $now): ?int {
    if ($preview === NULL) {
      return NULL;
    }
    $created = strtotime($preview['createdAt']);
    return $created === FALSE ? NULL : $now - $created;
  }

  private function log(string $name, BasePreviewStatus $status, ?int $age): void {
    $message = match ($status) {
      BasePreviewStatus::Missing => 'Base preview @name has no usable build. Sandboxes and one click demos for it build from scratch, and fail the same way the build did.',
      BasePreviewStatus::Failed => 'The latest build of base preview @name failed. Launches keep using the base built @age ago until the next rebuild succeeds.',
      BasePreviewStatus::Stale => 'Base preview @name has not been replaced in @age. Its rebuild is not finishing.',
      BasePreviewStatus::Ok => throw new \LogicException('A healthy base preview is not reported.'),
    };
    $this->logger->error($message, [
      '@name' => $name,
      '@age' => $age === NULL ? 'an unknown time' : $this->dateFormatter->formatInterval($age),
    ]);
  }

}
