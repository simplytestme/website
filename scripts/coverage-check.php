<?php

/**
 * @file
 * Fails the build when line coverage drops below a minimum percentage.
 *
 * PHPUnit 9 has no built-in coverage threshold, so this reads the Clover report
 * it writes and compares the line coverage against the minimum we agreed to
 * hold. Run it after `phpunit --coverage-clover`.
 *
 * Usage:
 *   php scripts/coverage-check.php <clover.xml> <minimum-percentage>
 */

declare(strict_types=1);

/**
 * Line coverage totals for a single file or the whole project.
 */
final readonly class CoverageMetrics {

  public function __construct(
    public string $name,
    public int $statements,
    public int $coveredStatements,
  ) {
  }

  public function percentage(): float {
    // A file with no executable statements is vacuously covered. Reporting 0%
    // for interfaces and plain value objects would punish good design.
    if ($this->statements === 0) {
      return 100.0;
    }
    return ($this->coveredStatements / $this->statements) * 100;
  }

  public function uncovered(): int {
    return $this->statements - $this->coveredStatements;
  }

}

/**
 * Reads the totals we care about out of a Clover report.
 */
final readonly class CloverReport {

  /**
   * @param \CoverageMetrics $project
   *   Totals across every measured file.
   * @param list<\CoverageMetrics> $files
   *   Per-file totals, so a failure can name the worst offenders.
   */
  private function __construct(
    public CoverageMetrics $project,
    public array $files,
  ) {
  }

  public static function fromFile(string $path): self {
    if (!is_file($path)) {
      throw new RuntimeException("Coverage report '$path' does not exist. Did PHPUnit run with --coverage-clover?");
    }
    $xml = simplexml_load_file($path);
    if ($xml === FALSE) {
      throw new RuntimeException("Coverage report '$path' is not valid XML.");
    }
    if (!isset($xml->project->metrics)) {
      throw new RuntimeException("Coverage report '$path' has no project metrics.");
    }

    $files = [];
    // php-code-coverage nests a namespaced class's <file> inside a <package>
    // element; only global-namespace files sit directly under <project>.
    foreach ($xml->xpath('//project//file') ?: [] as $file) {
      $metrics = $file->metrics;
      $files[] = new CoverageMetrics(
        (string) $file['name'],
        (int) $metrics['statements'],
        (int) $metrics['coveredstatements'],
      );
    }

    $project_metrics = $xml->project->metrics;
    return new self(
      new CoverageMetrics(
        'project',
        (int) $project_metrics['statements'],
        (int) $project_metrics['coveredstatements'],
      ),
      $files,
    );
  }

  /**
   * Returns the least covered files, worst first.
   *
   * @return list<\CoverageMetrics>
   */
  public function worstFiles(int $limit): array {
    $files = $this->files;
    usort($files, static fn(CoverageMetrics $a, CoverageMetrics $b) => $b->uncovered() <=> $a->uncovered());
    return array_slice(array_filter($files, static fn(CoverageMetrics $file) => $file->uncovered() > 0), 0, $limit);
  }

}

$report_path = $argv[1] ?? '';
$minimum = (float) ($argv[2] ?? 0);
if ($report_path === '' || $minimum <= 0) {
  fwrite(STDERR, "Usage: php scripts/coverage-check.php <clover.xml> <minimum-percentage>\n");
  exit(1);
}

try {
  $report = CloverReport::fromFile($report_path);
}
catch (RuntimeException $e) {
  fwrite(STDERR, $e->getMessage() . "\n");
  exit(1);
}

$actual = $report->project->percentage();
printf(
  "Line coverage: %.2f%% (%d/%d statements), minimum %.2f%%\n",
  $actual,
  $report->project->coveredStatements,
  $report->project->statements,
  $minimum,
);

// Compare the rounded value so a run reported as "85.00%" is never rejected for
// being 84.999% behind the decimal point.
if (round($actual, 2) >= $minimum) {
  echo "Coverage threshold met.\n";
  exit(0);
}

fwrite(STDERR, sprintf("Coverage %.2f%% is below the required %.2f%%.\n\n", $actual, $minimum));
fwrite(STDERR, "Least covered files:\n");
$root = dirname(__DIR__) . '/';
foreach ($report->worstFiles(15) as $file) {
  fwrite(STDERR, sprintf(
    "  %6.2f%%  %4d uncovered  %s\n",
    $file->percentage(),
    $file->uncovered(),
    str_replace($root, '', $file->name),
  ));
}
exit(1);
