<?php

namespace Drupal\simplytest_projects\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\simplytest_projects\DrupalUrls;

/**
 * Defines the Simplytest Project entity.
 *
 * @ContentEntityType(
 *   id = "simplytest_project",
 *   label = @Translation("Simplytest Project"),
 *   base_table = "simplytest_project",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *   },
 *   fieldable = TRUE,
 *   admin_permission = "administer simplytest projects",
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\simplytest_projects\SimplytestProjectListBuilder",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\simplytest_projects\Form\SimplytestProjectEntityForm",
 *       "add" = "Drupal\simplytest_projects\Form\SimplytestProjectEntityForm",
 *       "edit" = "Drupal\simplytest_projects\Form\SimplytestProjectEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   links = {
 *     "canonical" = "/simplytest_project/{simplytest_project}",
 *     "add-form" = "/simplytest_projec/add/simplytest_project",
 *     "edit-form" = "/simplytest_project/{simplytest_project}/edit",
 *     "delete-form" = "/simplytest_project/{simplytest_project}/delete",
 *     "collection" = "/admin/content/simplytest_projects",
 *   },
 *   field_ui_base_route = "entity.simplytest_project.collection",
 * )
 */
class SimplytestProject extends ContentEntityBase implements SimplytestProjectInterface {

  /**
   * @return string
   */
  public function label() {
    return $this->get('title')->value;
  }

  /**
   * @return string
   */
  public function getShortname() {
    return $this->get('shortname')->value;
  }

  /**
   * @return boolean
   */
  public function isSandbox() {
    return (bool) $this->get('sandbox')->value;
  }

  /**
   * @return string
   */
  public function getCreator() {
    return $this->get('creator')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatorEscaped() {
    return preg_replace('/[^a-zA-Z0-9-_\.+!*\(\)\']/s', '', $this->getCreator());
  }

  /**
   * @return string
   */
  public function getType() {
    return $this->get('type')->value;
  }

  /**
   * @return array
   */
  public function getVersions() {
    $versions = $this->get('versions')->getValue();
    return !empty($versions[0]) ? $versions[0] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setVersions($tags, $heads) {
    $this->set('versions', [
      'tags' => array_combine($tags, $tags),
      'heads' => array_combine($heads, $heads),
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp() {
    return $this->get('timestamp')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getGitUrl() {
    if ($this->isSandbox()) {
      return DrupalUrls::GIT_WEB . 'sandbox-' . $this->getCreatorEscaped() . '-' . $this->getShortname();
    }
    else {
      return DrupalUrls::GIT_WEB . $this->getShortname();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGitWebUrl() {
    if ($this->isSandbox()) {
      return DrupalUrls::GIT_SANDBOX . $this->getCreatorEscaped() . '/' . $this->getShortname() . '.git';
    }
    else {
      return DrupalUrls::GIT_PROJECT . $this->getShortname() . '.git';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectUrl() {
    if ($this->isSandbox()) {
      return DrupalUrls::ORG . 'sandbox/' . $this->getCreatorEscaped() . '/' . $this->getShortname();
    }
    else {
      return DrupalUrls::ORG . 'project/' . $this->getShortname();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The human readable title of the project.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['shortname'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Shortname'))
      ->setDescription(t('The shortname of the project.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['sandbox'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Sandbox'))
      ->setDescription(t('Boolean indicating whether the project is a sandbox.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('form', array(
        'type' => 'boolean_checkbox',
        'settings' => array(
          'display_label' => TRUE,
        ),
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['creator'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Creator'))
      ->setDescription(t('The creator of the project.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('The project type.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['versions'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Versions'))
      ->setDescription(t('A serialized array containing all available versions of the project.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('');


    $fields['timestamp'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Timestamp Updated'))
      ->setDescription(t('Timestamp of the last time the version data was renewed.'));

    return $fields;
  }

}
