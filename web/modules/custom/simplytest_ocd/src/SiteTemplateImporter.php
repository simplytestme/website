<?php declare(strict_types=1);

namespace Drupal\simplytest_ocd;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Imports Drupal CMS's curated list of site templates.
 *
 * The list is fetched and stored, never read live: the deriver that turns it
 * into launchable plugins runs during plugin discovery, and discovery has no
 * business making an HTTP request. It also means an outage upstream leaves the
 * template picker as it was rather than empty.
 *
 * @see \Drupal\simplytest_ocd\Plugin\Derivative\SiteTemplateDeriver
 */
final readonly class SiteTemplateImporter {

  /**
   * Drupal CMS's curated list.
   *
   * Drupal CMS documents this URL as the canonical location and part of its
   * API, in the header of the file itself.
   *
   * @see https://git.drupalcode.org/project/drupal_cms/-/blob/2.x/drupal_cms_installer/site-templates.yml
   */
  public const string URL = 'https://git.drupalcode.org/api/v4/projects/204857/repository/files/site-templates.yml/raw?ref=HEAD';

  /**
   * The state key holding when the list was last imported.
   */
  public const string IMPORTED = 'simplytest_ocd.site_templates_imported';

  public function __construct(
    private ClientInterface $http,
    private SiteTemplateRepository $repository,
    private StateInterface $state,
    private TimeInterface $time,
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Fetches the list and stores what can be launched.
   *
   * @return int
   *   How many templates were stored, or -1 when the list could not be read
   *   and the stored one was left alone.
   */
  public function import(): int {
    try {
      $body = (string) $this->http->request('GET', self::URL)->getBody();
      $decoded = Yaml::decode($body);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Could not read the site template list: @message', [
        '@message' => $e->getMessage(),
      ]);
      return -1;
    }

    if (!is_array($decoded) || $decoded === []) {
      $this->logger->warning('The site template list was empty or not a list; keeping the stored one.');
      return -1;
    }

    $templates = [];
    $skipped = [];
    foreach ($decoded as $machine_name => $values) {
      if (!is_string($machine_name)) {
        continue;
      }
      $template = SiteTemplateInfo::fromCuratedEntry($machine_name, $values);
      if ($template === NULL) {
        $skipped[] = $machine_name;
        continue;
      }
      $templates[$machine_name] = $template;
    }

    // Every entry being unusable means the file's shape changed under us, not
    // that Drupal CMS withdrew every template. Replacing a good list with an
    // empty one would empty the picker, so don't.
    if ($templates === []) {
      $this->logger->warning('No usable site templates in the list; keeping the stored one.');
      return -1;
    }

    $this->repository->save($templates);
    $this->state->set(self::IMPORTED, $this->time->getRequestTime());

    if ($skipped !== []) {
      // Expected for paid templates and those served from another Composer
      // repository. Worth a line, because it is also how a template that
      // changed shape goes missing.
      $this->logger->info('Skipped @count site template(s) that cannot be launched: @names', [
        '@count' => count($skipped),
        '@names' => implode(', ', $skipped),
      ]);
    }
    return count($templates);
  }

}
