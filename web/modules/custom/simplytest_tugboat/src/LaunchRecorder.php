<?php declare(strict_types=1);

namespace Drupal\simplytest_tugboat;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;

/**
 * Writes one row per launch so we can report on what people evaluate.
 *
 * Recording is best effort. A launch that Tugboat accepted must not turn into
 * an error page because the analytics insert failed, so every write is caught
 * and logged instead of thrown.
 */
final readonly class LaunchRecorder {

  public const string TABLE_NAME = 'simplytest_launch_record';

  /**
   * Tugboat accepted the launch and returned a preview.
   */
  public const string STATUS_LAUNCHED = 'launched';

  /**
   * The launch never reached Tugboat, or Tugboat rejected it.
   */
  public const string STATUS_FAILED = 'failed';

  public function __construct(
    private Connection $database,
    private TimeInterface $time,
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Records a launch Tugboat accepted.
   *
   * @param \Drupal\simplytest_tugboat\LaunchRecord $record
   *   What was launched.
   * @param string $preview_id
   *   The Tugboat preview ID.
   */
  public function recordLaunch(LaunchRecord $record, string $preview_id): void {
    $this->write($record, self::STATUS_LAUNCHED, $preview_id);
  }

  /**
   * Records a launch that never made it to a preview.
   *
   * Without this the one number nobody can reconstruct afterwards is how often
   * launching fails, since a failed launch leaves nothing behind on Tugboat.
   *
   * @param \Drupal\simplytest_tugboat\LaunchRecord $record
   *   What was being launched.
   */
  public function recordFailure(LaunchRecord $record): void {
    $this->write($record, self::STATUS_FAILED, '');
  }

  private function write(LaunchRecord $record, string $status, string $preview_id): void {
    $timestamp = $this->time->getRequestTime();
    try {
      $this->database->insert(self::TABLE_NAME)
        ->fields([
          'created' => $timestamp,
          'created_date' => gmdate('Y-m-d', $timestamp),
          'status' => $status,
          'preview_id' => $preview_id,
          'project' => $record->project,
          'project_type' => $record->projectType,
          'project_version' => $record->projectVersion,
          'core_version' => $record->coreVersion,
          'install_profile' => $record->installProfile,
          'one_click_demo' => $record->oneClickDemo,
          'manual_install' => (int) $record->manualInstall,
          'patch_count' => $record->patchCount,
          'additional_count' => $record->additionalCount,
        ])
        ->execute();
    }
    catch (\Throwable $e) {
      $this->logger->error('Could not record the launch of @project: @message', [
        '@project' => $record->project !== '' ? $record->project : $record->oneClickDemo,
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
