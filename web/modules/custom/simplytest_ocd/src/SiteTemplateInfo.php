<?php declare(strict_types=1);

namespace Drupal\simplytest_ocd;

use Drupal\Component\Utility\UrlHelper;

/**
 * One site template, as Drupal CMS describes it.
 *
 * Drupal CMS keeps a curated list of site templates and calls that list part
 * of its API. This is one entry of it, reduced to what a launch and a template
 * card need, and validated on the way in: the list is fetched from somebody
 * else's repository, so nothing in it is trusted.
 *
 * @see \Drupal\simplytest_ocd\SiteTemplateImporter
 */
final readonly class SiteTemplateInfo {

  /**
   * @param string $machineName
   *   The template's key in the curated list.
   * @param string $name
   *   The human readable name, for the card and the site name.
   * @param string $description
   *   A sentence describing what the template is for.
   * @param string $package
   *   The Composer package that provides the template, as `vendor/name`.
   * @param string $recipe
   *   The directory the recipe installs into, under `recipes/`.
   * @param string|null $screenshot
   *   An absolute URL to a screenshot, or NULL when the entry has none.
   * @param list<array{text: string, url: string}> $links
   *   Informational links: a demo, documentation, the project page.
   * @param string|null $creator
   *   Who made the template.
   */
  public function __construct(
    public string $machineName,
    public string $name,
    public string $description,
    public string $package,
    public string $recipe,
    public ?string $screenshot,
    public array $links,
    public ?string $creator,
  ) {
  }

  /**
   * Builds an instance from one entry of the curated list.
   *
   * @param string $machine_name
   *   The entry's key.
   * @param mixed $values
   *   The entry, straight from the decoded YAML.
   *
   * @return self|null
   *   The template, or NULL when the entry is one this site cannot launch.
   */
  public static function fromCuratedEntry(string $machine_name, mixed $values): ?self {
    if (!is_array($values)) {
      return NULL;
    }
    // The key becomes the derivative half of a plugin ID, and the plugin
    // system splits those on a colon. Anything that is not a machine name
    // would either break discovery or collide with another template.
    if (preg_match('/^[a-z0-9_]+$/', $machine_name) !== 1) {
      return NULL;
    }
    // A paid template needs a license key from the buyer and a Composer
    // repository configured to use it. A sandbox has neither, and offering a
    // card that cannot launch is worse than not offering it.
    if (array_key_exists('purchase', $values)) {
      return NULL;
    }

    $package = $values['package'] ?? NULL;
    // The array form carries a repository, which is how a template is served
    // from somewhere other than drupal.org. Supporting that means configuring
    // Composer per launch, so those are skipped for now.
    if (is_array($package)) {
      if (isset($package['repository'])) {
        return NULL;
      }
      $package = $package['name'] ?? NULL;
    }
    if (!is_string($package) || preg_match('#^[a-z0-9]([a-z0-9._-]*)/[a-z0-9]([a-z0-9._-]*)$#', $package) !== 1) {
      return NULL;
    }
    $name = $values['name'] ?? NULL;
    if (!is_string($name) || trim($name) === '') {
      return NULL;
    }

    $screenshot = $values['screenshot'] ?? NULL;
    if (!is_string($screenshot) || !UrlHelper::isValid($screenshot, TRUE) || !str_starts_with($screenshot, 'https://')) {
      $screenshot = NULL;
    }

    return new self(
      $machine_name,
      trim($name),
      is_string($values['description'] ?? NULL) ? trim($values['description']) : '',
      $package,
      // Composer installs a recipe into `recipes/{$name}`, so the directory is
      // the package name without its vendor, not the key in the curated list.
      explode('/', $package, 2)[1],
      $screenshot,
      self::links($values['links'] ?? []),
      is_string($values['creator'] ?? NULL) ? trim($values['creator']) : NULL,
    );
  }

  /**
   * Keeps the links that are absolute, external and safe to render.
   *
   * @return list<array{text: string, url: string}>
   */
  private static function links(mixed $links): array {
    if (!is_array($links)) {
      return [];
    }
    $kept = [];
    foreach ($links as $key => $link) {
      $url = is_array($link) ? ($link['url'] ?? NULL) : $link;
      if (!is_string($url) || !UrlHelper::isValid($url, TRUE) || !UrlHelper::isExternal($url)) {
        continue;
      }
      $text = is_array($link) ? ($link['text'] ?? NULL) : NULL;
      if (!is_string($text) || trim($text) === '') {
        // The curated list lets the key stand in for the label.
        $text = $key === 'demo' ? 'Demo' : 'Learn more';
      }
      $kept[] = ['text' => trim($text), 'url' => $url];
    }
    return $kept;
  }

  /**
   * @return array<string, mixed>
   */
  public function toArray(): array {
    return [
      'machine_name' => $this->machineName,
      'name' => $this->name,
      'description' => $this->description,
      'package' => $this->package,
      'recipe' => $this->recipe,
      'screenshot' => $this->screenshot,
      'links' => $this->links,
      'creator' => $this->creator,
    ];
  }

  /**
   * @param array<string, mixed> $values
   *   A previously stored array, as returned by ::toArray().
   */
  public static function fromArray(array $values): self {
    return new self(
      (string) $values['machine_name'],
      (string) $values['name'],
      (string) $values['description'],
      (string) $values['package'],
      (string) $values['recipe'],
      $values['screenshot'] === NULL ? NULL : (string) $values['screenshot'],
      $values['links'],
      $values['creator'] === NULL ? NULL : (string) $values['creator'],
    );
  }

}
