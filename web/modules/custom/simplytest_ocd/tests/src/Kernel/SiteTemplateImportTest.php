<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_ocd\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_ocd\Controller\Resources;
use Drupal\simplytest_ocd\Hook\SiteTemplateCron;
use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\simplytest_ocd\Plugin\Derivative\SiteTemplateDeriver;
use Drupal\simplytest_ocd\SiteTemplateImporter;
use Drupal\simplytest_ocd\SiteTemplateRepository;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_ocd\Plugin\OneClickDemo\SiteTemplate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * The curated list, from the fetch through to a launchable plugin.
 */
#[CoversClass(SiteTemplateImporter::class)]
#[CoversClass(SiteTemplateRepository::class)]
#[CoversClass(SiteTemplateDeriver::class)]
#[CoversClass(SiteTemplateCron::class)]
#[Group('simplytest')]
#[Group('simplytest_ocd')]
#[RunTestsInSeparateProcesses]
final class SiteTemplateImportTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'tugboat',
    'simplytest_projects',
    'simplytest_projects_test',
    'simplytest_ocd',
    'simplytest_tugboat',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
    $this->config('tugboat.settings')->set('repository_id', 'kerneltestrepo')->save();
  }

  private function importer(): SiteTemplateImporter {
    return $this->container->get(SiteTemplateImporter::class);
  }

  private function repository(): SiteTemplateRepository {
    return $this->container->get(SiteTemplateRepository::class);
  }

  private function demos(): OneClickDemoPluginManager {
    return $this->container->get('plugin.manager.oneclickdemo');
  }

  /**
   * A site starts with nothing, and that is not an error.
   */
  public function testNothingIsStoredBeforeTheFirstImport(): void {
    self::assertSame([], $this->repository()->all());
    self::assertArrayNotHasKey('site_template:byte', $this->demos()->getDefinitions());
  }

  /**
   * The entries that can be launched are kept, the rest are not.
   */
  public function testImportKeepsWhatCanBeLaunched(): void {
    self::assertSame(3, $this->importer()->import());

    $templates = $this->repository()->all();
    self::assertSame(['byte', 'forma', 'nuxt'], array_keys($templates));
    self::assertSame('Byte', $templates['byte']->name);
    self::assertSame('drupal/byte', $templates['byte']->package);
    // The paid one, the one on another repository, and the two malformed ones.
    self::assertArrayNotHasKey('premium_thing', $templates);
    self::assertArrayNotHasKey('elsewhere', $templates);
    self::assertArrayNotHasKey('no_name', $templates);
    self::assertArrayNotHasKey('bad_package', $templates);
  }

  /**
   * A second import replaces the list rather than adding to it.
   *
   * A template withdrawn upstream has to stop being offered.
   */
  public function testImportReplacesTheStoredList(): void {
    $this->repository()->save([]);
    $this->importer()->import();
    self::assertCount(3, $this->repository()->all());

    // Only the entries the fixture still has survive a re-import.
    $this->importer()->import();
    self::assertSame(['byte', 'forma', 'nuxt'], array_keys($this->repository()->all()));
  }

  /**
   * A list that cannot be read leaves the stored one alone.
   *
   * An empty picker is worse than a stale one.
   *
   * @param string $response
   *   The mocked response shape.
   */
  #[DataProvider('badResponses')]
  public function testABadListKeepsTheStoredOne(string $response): void {
    self::assertSame(3, $this->importer()->import());

    $this->container->get('state')->set('simplytest_ocd.site_templates_response', $response);
    self::assertSame(-1, $this->importer()->import());
    self::assertSame(['byte', 'forma', 'nuxt'], array_keys($this->repository()->all()));
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function badResponses(): iterable {
    yield 'empty file' => ['empty'];
    yield 'not yaml' => ['garbage'];
    yield 'nothing launchable' => ['unusable'];
  }

  /**
   * Each imported template becomes a plugin that shares the Drupal CMS base.
   */
  public function testImportedTemplatesBecomePlugins(): void {
    $this->importer()->import();
    $this->demos()->clearCachedDefinitions();

    $definitions = $this->demos()->getDefinitions();
    self::assertArrayHasKey('site_template:byte', $definitions);
    self::assertArrayHasKey('site_template:forma', $definitions);
    // The base plugin itself is not launchable; only its derivatives are.
    self::assertArrayNotHasKey('site_template', $definitions);

    $byte = $definitions['site_template:byte'];
    self::assertSame('site_template:byte', $byte['id']);
    self::assertSame('Byte', (string) $byte['title']);
    self::assertSame('site_template', $byte['group']);
    self::assertSame(SiteTemplate::BASE_PREVIEW, $byte['base_preview_name']);
    // A shared base cannot be cloned: the clone would be the base.
    self::assertFalse($byte['clone_base']);
  }

  /**
   * Templates stay out of the demo tiles and answer on their own endpoint.
   */
  public function testTemplatesAreNotDemoTiles(): void {
    $this->importer()->import();
    $this->demos()->clearCachedDefinitions();

    $tiles = Json::decode((string) Resources::create($this->container)->info()->getContent());
    self::assertSame(
      ['starshot', 'oneclickdemo_commerce', 'oneclickdemo_umami'],
      array_column($tiles, 'id'),
    );

    $cards = Json::decode((string) Resources::create($this->container)->siteTemplates()->getContent());
    self::assertSame(
      ['site_template:byte', 'site_template:forma', 'site_template:nuxt'],
      array_column($cards, 'id'),
    );
    self::assertSame([
      'id' => 'site_template:byte',
      'name' => 'Byte',
      'description' => 'Designed for a SaaS product website, this template includes landing pages, a blog, newsletter sign up and other features.',
      'screenshot' => 'https://git.drupalcode.org/project/byte/-/raw/1.x/screenshot.webp?ref_type=heads',
      'creator' => 'Drupal CMS',
      'links' => [
        ['text' => 'Demo', 'url' => 'https://1-x-example.tugboatqa.com/'],
        ['text' => 'Learn more', 'url' => 'https://new.drupal.org/site-template/byte'],
      ],
    ], $cards[0]);
  }

  /**
   * The endpoint is routed and public, like the demo one.
   */
  public function testTheEndpointIsRouted(): void {
    $this->importer()->import();
    $this->demos()->clearCachedDefinitions();

    $request = Request::create('/site-templates');
    $request->headers->set('Accept', 'application/json');
    $response = $this->container->get('http_kernel')->handle($request);

    self::assertSame(200, $response->getStatusCode());
    self::assertCount(3, Json::decode((string) $response->getContent()));
  }

  /**
   * Each template has a permalink that shows it without launching it.
   */
  public function testATemplateHasAPermalink(): void {
    $this->importer()->import();
    $this->demos()->clearCachedDefinitions();

    self::assertSame(
      '/template/byte',
      Url::fromRoute('simplytest_ocd.site_template', ['machine_name' => 'byte'])->toString(),
    );

    $resources = Resources::create($this->container);
    self::assertSame('Byte', $resources->siteTemplateTitle('byte'));

    $build = $resources->siteTemplate('byte');
    $card = $build['mount']['#attached']['drupalSettings']['siteTemplate'];
    // The page shows the same card as the picker.
    $cards = Json::decode((string) $resources->siteTemplates()->getContent());
    self::assertSame($cards[0], $card);
    // A new import can change the template, so the page must follow it.
    self::assertContains('oneclickdemo', $build['#cache']['tags']);

    // Viewing the page did not start a build.
    self::assertNull($this->container->get('state')->get('https://api.tugboatqa.com/v3/previews'));
  }

  /**
   * A permalink for a template that is not in the curated list is a 404.
   *
   * The 404 carries the plugin cache tag, so a link published before its
   * template is imported starts working after the next import.
   */
  public function testAnUnknownTemplatePermalinkIsNotFound(): void {
    $this->importer()->import();
    $this->demos()->clearCachedDefinitions();

    try {
      Resources::create($this->container)->siteTemplate('nope');
      self::fail('An unknown template should not render.');
    }
    catch (CacheableNotFoundHttpException $e) {
      self::assertSame('nope is not a site template', $e->getMessage());
      self::assertContains('oneclickdemo', $e->getCacheTags());
    }

    // The demo tiles are not site templates, so they have no permalink.
    $this->expectException(CacheableNotFoundHttpException::class);
    Resources::create($this->container)->siteTemplate('oneclickdemo_umami');
  }

  /**
   * A template launches through the same route the demo tiles use.
   *
   * The plugin ID of a derivative carries a colon, and that has to survive
   * being put in a URL path.
   */
  public function testATemplateLaunchesThroughTheDemoRoute(): void {
    $this->importer()->import();
    $this->demos()->clearCachedDefinitions();

    $request = Request::create('/one-click-demos/' . rawurlencode('site_template:byte'), 'POST');
    $request->headers->set('Accept', 'application/json');
    $response = $this->container->get('http_kernel')->handle($request);

    self::assertSame(200, $response->getStatusCode());
    $data = Json::decode((string) $response->getContent());
    self::assertSame('OK', $data['status']);
  }

  /**
   * The build commands name the template's own package and recipe.
   */
  public function testTheLaunchRequiresTheTemplateAndInstallsItsRecipe(): void {
    $this->importer()->import();
    $this->demos()->clearCachedDefinitions();

    $generator = $this->container->get('simplytest_tugboat.preview_config_generator');
    $commands = $generator->oneClickDemo('site_template:nuxt', [])['services']['php']['commands']['build'];

    // The recipe directory is the package name, not the key in the list.
    self::assertContains('cd "${TUGBOAT_ROOT}/stm" && composer require drupal/lupus_decoupled_starter --no-ansi', $commands);
    self::assertContains(
      'cd "${DOCROOT}" && ../vendor/bin/drush si ../recipes/lupus_decoupled_starter --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y --site-name=\'Nuxt Starter\'',
      $commands,
    );
    // A launch that starts from the base must not update off its lock file.
    self::assertNotContains('cd stm && composer update --no-ansi', $commands);
    // It reuses the base rather than building Drupal CMS again.
    self::assertStringContainsString('Reusing Drupal CMS from the base preview', implode("\n", $commands));
  }

  /**
   * The shared base is built once the first template exists.
   */
  public function testTheSharedBaseIsExpectedOnlyOnceTemplatesExist(): void {
    $bases = $this->container->get('simplytest_tugboat.base_preview_manager');
    self::assertNotContains(SiteTemplate::BASE_PREVIEW, $bases->names());

    $this->importer()->import();
    $this->demos()->clearCachedDefinitions();

    // Every template shares it, so it appears once however many there are.
    self::assertSame(
      [SiteTemplate::BASE_PREVIEW],
      array_values(array_filter(
        $bases->names(),
        static fn (string $name): bool => $name === SiteTemplate::BASE_PREVIEW,
      )),
    );
  }

  /**
   * Cron imports the list, then leaves it alone for a day.
   */
  public function testCronImportsAtMostOncePerLifetime(): void {
    $state = $this->container->get('state');
    $cron = $this->container->get(SiteTemplateCron::class);

    $cron->cron();
    $first = (int) $state->get(SiteTemplateImporter::IMPORTED);
    self::assertGreaterThan(0, $first);
    self::assertCount(3, $this->repository()->all());

    // Within the lifetime nothing is fetched again.
    $state->set('simplytest_ocd.site_templates_response', 'garbage');
    $cron->cron();
    self::assertSame($first, (int) $state->get(SiteTemplateImporter::IMPORTED));
    self::assertCount(3, $this->repository()->all());
  }

}
