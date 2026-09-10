<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_tugboat\LaunchRecorder;
use Drupal\simplytest_tugboat\LaunchStatistics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversClass(LaunchStatistics::class)]
#[CoversMethod(LaunchStatistics::class, 'getTotal')]
#[CoversMethod(LaunchStatistics::class, 'getTopProjects')]
#[CoversMethod(LaunchStatistics::class, 'topBy')]
#[CoversMethod(LaunchStatistics::class, 'getTopOneClickDemos')]
#[CoversMethod(LaunchStatistics::class, 'getTopCoreVersions')]
#[CoversMethod(LaunchStatistics::class, 'getTopInstallProfiles')]
#[CoversMethod(LaunchStatistics::class, 'getProjectTypes')]
#[CoversMethod(LaunchStatistics::class, 'getDailyTotals')]
#[CoversMethod(LaunchStatistics::class, 'getFirstRecordedAt')]
#[Group('simplytest')]
#[Group('simplytest_tugboat')]
#[RunTestsInSeparateProcesses]
final class LaunchStatisticsTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'tugboat',
    'simplytest_ocd',
    'simplytest_projects',
    'simplytest_tugboat',
  ];

  private int $now;

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('simplytest_tugboat', LaunchRecorder::TABLE_NAME);
    $this->now = $this->container->get('datetime.time')->getRequestTime();
  }

  public function testGetTotalCountsOnlySuccessfulLaunches(): void {
    $this->record('token', daysAgo: 1);
    $this->record('token', daysAgo: 1, status: LaunchRecorder::STATUS_FAILED);

    self::assertEquals(1, $this->statistics()->getTotal());
  }

  public function testGetTotalWindowsByDays(): void {
    $this->record('token', daysAgo: 1);
    $this->record('token', daysAgo: 3);
    $this->record('token', daysAgo: 20);
    $this->record('token', daysAgo: 90);

    $statistics = $this->statistics();
    self::assertEquals(2, $statistics->getTotal(7));
    self::assertEquals(3, $statistics->getTotal(30));
    self::assertEquals(4, $statistics->getTotal());
  }

  public function testGetTopProjectsOrdersByCount(): void {
    $this->record('token', daysAgo: 1);
    $this->record('token', daysAgo: 2);
    $this->record('token', daysAgo: 3);
    $this->record('webform', daysAgo: 1);
    $this->record('webform', daysAgo: 2);
    $this->record('ctools', daysAgo: 1);

    self::assertEquals(
      [
        ['name' => 'token', 'total' => 3],
        ['name' => 'webform', 'total' => 2],
        ['name' => 'ctools', 'total' => 1],
      ],
      $this->statistics()->getTopProjects(30, 10)
    );
  }

  public function testGetTopProjectsRespectsTheLimit(): void {
    $this->record('token', daysAgo: 1);
    $this->record('webform', daysAgo: 1);
    $this->record('ctools', daysAgo: 1);

    self::assertCount(2, $this->statistics()->getTopProjects(30, 2));
  }

  /**
   * One click demos leave the project columns empty and must not be grouped.
   */
  public function testGetTopProjectsSkipsOneClickDemos(): void {
    $this->record('token', daysAgo: 1);
    $this->recordOneClickDemo('umami', daysAgo: 1);
    $this->recordOneClickDemo('commerce', daysAgo: 1);

    self::assertEquals(
      [['name' => 'token', 'total' => 1]],
      $this->statistics()->getTopProjects(30, 10)
    );
  }

  /**
   * Demos are counted by plugin ID, and normal launches stay out of the list.
   */
  public function testGetTopOneClickDemos(): void {
    $this->record('token', daysAgo: 1);
    $this->recordOneClickDemo('starshot', daysAgo: 1);
    $this->recordOneClickDemo('starshot', daysAgo: 2);
    $this->recordOneClickDemo('commerce', daysAgo: 1);
    $this->recordOneClickDemo('umami', daysAgo: 40);

    self::assertEquals(
      [
        ['name' => 'starshot', 'total' => 2],
        ['name' => 'commerce', 'total' => 1],
      ],
      $this->statistics()->getTopOneClickDemos(30, 10)
    );
  }

  public function testOtherBreakdowns(): void {
    $this->record('token', daysAgo: 1, coreVersion: '10.3.0', installProfile: 'standard');
    $this->record('webform', daysAgo: 1, coreVersion: '10.3.0', installProfile: 'umami');
    $this->record('olivero', daysAgo: 1, coreVersion: '11.0.0', installProfile: 'standard', projectType: ProjectTypes::THEME);

    $statistics = $this->statistics();
    self::assertEquals(
      [['name' => '10.3.0', 'total' => 2], ['name' => '11.0.0', 'total' => 1]],
      $statistics->getTopCoreVersions(30, 10)
    );
    self::assertEquals(
      [['name' => 'standard', 'total' => 2], ['name' => 'umami', 'total' => 1]],
      $statistics->getTopInstallProfiles(30, 10)
    );
    self::assertEquals(
      [['name' => ProjectTypes::MODULE, 'total' => 2], ['name' => ProjectTypes::THEME, 'total' => 1]],
      $statistics->getProjectTypes(30)
    );
  }

  /**
   * Quiet days are filled in so a caller can chart the window directly.
   */
  public function testGetDailyTotalsFillsQuietDays(): void {
    $this->record('token', daysAgo: 0);
    $this->record('token', daysAgo: 0);
    $this->record('token', daysAgo: 2);

    $daily = $this->statistics()->getDailyTotals(4);

    self::assertCount(4, $daily);
    self::assertEquals(gmdate('Y-m-d', $this->now - (3 * 86400)), $daily[0]['date']);
    self::assertEquals(gmdate('Y-m-d', $this->now), $daily[3]['date']);
    self::assertEquals([0, 1, 0, 2], array_column($daily, 'total'));
  }

  /**
   * A window is whole calendar days, so the chart always sums to the total.
   *
   * A launch seven days ago sits inside a rolling seven times 24 hour window
   * but on the day before a seven day chart starts. Counting it in the total
   * and not in the chart would leave the page disagreeing with itself.
   */
  public function testWindowsAreWholeCalendarDays(): void {
    $this->record('token', daysAgo: 0);
    $this->record('token', daysAgo: 6);
    $this->record('token', daysAgo: 7);

    $statistics = $this->statistics();
    $daily = $statistics->getDailyTotals(7);

    self::assertEquals(2, $statistics->getTotal(7));
    self::assertEquals(2, array_sum(array_column($daily, 'total')));
    self::assertEquals([['name' => 'token', 'total' => 2]], $statistics->getTopProjects(7, 10));
  }

  public function testGetFirstRecordedAt(): void {
    self::assertNull($this->statistics()->getFirstRecordedAt());

    $this->record('token', daysAgo: 5);
    $this->record('token', daysAgo: 40);

    self::assertEquals($this->now - (40 * 86400), $this->statistics()->getFirstRecordedAt());
  }

  /**
   * An empty table reports zeroes rather than failing.
   */
  public function testEmptyTable(): void {
    $statistics = $this->statistics();
    self::assertEquals(0, $statistics->getTotal());
    self::assertEquals([], $statistics->getTopProjects(30, 10));
  }

  private function statistics(): LaunchStatistics {
    return $this->container->get('simplytest_tugboat.launch_statistics');
  }

  private function record(
    string $project,
    int $daysAgo,
    string $status = LaunchRecorder::STATUS_LAUNCHED,
    string $coreVersion = '10.3.0',
    string $installProfile = 'standard',
    string $projectType = ProjectTypes::MODULE,
  ): void {
    $this->insert([
      'project' => $project,
      'project_type' => $projectType,
      'project_version' => '8.x-1.0',
      'core_version' => $coreVersion,
      'install_profile' => $installProfile,
      'one_click_demo' => '',
    ], $daysAgo, $status);
  }

  private function recordOneClickDemo(string $plugin_id, int $daysAgo): void {
    $this->insert([
      'project' => '',
      'project_type' => '',
      'project_version' => '',
      'core_version' => '',
      'install_profile' => '',
      'one_click_demo' => $plugin_id,
    ], $daysAgo, LaunchRecorder::STATUS_LAUNCHED);
  }

  /**
   * @param array<string, string> $fields
   */
  private function insert(array $fields, int $daysAgo, string $status): void {
    $created = $this->now - ($daysAgo * 86400);
    $this->container->get('database')->insert(LaunchRecorder::TABLE_NAME)
      ->fields($fields + [
        'created' => $created,
        'created_date' => gmdate('Y-m-d', $created),
        'status' => $status,
        'preview_id' => 'preview-' . $created,
        'manual_install' => 0,
        'patch_count' => 0,
        'additional_count' => 0,
      ])
      ->execute();
  }

}
