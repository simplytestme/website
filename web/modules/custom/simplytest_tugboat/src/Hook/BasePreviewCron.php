<?php

declare(strict_types=1);

namespace Drupal\simplytest_tugboat\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\ProceduralCall;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\Core\State\StateInterface;
use Drupal\simplytest_tugboat\BasePreviewHealth;
use Drupal\simplytest_tugboat\BasePreviewManager;
use GuzzleHttp\Exception\ConnectException;
use Psr\Log\LoggerInterface;

/**
 * Keeps the Tugboat base previews fresh.
 */
final readonly class BasePreviewCron {

  /**
   * How long a set of base previews is used before being rebuilt.
   */
  public const int LIFETIME = 86400;

  /**
   * The state key holding when the base previews were last rebuilt.
   */
  public const string REBUILT = 'simplytest_tugboat.base_previews_rebuilt';

  public function __construct(
    private BasePreviewManager $basePreviews,
    private BasePreviewHealth $health,
    private StateInterface $state,
    private TimeInterface $time,
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Implements hook_cron().
   *
   * The Tugboat module's own cron deletes every preview older than the sandbox
   * lifetime that Tugboat does not report as an anchor. A base preview is not
   * an anchor -- an anchor is the repository's default base, and these are
   * named previews picked per launch -- so two hours into a base's day that
   * sweep started trying to delete it. It failed with a 409 while a sandbox was
   * built on the base and succeeded the rest of the time, which is why the demo
   * bases went first: a demo launch is a clone, and Tugboat counts a clone
   * separately from a base's children.
   *
   * Sandboxes now carry an expiry Tugboat enforces itself, and prune() retires
   * the bases, so there is nothing left for that sweep to do.
   */
  #[Hook('cron')]
  #[RemoveHook('cron', ProceduralCall::class, 'tugboat_cron')]
  public function cron(): void {
    // Only production owns the base previews. The Tugboat token reaches every
    // Lagoon environment, and site install runs cron, so without this each PR
    // environment and every local install would start its own set of builds.
    if (getenv('LAGOON_ENVIRONMENT_TYPE') !== 'production') {
      return;
    }

    $now = $this->time->getRequestTime();

    try {
      // Reported before pruning: deleting a failed build takes the only
      // evidence that it failed with it.
      $this->health->report(self::LIFETIME);
      $this->basePreviews->prune();
    }
    catch (ConnectException $e) {
      // Reading the bases means asking Tugboat for every preview in the
      // repository, and that request times out often enough to matter. Nothing
      // here is worth waking somebody over: the next run does the same work,
      // and a rebuild is not started against a Tugboat that is not answering.
      $this->logger->warning('Tugboat did not answer in time. The base previews are left alone this run: @message', [
        '@message' => $e->getMessage(),
      ]);
      return;
    }

    $rebuilt = (int) $this->state->get(self::REBUILT, 0);
    if ($now - $rebuilt < self::LIFETIME) {
      return;
    }
    // Record the attempt before building. A base that fails to build is retried
    // on the next cycle, not on every cron run, so a broken build cannot pile
    // previews up on Tugboat.
    $this->state->set(self::REBUILT, $now);
    $this->basePreviews->rebuildAll();
  }

}
