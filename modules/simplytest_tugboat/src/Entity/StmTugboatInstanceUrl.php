<?php

namespace Drupal\simplytest_tugboat\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\simplytest_tugboat\StmTugboatInstanceUrlInterface;

/**
 * One record per instance to match the provisioned URL to instance.
 *
 * @ContentEntityType(
 *   id = "stm_tugboat_instanceurl",
 *   label = @Translation("InstanceUrl"),
 *   label_collection = @Translation("InstanceUrls"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\simplytest_tugboat\StmTugboatInstanceUrlListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\simplytest_tugboat\StmTugboatInstanceUrlAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\simplytest_tugboat\Form\StmTugboatInstanceUrlForm",
 *       "edit" = "Drupal\simplytest_tugboat\Form\StmTugboatInstanceUrlForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "stm_tugboat_instanceurl",
 *   data_table = "stm_tugboat_instance_url_field_data",
 *   admin_permission = "access instanceurl overview",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/stm-tugboat-instanceurl/add",
 *     "canonical" = "/stm_tugboat_instanceurl/{stm_tugboat_instanceurl}",
 *     "edit-form" = "/admin/content/stm-tugboat-instanceurl/{stm_tugboat_instanceurl}/edit",
 *     "delete-form" = "/admin/content/stm-tugboat-instanceurl/{stm_tugboat_instanceurl}/delete",
 *     "collection" = "/admin/content/stm-tugboat-instanceurl"
 *   },
 * )
 */
class StmTugboatInstanceUrl extends ContentEntityBase implements StmTugboatInstanceUrlInterface {

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the instanceurl was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['instance_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Instance ID'))
      ->setRequired(TRUE)
      ->setDescription(t('The primary identifier for the instance.'))
      ->setSetting('max_length', 64)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['tugboat_url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Tugboat URL'))
      ->setRequired(FALSE)
      ->setDescription(t('Tugboat redirection URL'))
      ->setSetting('max_length', 200)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['context'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Context'))
      ->setRequired(TRUE)
      ->setDescription(t('Tugboat redirection URL'))
      ->setSetting('max_length', 200)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
