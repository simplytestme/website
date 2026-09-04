<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\Form\ImportForm;
use Drupal\simplytest_projects\Form\Settings;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;

/**
 * @group simplytest
 * @group simplytest_project
 */
final class ProjectFormsTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'simplytest_projects',
    'simplytest_projects_test',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installEntitySchema('user');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
    $this->installConfig(['simplytest_projects']);
  }

  /**
   * @covers \Drupal\simplytest_projects\Form\Settings::buildForm
   * @covers \Drupal\simplytest_projects\Form\Settings::getFormId
   */
  public function testSettingsFormBuild(): void {
    $this->config('simplytest_projects.settings')
      ->set('version_timeout', '-2 hour')
      ->set('blacklisted_projects', ['badproject'])
      ->set('blacklisted_versions', ['^1\\.'])
      ->save();

    $form_object = Settings::create($this->container);
    self::assertEquals('simplytest_projects_admin_settings', $form_object->getFormId());

    $form = $this->container->get('form_builder')->getForm($form_object);
    self::assertEquals('-2 hour', $form['settings_version']['version_timeout']['#default_value']);
    self::assertEquals('badproject', $form['settings_blacklists']['blacklisted_projects']['#default_value']);
    self::assertEquals('^1\\.', $form['settings_blacklists']['blacklisted_versions']['#default_value']);
  }

  /**
   * Blank lines and stray whitespace are trimmed out of the blacklists.
   *
   * @covers \Drupal\simplytest_projects\Form\Settings::submitForm
   * @covers \Drupal\simplytest_projects\Form\Settings::validateForm
   */
  public function testSettingsFormSubmit(): void {
    $form_state = new FormState();
    $form_state->setValues([
      'version_timeout' => '-3 hour',
      'blacklisted_projects' => "  first  \n\n second \n",
      'blacklisted_versions' => "^1\\.\n\n",
    ]);
    $this->container->get('form_builder')->submitForm(Settings::create($this->container), $form_state);

    $config = $this->config('simplytest_projects.settings');
    self::assertEquals('-3 hour', $config->get('version_timeout'));
    self::assertEquals(['first', 'second'], array_values($config->get('blacklisted_projects')));
    self::assertEquals(['^1\\.'], array_values($config->get('blacklisted_versions')));
  }

  /**
   * @covers \Drupal\simplytest_projects\Form\ImportForm::buildForm
   * @covers \Drupal\simplytest_projects\Form\ImportForm::getFormId
   */
  public function testImportFormBuild(): void {
    $form_object = ImportForm::create($this->container);
    self::assertEquals('simplytest_import_form', $form_object->getFormId());

    $form = $this->container->get('form_builder')->getForm($form_object);
    self::assertEquals(
      ['module', 'theme', 'distribution'],
      array_keys($form['type']['#options']),
    );
  }

  /**
   * Submitting the import form seeds Drupal core and queues a batch.
   *
   * @covers \Drupal\simplytest_projects\Form\ImportForm::submitForm
   */
  public function testImportFormSubmit(): void {
    $form_object = ImportForm::create($this->container);
    $form_state = new FormState();
    $form_state->setValues([
      'type' => ['module' => 'module', 'theme' => 0, 'distribution' => 0],
    ]);
    $form = [];
    $form_object->submitForm($form, $form_state);

    // Drupal core is created if it is not already known.
    $storage = $this->container->get('entity_type.manager')->getStorage('simplytest_project');
    $core = $storage->loadByProperties(['shortname' => 'drupal']);
    self::assertCount(1, $core);
    self::assertEquals(ProjectTypes::CORE, reset($core)->getType());

    $batch = &batch_get();
    self::assertNotEmpty($batch['sets'][0]['operations']);

    // Clear the batch so it does not leak into the next test.
    $batch = [];
  }

  /**
   * A project type the importer rejects is surfaced as a form error message.
   *
   * @covers \Drupal\simplytest_projects\Form\ImportForm::submitForm
   */
  public function testImportFormSubmitWithUnsupportedType(): void {
    SimplytestProject::create([
      'title' => 'Drupal core',
      'shortname' => 'drupal',
      'sandbox' => "0",
      'type' => ProjectTypes::CORE,
    ])->save();

    // Calling the handler directly bypasses the `#options` check on the
    // checkboxes element, which would otherwise reject the value first.
    $form_object = ImportForm::create($this->container);
    $form_state = new FormState();
    $form_state->setValues(['type' => ['widget' => 'widget']]);
    $form = [];
    $form_object->submitForm($form, $form_state);

    $messages = $this->container->get('messenger')->messagesByType('error');
    self::assertEquals("The type 'widget' is not allowed.", (string) $messages[0]);
  }

  /**
   * @covers \Drupal\simplytest_projects\Form\SimplytestProjectEntityForm::save
   */
  public function testEntityFormSave(): void {
    $project = SimplytestProject::create([
      'title' => 'Token',
      'shortname' => 'token',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
    ]);

    $form_state = new FormState();
    $form_object = $this->entityForm($project, 'default', $form_state);
    $form = $this->buildForm($form_object, $form_state);

    $form_object->save($form, $form_state);

    self::assertNotNull($form_object->getEntity()->id());
    $messages = $this->container->get('messenger')->messagesByType('status');
    self::assertStringContainsString('Created', (string) $messages[0]);
    self::assertStringContainsString('Simplytest Project', (string) $messages[0]);
    self::assertStringContainsString((string) $form_object->getEntity()->id(), (string) $messages[0]);
    self::assertEquals(
      'entity.simplytest_project.canonical',
      $form_state->getRedirect()->getRouteName(),
    );
  }

  /**
   * @covers \Drupal\simplytest_projects\Form\SimplytestProjectEntityForm::save
   */
  public function testEntityFormSaveExisting(): void {
    $project = SimplytestProject::create([
      'title' => 'Token',
      'shortname' => 'token',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
    ]);
    $project->save();

    $form_state = new FormState();
    $form_object = $this->entityForm($project, 'edit', $form_state);
    $form = $this->buildForm($form_object, $form_state);

    $form_object->save($form, $form_state);

    $messages = $this->container->get('messenger')->messagesByType('status');
    self::assertStringContainsString('Saved', (string) end($messages));
    self::assertStringContainsString('Simplytest Project', (string) end($messages));
  }

  /**
   * Returns the entity form object for a project, ready to be built.
   */
  private function entityForm(SimplytestProject $project, string $operation, FormState $form_state): EntityFormInterface {
    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('simplytest_project', $operation);
    $form_object->setEntity($project);
    $form_state->setFormObject($form_object);
    return $form_object;
  }

  /**
   * @return array<string, mixed>
   */
  private function buildForm(EntityFormInterface $form_object, FormState $form_state): array {
    return $this->container->get('form_builder')->buildForm($form_object, $form_state);
  }

}
