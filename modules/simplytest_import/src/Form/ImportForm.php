<?php

namespace Drupal\simplytest_import\Form;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simplytest_import\ProjectImporter;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\SimplytestProjectFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ImportForm.
 */
class ImportForm extends FormBase {

  /**
   * Simplytest Project Fetcher Service.
   *
   * @var \Drupal\simplytest_projects\SimplytestProjectFetcher
   */
  protected SimplytestProjectFetcher $simplytestProjectFetcher;

  /**
   * Simplytest Project Import Service.
   *
   * @var \Drupal\simplytest_import\ProjectImporter
   */
  protected ProjectImporter $projectImporter;

  /**
   * {@inheritdoc}
   */
  public function __construct(SimplytestProjectFetcher $simplytestProjectFetcher, ProjectImporter $projectImporter) {
    $this->simplytestProjectFetcher = $simplytestProjectFetcher;
    $this->projectImporter = $projectImporter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simplytest_projects.fetcher'),
      $container->get('simplytest_import.importer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplytest_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['type'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Importing Type:'),
      '#required' => TRUE,
      '#options' => [
        'project_module' => $this->t('Modules'),
        'project_theme' => $this->t('Themes'),
        'project_distribution' => $this->t('Distributions'),
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = array_filter($form_state->getValue('type'));
    // Import the Drupal core data.
    if (empty($this->simplytestProjectFetcher->searchFromProjects('drupal'))) {
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
      catch (EntityStorageException $e) {
        // @todo decide how to handle this error if we got a dupe save, somehow.
      }
    }
    foreach ($types as $type) {
      $batch_builder = $this->projectImporter->buildBatch($type);
      batch_set($batch_builder->toArray());
    }
  }

}
