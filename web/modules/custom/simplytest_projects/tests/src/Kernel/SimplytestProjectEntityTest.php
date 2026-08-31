<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\Exception\EntityValidationException;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;

/**
 * @group simplytest
 * @group simplytest_project
 *
 * @coversDefaultClass \Drupal\simplytest_projects\Entity\SimplytestProject
 */
final class SimplytestProjectEntityTest extends KernelTestBase {

  protected static $modules = [
    'simplytest_projects',
    'simplytest_projects_test',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
  }

  /**
   * @covers ::label
   * @covers ::getShortname
   * @covers ::getType
   * @covers ::getCreator
   * @covers ::isSandbox
   * @covers ::getTimestamp
   */
  public function testFullProjectAccessors(): void {
    $project = $this->createProject([
      'title' => 'Token',
      'shortname' => 'token',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
      'creator' => 'dries',
    ]);

    self::assertEquals('Token', $project->label());
    self::assertEquals('token', $project->getShortname());
    self::assertEquals(ProjectTypes::MODULE, $project->getType());
    self::assertEquals('dries', $project->getCreator());
    self::assertFalse($project->isSandbox());
    self::assertGreaterThan(0, $project->getTimestamp());
  }

  /**
   * @covers ::getGitUrl
   * @covers ::getGitWebUrl
   * @covers ::getProjectUrl
   */
  public function testFullProjectUrls(): void {
    $project = $this->createProject([
      'title' => 'Token',
      'shortname' => 'token',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
    ]);

    self::assertEquals('http://cgit.drupalcode.org/token', $project->getGitUrl());
    self::assertEquals('http://git.drupal.org/project/token.git', $project->getGitWebUrl());
    self::assertEquals('https://www.drupal.org/project/token', $project->getProjectUrl());
  }

  /**
   * @covers ::getGitUrl
   * @covers ::getGitWebUrl
   * @covers ::getProjectUrl
   * @covers ::getCreatorEscaped
   */
  public function testSandboxProjectUrls(): void {
    $project = $this->createProject([
      'title' => 'A sandbox',
      'shortname' => 'a_sandbox',
      'sandbox' => "1",
      'type' => ProjectTypes::MODULE,
      'creator' => 'some user!',
    ]);

    self::assertTrue($project->isSandbox());
    // Everything outside the allowed character class is stripped.
    self::assertEquals('someuser!', $project->getCreatorEscaped());
    self::assertEquals('http://cgit.drupalcode.org/sandbox-someuser!-a_sandbox', $project->getGitUrl());
    self::assertEquals('http://git.drupal.org/sandbox/someuser!/a_sandbox.git', $project->getGitWebUrl());
    self::assertEquals('https://www.drupal.org/sandbox/someuser!/a_sandbox', $project->getProjectUrl());
  }

  /**
   * @covers ::getVersions
   * @covers ::setVersions
   */
  public function testVersions(): void {
    $project = $this->createProject([
      'title' => 'Token',
      'shortname' => 'token',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
    ]);

    // The map field carries an empty default rather than a missing value.
    self::assertEquals(['' => ''], $project->getVersions());

    $project->setVersions(['8.x-1.9', '8.x-1.10'], ['8.x-1.x']);
    self::assertEquals([
      'tags' => ['8.x-1.9' => '8.x-1.9', '8.x-1.10' => '8.x-1.10'],
      'heads' => ['8.x-1.x' => '8.x-1.x'],
    ], $project->getVersions());
  }

  /**
   * @covers ::preSave
   */
  public function testPreSaveRejectsDuplicateShortname(): void {
    $this->createProject([
      'title' => 'Token',
      'shortname' => 'token',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
    ]);

    $duplicate = SimplytestProject::create([
      'title' => 'Token again',
      'shortname' => 'token',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
    ]);

    try {
      $duplicate->save();
      self::fail('Expected the duplicate shortname to be rejected.');
    }
    catch (\Exception $e) {
      $validation_exception = $e instanceof EntityValidationException ? $e : $e->getPrevious();
      self::assertInstanceOf(EntityValidationException::class, $validation_exception);
      self::assertStringContainsString('already exists', implode('', $validation_exception->getViolationMessages()));
    }
  }

  /**
   * @param array<string, mixed> $values
   */
  private function createProject(array $values): SimplytestProject {
    $project = SimplytestProject::create($values);
    $project->save();
    return $project;
  }

}
