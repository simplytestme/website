<?php

namespace Drupal\simplytest_tugboat\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the instance status entity edit forms.
 */
class StmTugboatInstanceStatusForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New instance status %label has been created.', $message_arguments));
      $this->logger('simplytest_tugboat')->notice('Created new instance status %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The instance status %label has been updated.', $message_arguments));
      $this->logger('simplytest_tugboat')->notice('Updated new instance status %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.stm_tugboat_instance_status.canonical', ['stm_tugboat_instance_status' => $entity->id()]);
  }

}
