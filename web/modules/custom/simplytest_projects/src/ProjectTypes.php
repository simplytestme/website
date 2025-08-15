<?php

namespace Drupal\simplytest_projects;

/**
 * Class ProjectTypes
 *
 * Helper/Utility for determining human readable project types.
 *
 * @package Drupal\simplytest_projects
 */
class ProjectTypes {

  /**
   * Drupal.org project types human readable.
   */
  const CORE = 'Drupal core';
  const MODULE = 'Module';
  const THEME = 'Theme';
  const DISTRO = 'Distribution';

  /**
   * Finds out the readable project type by term.
   *
   * @param string $term
   *  A drupal.org project term.
   *
   * @return string
   *  The corresponding project type.
   */
  public static function getProjectType($term) {
    return match (strtolower(trim($term))) {
        'drupal core', 'core', 'project_core' => self::CORE,
        'modules', 'module', 'project_module' => self::MODULE,
        'themes', 'theme', 'project_theme' => self::THEME,
        'distributions', 'distribution', 'project_distribution' => self::DISTRO,
        default => FALSE,
    };
  }

}
