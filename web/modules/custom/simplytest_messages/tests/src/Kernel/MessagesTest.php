<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_messages\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_messages\Form\SettingsForm;

/**
 * @group simplytest
 * @group simplytest_messages
 */
final class MessagesTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'file',
    'filter',
    'block',
    'image',
    'responsive_image',
    'breakpoint',
    'simplytest_messages',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'filter']);
  }

  /**
   * A disabled message renders nothing at all.
   *
   * @covers \Drupal\simplytest_messages\Plugin\Block\MessageBlock::build
   */
  public function testBlockIsEmptyWhenDisabled(): void {
    $this->setMessageConfig(['enable' => FALSE]);
    self::assertEquals([], $this->buildBlock());
  }

  /**
   * @covers \Drupal\simplytest_messages\Plugin\Block\MessageBlock::build
   * @covers \Drupal\simplytest_messages\Plugin\Block\MessageBlock::getCacheMaxAge
   */
  public function testBlockRendersTitleAndBody(): void {
    $this->setMessageConfig([
      'enable' => TRUE,
      'title' => 'Scheduled maintenance',
      'body' => ['value' => 'We will be back shortly.', 'format' => 'plain_text'],
    ]);

    $build = $this->buildBlock();
    self::assertEquals('Scheduled maintenance', $build['title']['#value']);
    self::assertEquals('We will be back shortly.', $build['Body']['#value']);
    // Without an icon configured, no icon element is built.
    self::assertArrayNotHasKey('icon', $build);

    $block = $this->createBlock();
    self::assertEquals(0, $block->getCacheMaxAge());
  }

  /**
   * @covers \Drupal\simplytest_messages\Plugin\Block\MessageBlock::build
   */
  public function testBlockRendersIcon(): void {
    $file = File::create([
      'uri' => 'public://warning.png',
      'filename' => 'warning.png',
      'status' => 1,
    ]);
    $file->save();

    $this->setMessageConfig([
      'enable' => TRUE,
      'title' => 'Heads up',
      'icon' => [$file->id()],
      'body' => ['value' => 'Something to read.', 'format' => 'plain_text'],
    ]);

    $build = $this->buildBlock();
    self::assertEquals('public://warning.png', $build['icon']['#uri']);
    self::assertEquals(['messages__icon'], $build['icon']['#attributes']['class']);
  }

  /**
   * @covers \Drupal\simplytest_messages\Form\SettingsForm::buildForm
   * @covers \Drupal\simplytest_messages\Form\SettingsForm::getFormId
   */
  public function testSettingsFormBuild(): void {
    $this->setMessageConfig([
      'enable' => TRUE,
      'title' => 'Heads up',
      'body' => ['value' => 'Something to read.', 'format' => 'plain_text'],
    ]);

    $form_object = SettingsForm::create($this->container);
    self::assertEquals('simplytest_messages_settings', $form_object->getFormId());

    $form = $this->container->get('form_builder')->getForm($form_object);
    self::assertTrue((bool) $form['enable']['#default_value']);
    self::assertEquals('Heads up', $form['message']['title']['#default_value']);
    self::assertEquals('Something to read.', $form['message']['body']['#default_value']);
  }

  /**
   * An unset body does not fatal when the form is built.
   *
   * @covers \Drupal\simplytest_messages\Form\SettingsForm::buildForm
   */
  public function testSettingsFormBuildWithoutBody(): void {
    $this->setMessageConfig(['enable' => FALSE]);

    $form = $this->container->get('form_builder')->getForm(SettingsForm::create($this->container));
    self::assertEquals('', $form['message']['body']['#default_value']);
  }

  /**
   * @covers \Drupal\simplytest_messages\Form\SettingsForm::submitForm
   */
  public function testSettingsFormSubmit(): void {
    $this->setMessageConfig(['enable' => FALSE]);

    $form_object = SettingsForm::create($this->container);
    $form_state = new FormState();
    $form_state->setValues([
      'enable' => 1,
      'title' => 'New title',
      'icon' => [],
      'body' => ['value' => 'New body', 'format' => 'plain_text'],
    ]);
    $form = [];
    $form_object->submitForm($form, $form_state);

    $config = $this->config('simplytest_messages.settings');
    self::assertEquals(1, $config->get('enable'));
    self::assertEquals('New title', $config->get('title'));
    self::assertEquals('New body', $config->get('body')['value']);
  }

  /**
   * The messages region is told whether the block is switched on.
   */
  public function testRegionPreprocess(): void {
    $this->setMessageConfig(['enable' => TRUE]);

    $variables = ['region' => 'messages'];
    simplytest_messages_preprocess_region($variables);
    self::assertTrue((bool) $variables['inEnable']);

    // Any other region is left alone.
    $other = ['region' => 'content'];
    simplytest_messages_preprocess_region($other);
    self::assertArrayNotHasKey('inEnable', $other);
  }

  /**
   * @param array<string, mixed> $values
   */
  private function setMessageConfig(array $values): void {
    $config = $this->config('simplytest_messages.settings');
    foreach ($values as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();
  }

  private function createBlock(): object {
    return $this->container->get('plugin.manager.block')
      ->createInstance('simplytest_messages_example', []);
  }

  /**
   * @return array<string, mixed>
   */
  private function buildBlock(): array {
    return $this->createBlock()->build();
  }

}
