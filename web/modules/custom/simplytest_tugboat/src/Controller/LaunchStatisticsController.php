<?php declare(strict_types=1);

namespace Drupal\simplytest_tugboat\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\simplytest_tugboat\LaunchStatistics;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The public launch statistics report.
 *
 * @see https://www.drupal.org/project/simplytest/issues/3541515
 */
final readonly class LaunchStatisticsController implements ContainerInjectionInterface {

  /**
   * How far back the breakdowns look.
   */
  private const int WINDOW_DAYS = 30;

  /**
   * How long a rendered report stays fresh.
   *
   * The page is anonymous and sits behind Fastly, so it is cached by time
   * rather than by tag: tagging it would mean every launch invalidated the
   * report, which is the one thing that would make it expensive to serve.
   */
  private const int MAX_AGE = 900;

  public function __construct(
    private LaunchStatistics $statistics,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container): self {
    return new self($container->get('simplytest_tugboat.launch_statistics'));
  }

  /**
   * Builds the report.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  public function report(): array {
    $daily = $this->statistics->getDailyTotals(self::WINDOW_DAYS);

    return [
      '#type' => 'component',
      '#component' => 'simplytest_tugboat:launch-statistics',
      '#props' => [
        'window_days' => self::WINDOW_DAYS,
        'totals' => [
          'week' => $this->statistics->getTotal(7),
          'window' => $this->statistics->getTotal(self::WINDOW_DAYS),
          'all_time' => $this->statistics->getTotal(),
        ],
        'daily' => $daily,
        'daily_peak' => $this->peak($daily),
        'projects' => $this->statistics->getTopProjects(self::WINDOW_DAYS, 20),
        'core_versions' => $this->statistics->getTopCoreVersions(self::WINDOW_DAYS, 10),
        'install_profiles' => $this->statistics->getTopInstallProfiles(self::WINDOW_DAYS, 10),
        'project_types' => $this->statistics->getProjectTypes(self::WINDOW_DAYS),
        'first_recorded_at' => $this->statistics->getFirstRecordedAt(),
      ],
      '#cache' => [
        'max-age' => self::MAX_AGE,
      ],
    ];
  }

  /**
   * Returns the busiest day's total, used to scale the bar chart.
   *
   * @param list<array{date: string, total: int}> $daily
   *   The per-day totals.
   */
  private function peak(array $daily): int {
    $totals = array_column($daily, 'total');
    // max() throws on an empty array, and a zero peak would divide by zero in
    // the template, so floor the scale at one.
    return $totals === [] ? 1 : max(1, max($totals));
  }

}
