<?php declare(strict_types=1);

namespace Drupal\simplytest_projects\ReleaseHistory;

use Drupal\simplytest_projects\Exception\NoReleaseHistoryFoundException;

final class Processor {

  /**
   * Gets data from a release history XML string.
   *
   * @param string $release_xml
   *   The release history XML.
   *
   * @return array
   *   The processed release data.
   *
   * @see \Drupal\update\UpdateProcessor::parseXml
   */
  public static function getData(string $release_xml) {
    if (str_contains($release_xml, 'No release history available for')) {
      throw new NoReleaseHistoryFoundException();
    }
    if (str_contains($release_xml, 'No release history was found for')) {
      throw new NoReleaseHistoryFoundException();
    }
    try {
      $xml = new \SimpleXMLElement($release_xml);
    }
    catch (\Exception) {
      throw new \InvalidArgumentException("Could not parse release XML");
    }
    // If there is no valid project data, the XML is invalid, so return failure.
    if (!isset($xml->short_name)) {
      throw new \InvalidArgumentException("Could not determine project short name.");
    }
    $data = [];
    foreach ($xml as $k => $v) {
      $data[$k] = (string) $v;
    }

    $is_legacy = isset($data['api_version']);

    $data['releases'] = [];
    if (isset($xml->releases)) {
      foreach ($xml->releases->children() as $release) {
        $version = (string) $release->version;
        $release_data = [];
        foreach ($release->children() as $k => $v) {
          $release_data[$k] = (string) $v;
        }

        // Somehow the release node had no tag value associated. Assume it is
        // the same as the version.
        if (empty($release_data['tag'])) {
          $release_data['tag'] = $version;
        }

        // @todo for some reason various releases do not have dates.
        if (empty($release_data['date'])) {
          continue;
        }
        if ($is_legacy) {
          $release_data['core_compatibility'] = $data['api_version'];
        }
        elseif ($data['short_name'] === 'drupal') {
          [$major, ,] = explode('.', $version);
          $release_data['core_compatibility'] = $major . '.x';
        }
        // Some projects are missing this, somehow. Assume just 8.x if it is
        // not present, safest assumption.
        // @see https://www.drupal.org/project/password_policy_pwned/releases/8.x-1.0-beta1
        elseif (empty($release_data['core_compatibility'])) {
          $release_data['core_compatibility'] = '8.x';
        }

        $release_data['terms'] = [];
        if ($release->terms) {
          foreach ($release->terms->children() as $term) {
            if (!isset($release_data['terms'][(string) $term->name])) {
              $release_data['terms'][(string) $term->name] = [];
            }
            $release_data['terms'][(string) $term->name][] = (string) $term->value;
          }
        }
        $data['releases'][$version] = new ProjectRelease($release_data);
      }
    }
    return $data;
  }

}
