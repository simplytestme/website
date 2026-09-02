<?php declare(strict_types=1);

namespace Drupal\simplytest_tugboat;

/**
 * What was launched, in the shape the launch record table stores it.
 *
 * This deliberately carries nothing about *who* launched: no IP, no user agent,
 * no session, and no patch URLs. A patch URL points at the specific issue
 * somebody is testing, which is identifying in a way a bare count is not.
 *
 * @see \Drupal\simplytest_tugboat\LaunchRecorder
 */
final readonly class LaunchRecord {

  /**
   * @param string $project
   *   Project shortname, or an empty string for a one click demo.
   * @param string $projectType
   *   Project type, as a \Drupal\simplytest_projects\ProjectTypes constant.
   * @param string $projectVersion
   *   The release the project was launched at.
   * @param string $coreVersion
   *   The Drupal core release the sandbox was built on.
   * @param string $installProfile
   *   The selected install profile.
   * @param string $oneClickDemo
   *   One click demo plugin ID, or an empty string for a normal launch.
   * @param bool $manualInstall
   *   Whether the user asked to run the installer themselves.
   * @param int $patchCount
   *   How many patches were applied.
   * @param int $additionalCount
   *   How many additional projects were added.
   */
  private function __construct(
    public string $project,
    public string $projectType,
    public string $projectVersion,
    public string $coreVersion,
    public string $installProfile,
    public string $oneClickDemo,
    public bool $manualInstall,
    public int $patchCount,
    public int $additionalCount,
  ) {
  }

  /**
   * Builds a record from the preview parameters of a normal launch.
   *
   * @param array{
   *   project: string,
   *   project_type: string,
   *   project_version: string,
   *   drupal_core_version: string,
   *   install_profile: string,
   *   perform_install: bool,
   *   patches: array<mixed>,
   *   additionals: array<mixed>,
   * } $parameters
   *   The parameters handed to the preview config generator.
   */
  public static function fromPreviewParameters(array $parameters): self {
    return new self(
      project: $parameters['project'],
      projectType: $parameters['project_type'],
      projectVersion: $parameters['project_version'],
      coreVersion: $parameters['drupal_core_version'],
      installProfile: $parameters['install_profile'],
      oneClickDemo: '',
      manualInstall: !$parameters['perform_install'],
      patchCount: count($parameters['patches']),
      additionalCount: count($parameters['additionals']),
    );
  }

  /**
   * Builds a record for a one click demo launch.
   *
   * Demos take no options, so there is nothing to record beyond which one ran.
   *
   * @param string $plugin_id
   *   The one click demo plugin ID.
   */
  public static function forOneClickDemo(string $plugin_id): self {
    return new self(
      project: '',
      projectType: '',
      projectVersion: '',
      coreVersion: '',
      installProfile: '',
      oneClickDemo: $plugin_id,
      manualInstall: FALSE,
      patchCount: 0,
      additionalCount: 0,
    );
  }

}
