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
    switch (strtolower(trim($term))) {
      case 'drupal core':
      case 'core':
      case 'project_core':
        return self::CORE;

      case 'modules':
      case 'module':
      case 'project_module':
        return self::MODULE;

      case 'themes':
      case 'theme':
      case 'project_theme':
        return self::THEME;

      case 'distributions':
      case 'distribution':
      case 'project_distribution':
        return self::DISTRO;

      default:
        return FALSE;
    }
  }

}
