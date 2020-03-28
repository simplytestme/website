<?php

namespace Drupal\simplytest_tugboat\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\simplytest_tugboat\StmTugboatInstanceStatusInterface;

/**
 * A log of status updates per instance.
 *
 * @ContentEntityType(
 *   id = "stm_tugboat_instance_status",
 *   label = @Translation("Instance status"),
 *   label_collection = @Translation("Instance statuses"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\simplytest_tugboat\StmTugboatInstanceStatusListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\simplytest_tugboat\StmTugboatInstanceStatusAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\simplytest_tugboat\Form\StmTugboatInstanceStatusForm",
 *       "edit" = "Drupal\simplytest_tugboat\Form\StmTugboatInstanceStatusForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "stm_tugboat_instance_status",
 *   data_table = "stm_tugboat_instance_status_field_data",
 *   admin_permission = "access instance status overview",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/stm-tugboat-instance-status/add",
 *     "canonical" = "/stm_tugboat_instance_status/{stm_tugboat_instance_status}",
 *     "edit-form" = "/admin/content/stm-tugboat-instance-status/{stm_tugboat_instance_status}/edit",
 *     "delete-form" = "/admin/content/stm-tugboat-instance-status/{stm_tugboat_instance_status}/delete",
 *     "collection" = "/admin/content/stm-tugboat-instance-status"
 *   },
 * )
 */
class StmTugboatInstanceStatus extends ContentEntityBase implements StmTugboatInstanceStatusInterface {

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
      ->setDescription(t('The time that the instance status was created.'))
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
      ->setRequired(TRUE)
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

    $fields['instance_status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Instance status'))
      ->setRequired(TRUE)
      ->setDescription('The reported status of the instance.')
      ->setSetting('unsigned', TRUE)
      ->setDisplayOptions('view', [
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
