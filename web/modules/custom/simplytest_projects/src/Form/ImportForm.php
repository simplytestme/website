<?php

namespace Drupal\simplytest_projects\Form;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simplytest_projects\ProjectImporter;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ImportForm.
 */
class ImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
      /**
       * Simplytest Project Fetcher Service.
       */
      protected ProjectFetcher $projectFetcher,
      /**
       * Simplytest Project Import Service.
       */
      protected ProjectImporter $projectImporter
  )
  {
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simplytest_projects.fetcher'),
      $container->get('simplytest_projects.importer')
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getFormId() {
    return 'simplytest_import_form';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['type'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Importing Type:'),
      '#required' => TRUE,
      '#options' => [
        'module' => $this->t('Modules'),
        'theme' => $this->t('Themes'),
        'distribution' => $this->t('Distributions'),
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = array_filter($form_state->getValue('type'));
    // Import the Drupal core data.
    if (empty($this->projectFetcher->searchFromProjects('drupal'))) {
      try {
        $project = SimplytestProject::create([
          'title' => 'Drupal core',
          'shortname' => 'drupal',
          'sandbox' => "0",
          'type' => ProjectTypes::CORE,
          'creator' => 'dries',
        ]);
        $project->save();
      }
      catch (EntityStorageException) {
        // @todo decide how to handle this error if we got a dupe save, somehow.
      }
    }
    foreach ($types as $type) {
      try {
        $batch_builder = $this->projectImporter->buildBatch($type);
        batch_set($batch_builder->toArray());
      }
      catch (\Exception $exception) {
        $this->messenger()->addError($exception->getMessage());
      }
    }
  }

}
