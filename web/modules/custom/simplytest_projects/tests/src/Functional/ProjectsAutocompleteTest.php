<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * @group simplytest
 * @group simplytest_projects
 */
final class ProjectsAutocompleteTest extends BrowserTestBase {

  protected $profile = 'simplytest';
  protected static $modules = [
    'simplytest_projects'
  ];

  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    $this->markTestSkipped('Moved to Cypress due to Functional test problems with output HTTP requests');
    parent::setUp();
  }

  public function testAutoImport() {
    $test_queries = [
      'Pathauto' => [
        'sandbox' => 0,
        'shortname' => 'pathauto',
        'title' => 'Pathauto',
        'type' => 'Module',
      ],
      'Password Policy' => [
        'sandbox' => '0',
        'shortname' => 'password_policy',
        'title' => 'Password Policy',
        'type' => 'Module',
      ],
      'token' => [
        'sandbox' => '0',
        'shortname' => 'token',
        'title' => 'Token',
        'type' => 'Module',
      ],
      'Bootstrap' => [
        'sandbox' => '0',
        'shortname' => 'bootstrap',
        'title' => 'Bootstrap',
        'type' => 'Theme',
      ],
    ];

    foreach ($test_queries as $sample_project_query => $expected_project_data) {
      $autocomplete_results = $this->drupalGet(Url::fromRoute('simplytest_projects.projects', [], [
        'query' => [
          'string' => $sample_project_query,
        ],
      ]));
      $this->assertSession()->statusCodeEquals(200);
      $data = Json::decode($autocomplete_results);
      self::assertEquals([$expected_project_data], $data, "Expected data for: {$sample_project_query}");
    }

    // Test searching for Password Policy again to ensure there isn't a double
    // import.
    $autocomplete_results = $this->drupalGet(Url::fromRoute('simplytest_projects.projects', [], [
      'query' => [
        'string' => 'Password Pol',
      ],
    ]));
    $this->assertSession()->statusCodeEquals(200);
    $data = Json::decode($autocomplete_results);
    self::assertEquals([
      [
        'sandbox' => '0',
        'shortname' => 'password_policy',
        'title' => 'Password Policy',
        'type' => 'Module',
      ]
    ], $data, 'Expected data for: Password Pol');

  }

}
