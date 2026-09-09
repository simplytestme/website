<?php

declare(strict_types=1);

namespace Drupal\simplytest_tugboat;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\tugboat\TugboatClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Creates and retires the base previews sandboxes build on.
 *
 * Base previews used to be built from branches in the backing Git repository,
 * one branch per base, and finding them meant matching the branch name. Now
 * they are created through the Tugboat API with generated config and a name.
 * The name is the only thing the launch code needs, so the repository has
 * nothing left to manage.
 *
 * A base is never rebuilt in place. A rebuild creates a new preview under the
 * same name, launches keep using the old one until the new one is usable, and
 * the old one is deleted once nothing builds on it anymore.
 *
 * @phpstan-type Preview array{id: string, name: string, state: string, createdAt: string, children: list<string>}
 */
final readonly class BasePreviewManager {

  /**
   * Core major versions that get a base preview.
   */
  private const array MAJOR_VERSIONS = [7, 8, 9, 10, 11];

  /**
   * The prefix every base preview name carries.
   *
   * It matches the branch names the bases used to be built from, so the bases
   * already on Tugboat keep working during the changeover.
   */
  private const string NAME_PREFIX = 'base-';

  /**
   * Preview states a sandbox cannot build on.
   *
   * Anything else is treated as usable. Tugboat has more states than these,
   * and a base sitting in one of them is still a better starting point than
   * no base at all.
   */
  private const array UNUSABLE_STATES = [
    'new',
    'pending',
    'building',
    'rebuilding',
    'refreshing',
    'failed',
    'cancelled',
  ];

  private ImmutableConfig $tugboatSettings;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    private TugboatClient $tugboatClient,
    private PreviewConfigGenerator $previewConfigGenerator,
    private OneClickDemoPluginManager $oneClickDemoManager,
    private LoggerInterface $logger,
  ) {
    $this->tugboatSettings = $config_factory->get('tugboat.settings');
  }

  /**
   * The names of every base preview the site expects to exist.
   *
   * @return list<string>
   */
  public function names(): array {
    $names = array_map(static fn (int $major): string => "drupal$major", self::MAJOR_VERSIONS);
    // Plugin discovery order follows the filesystem, so the demos are sorted
    // to keep the order the same everywhere.
    $demos = array_column($this->oneClickDemoManager->getDefinitions(), 'base_preview_name');
    sort($demos);
    return array_values(array_unique([...$names, ...$demos]));
  }

  /**
   * Finds the base preview a sandbox should build on.
   *
   * @return string|null
   *   The preview ID, or NULL when no usable base exists.
   */
  public function findUsable(string $name): ?string {
    return self::usableIn($this->previewsNamed($name, $this->allPreviews()));
  }

  /**
   * Creates a fresh preview for a base.
   *
   * @return string
   *   The new preview ID.
   */
  public function rebuild(string $name): string {
    $response = $this->tugboatClient->requestWithApiKey('POST', 'previews', [
      'repo' => $this->tugboatSettings->get('repository_id'),
      'ref' => $this->tugboatSettings->get('repository_base') ?: 'master',
      'type' => 'branch',
      'name' => self::NAME_PREFIX . $name,
      'base' => 'none',
      'config' => $this->previewConfigGenerator->basePreview($name),
    ]);
    $body = Json::decode((string) $response->getBody());
    $this->logger->info('Building base preview @name as @id.', [
      '@name' => $name,
      '@id' => $body['preview'],
    ]);
    return $body['preview'];
  }

  /**
   * Creates a fresh preview for every base.
   *
   * One base failing to start does not stop the others. Tugboat rejecting a
   * request is logged, and that base is picked up by the next rebuild.
   *
   * @return array<string, string|null>
   *   The new preview ID per base name, or NULL where Tugboat refused.
   */
  public function rebuildAll(): array {
    $started = [];
    foreach ($this->names() as $name) {
      try {
        $started[$name] = $this->rebuild($name);
      }
      catch (GuzzleException $e) {
        $this->logger->error('Tugboat refused to build base preview @name: @message', [
          '@name' => $name,
          '@message' => $e->getMessage(),
        ]);
        $started[$name] = NULL;
      }
    }
    return $started;
  }

  /**
   * Deletes base previews that a newer usable one has replaced.
   *
   * A replaced base is only deleted once no sandbox builds on it. Sandboxes
   * expire on their own, so this catches up within a lifetime of the newer
   * base becoming usable. Failed builds are deleted right away.
   *
   * @return list<string>
   *   The IDs of the previews that were deleted.
   */
  public function prune(): array {
    $deleted = [];
    $all = $this->allPreviews();
    foreach ($this->names() as $name) {
      $previews = $this->previewsNamed($name, $all);
      $current = self::usableIn($previews);
      foreach ($previews as $preview) {
        if ($preview['id'] === $current) {
          continue;
        }
        $failed = in_array($preview['state'], ['failed', 'cancelled'], TRUE);
        // A build still in progress is the next current base, not a leftover.
        $in_progress = !$failed && in_array($preview['state'], self::UNUSABLE_STATES, TRUE);
        if ($in_progress || (!$failed && $preview['children'] !== [])) {
          continue;
        }
        $this->tugboatClient->requestWithApiKey('DELETE', "previews/{$preview['id']}", [
          // Required for anything Tugboat considers a base, which this was.
          'force' => TRUE,
        ]);
        $this->logger->info('Deleted base preview @name @id (@state).', [
          '@name' => $name,
          '@id' => $preview['id'],
          '@state' => $preview['state'],
        ]);
        $deleted[] = $preview['id'];
      }
    }
    return $deleted;
  }

  /**
   * Every preview carrying a base name, newest first.
   *
   * @return array<string, list<Preview>>
   *   Previews keyed by base name.
   */
  public function inventory(): array {
    $inventory = [];
    $all = $this->allPreviews();
    foreach ($this->names() as $name) {
      $inventory[$name] = $this->previewsNamed($name, $all);
    }
    return $inventory;
  }

  /**
   * The previews carrying a base name, newest first.
   *
   * @param list<Preview> $all
   *
   * @return list<Preview>
   */
  private function previewsNamed(string $name, array $all): array {
    $wanted = self::NAME_PREFIX . $name;
    $previews = array_values(array_filter(
      $all,
      static fn (array $preview): bool => $preview['name'] === $wanted,
    ));
    usort($previews, static fn (array $a, array $b): int => strcmp($b['createdAt'], $a['createdAt']));
    return $previews;
  }

  /**
   * The newest usable preview in a list sorted newest first.
   *
   * @param list<Preview> $previews
   */
  private static function usableIn(array $previews): ?string {
    foreach ($previews as $preview) {
      if (!in_array($preview['state'], self::UNUSABLE_STATES, TRUE)) {
        return $preview['id'];
      }
    }
    return NULL;
  }

  /**
   * Every preview in the repository, reduced to the fields this class reads.
   *
   * @return list<Preview>
   */
  private function allPreviews(): array {
    $repository_id = $this->tugboatSettings->get('repository_id');
    $response = $this->tugboatClient->requestWithApiKey('GET', "repos/$repository_id/previews");
    $previews = Json::decode((string) $response->getBody());
    return array_map(static fn (array $preview): array => [
      'id' => (string) $preview['id'],
      // A preview built from a branch is named after the branch, which is how
      // the bases built the old way still match.
      'name' => (string) ($preview['name'] ?? ''),
      // A suspended preview reports the state it was suspended in, which is
      // how a failed build shows up: suspended, from failed.
      'state' => (string) ($preview['suspended'] ?? $preview['state'] ?? 'ready'),
      'createdAt' => (string) ($preview['createdAt'] ?? ''),
      'children' => array_values($preview['children'] ?? []),
    ], $previews);
  }

}
