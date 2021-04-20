<?php

namespace Drupal\simplytest_messages\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "simplytest_messages_example",
 *   admin_label = @Translation("Simplytest Message"),
 *   category = @Translation("Simplytest Messages")
 * )
 */
class MessageBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * MessageBlock constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $messageConfig = $this->configFactory->get('simplytest_messages.settings');
    if ($messageConfig->get('enable')) {
      $fileStorage = $this->entityTypeManager->getStorage('file');
      $icon = $fileStorage->load($messageConfig->get('icon')[0]);
      $build['icon'] = [
        '#type' => 'responsive_image',
        '#theme' => 'image',
        '#uri' => $icon->getFileUri(),
        '#prefix' => '<div class="warning-img w-3 h-3 bg-yellow-tan">',
        '#suffix' => '</div>',
        '#attributes' => [
          'class' => [
            'messages__icon',
          ],
        ],
      ];
      $build['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $messageConfig->get('title'),
        '#prefix' => '<div class="warning-wrapper">',
        '#attributes' => [
          'class' => [
            'messages__title',
          ],
        ],
      ];
      $build['Body'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $messageConfig->get('body')['value'],
        '#suffix' => '</div>',
        '#attributes' => [
          'class' => [
            'messages__body',
          ],
        ],
      ];
      return $build;
    }
    return [];
  }

}
