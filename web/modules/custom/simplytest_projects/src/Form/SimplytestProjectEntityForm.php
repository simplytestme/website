<?php

namespace Drupal\simplytest_projects\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class PracticalEntityForm.
 */
class SimplytestProjectEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function save(array $form, FormStateInterface $form_state) {
    $entity = &$this->entity;

    $status = parent::save($form, $form_state);

    // The ID is only assigned once the entity has been saved, so the message
    // parameters have to be built afterwards.
    $message_params = [
      '%entity_label' => $entity->id(),
      '%content_entity_label' => $entity->getEntityType()->getLabel()->render(),
    ];

    match ($status) {
        SAVED_NEW => $this->messenger()->addMessage($this->t('Created %content_entity_label entity:  %entity_label.', $message_params)),
        default => $this->messenger()->addMessage($this->t('Saved %content_entity_label entity:  %entity_label.', $message_params)),
    };

    $content_entity_id = $entity->getEntityType()->id();
    $form_state->setRedirect("entity.{$content_entity_id}.canonical", [$content_entity_id => $entity->id()]);
  }

}
