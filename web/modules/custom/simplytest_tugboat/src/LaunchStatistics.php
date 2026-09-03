<?php declare(strict_types=1);

namespace Drupal\simplytest_tugboat;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * Aggregate queries over the launch record table.
 *
 * Every query here counts successful launches only. Failed launches are
 * recorded, but a report of "what people launch" should not be padded with
 * sandboxes that never came up.
 *
 * A window of N days means the last N UTC calendar days, today included, so
 * that the headline totals, the breakdowns and the per-day chart all describe
 * the same set of launches.
 *
 * @see \Drupal\simplytest_tugboat\LaunchRecorder
 */
final readonly class LaunchStatistics {

  public function __construct(
    private Connection $database,
    private TimeInterface $time,
  ) {
  }

  /**
   * Counts successful launches, optionally within the last N calendar days.
   *
   * @param int|null $days
   *   How many days back to count, or NULL for every launch on record.
   */
  public function getTotal(?int $days = NULL): int {
    $query = $this->baseQuery();
    if ($days !== NULL) {
      $query->condition('created_date', $this->firstDay($days), '>=');
    }
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Returns the most launched projects, most launched first.
   *
   * @return list<array{name: string, total: int}>
   */
  public function getTopProjects(int $days, int $limit): array {
    return $this->topBy('project', $days, $limit);
  }

  /**
   * Returns the most launched one click demos, most launched first.
   *
   * Names are plugin IDs. A demo that has since been removed still has rows,
   * so the caller decides how to label an ID it no longer recognises.
   *
   * @return list<array{name: string, total: int}>
   */
  public function getTopOneClickDemos(int $days, int $limit): array {
    return $this->topBy('one_click_demo', $days, $limit);
  }

  /**
   * Returns the most used Drupal core releases, most used first.
   *
   * @return list<array{name: string, total: int}>
   */
  public function getTopCoreVersions(int $days, int $limit): array {
    return $this->topBy('core_version', $days, $limit);
  }

  /**
   * Returns the most used install profiles, most used first.
   *
   * @return list<array{name: string, total: int}>
   */
  public function getTopInstallProfiles(int $days, int $limit): array {
    return $this->topBy('install_profile', $days, $limit);
  }

  /**
   * Returns launch counts per project type, largest first.
   *
   * @return list<array{name: string, total: int}>
   */
  public function getProjectTypes(int $days): array {
    return $this->topBy('project_type', $days, 20);
  }

  /**
   * Returns one entry per calendar day, oldest first, including quiet days.
   *
   * Days with no launches are filled in with a zero so that a caller rendering
   * a chart does not have to reconstruct the calendar itself.
   *
   * @return list<array{date: string, total: int}>
   */
  public function getDailyTotals(int $days): array {
    $results = $this->baseQuery();
    $results->addField('r', 'created_date', 'date');
    $results->addExpression('COUNT(*)', 'total');
    $results->condition('created_date', $this->firstDay($days), '>=');
    $results->groupBy('r.created_date');
    $totals = $results->execute()->fetchAllKeyed();

    $today = $this->time->getRequestTime();
    $daily = [];
    for ($offset = $days - 1; $offset >= 0; $offset--) {
      $date = gmdate('Y-m-d', $today - ($offset * 86400));
      $daily[] = [
        'date' => $date,
        'total' => (int) ($totals[$date] ?? 0),
      ];
    }
    return $daily;
  }

  /**
   * Returns the timestamp of the earliest recorded launch, or NULL if none.
   *
   * The report needs this to say how far back the numbers actually go, rather
   * than implying it has always been counting.
   */
  public function getFirstRecordedAt(): ?int {
    $query = $this->baseQuery();
    $query->addField('r', 'created');
    $query->orderBy('created');
    $query->range(0, 1);
    $first = $query->execute()->fetchField();
    return $first === FALSE ? NULL : (int) $first;
  }

  /**
   * Groups successful launches by one column, largest group first.
   *
   * @return list<array{name: string, total: int}>
   */
  private function topBy(string $column, int $days, int $limit): array {
    $query = $this->baseQuery();
    $query->addField('r', $column, 'name');
    $query->addExpression('COUNT(*)', 'total');
    $query->condition('created_date', $this->firstDay($days), '>=');
    // One click demos leave the project columns empty and normal launches
    // leave the demo column empty, so either would otherwise group together
    // under a blank label.
    $query->condition($column, '', '<>');
    $query->groupBy("r.$column");
    $query->orderBy('total', 'DESC');
    $query->orderBy('name');
    $query->range(0, $limit);

    $rows = [];
    foreach ($query->execute() as $row) {
      $rows[] = [
        'name' => (string) $row->name,
        'total' => (int) $row->total,
      ];
    }
    return $rows;
  }

  private function baseQuery(): SelectInterface {
    return $this->database->select(LaunchRecorder::TABLE_NAME, 'r')
      ->condition('status', LaunchRecorder::STATUS_LAUNCHED);
  }

  /**
   * Returns the earliest UTC calendar day inside a window of N days.
   */
  private function firstDay(int $days): string {
    return gmdate('Y-m-d', $this->time->getRequestTime() - (($days - 1) * 86400));
  }

}
