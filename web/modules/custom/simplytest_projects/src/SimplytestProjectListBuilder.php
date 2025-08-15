<?php

namespace Drupal\simplytest_projects;

use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PracticalTypeListBuilder
 */
class SimplytestProjectListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new PracticalListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatter $date_formatter, RendererInterface $renderer) {
    parent::__construct($entity_type, $storage);

    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildHeader(){
    $header['id'] = $this->t('Linked Entity Label');
    $header['type'] = $this->t('Type');
    $header['shortname'] = $this->t('Shortname');
    $header['creator'] = $this->t('Creator');
    $header['timestamp'] = $this->t('Last Updated');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\simplytest_projects\Entity\SimplytestProjectInterface $entity */
    $row['id'] = $entity->toLink($entity->label());
    $row['type'] = $entity->getType();
    $row['shortname'] = $entity->getShortname();
    $row['creator'] = $entity->getCreator();
    $row['timestamp'] = $this->dateFormatter->format($entity->getTimestamp(), 'short');

    return $row + parent::buildRow($entity);
  }

}
