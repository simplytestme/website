<?php

namespace Drupal\simplytest_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simplytest_import\SimplytestImportService;
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
  protected $simplytestProjectFetcher;

  /**
   * Simplytest Project Import Service.
   *
   * @var \Drupal\simplytest_import\SimplytestImportService
   */
  protected $importService;

  /**
   * {@inheritdoc}
   */
  public function __construct(SimplytestProjectFetcher $simplytestProjectFetcher, SimplytestImportService $simplytestImportService) {
    $this->simplytestProjectFetcher = $simplytestProjectFetcher;
    $this->importService = $simplytestImportService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simplytest_projects.fetcher'),
      $container->get('simplytest_import.service')
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
    $type = $form_state->getValue('type');
    // Import the Drupal core data.
    if (empty($this->simplytestProjectFetcher->searchFromProjects('drupal'))) {
      $project = SimplytestProject::create($this->getCoreData());
      $project->save();
    }
    foreach ($type as $value) {
      if ($value) {
        $this->import($value);
      }
    }
  }

  /**
   * Import the data via batch process.
   *
   * @param string $type
   *   Type of the items.
   */
  protected function import($type) {
    $items = $this->importService->dataProvider($type);
    if (!empty($items)) {
      $operations = [];
      $count = $this->getTotalDataCount($items['last']);
      for ($index = 0; $index < $count; $index++) {
        $operations[] = ['simplytest_import_batch_process', [$index, $type]];
      }
      $batch = [
        'title' => $this->t('Importing @num pages of @type',
          [
            '@num' => $count,
            '@type' => str_replace('project_', '', $type),
          ]
        ),
        'operations' => $operations,
        'finished' => 'simplytest_import_batch_finished',
      ];
      batch_set($batch);
    }
  }

  /**
   * Get core data from drupal.org.
   */
  protected function getCoreData() {
    return [
      'title' => 'Drupal core',
      'shortname' => 'drupal',
      'sandbox' => "0",
      'type' => ProjectTypes::CORE,
      'creator' => 'dries',
    ];
  }

  /**
   * Get the total page count from a particular request.
   *
   * @param string $lastUrl
   *   The last data url.
   *
   * @return string|null
   *   Return the total page count.
   */
  protected function getTotalDataCount($lastUrl) {
    if (preg_match('/&page=(\d*)/', $lastUrl, $count)) {
      return $count[1];
    }
    return NULL;
  }

}
