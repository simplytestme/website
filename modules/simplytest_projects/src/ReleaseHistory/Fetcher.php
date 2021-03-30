<?php declare(strict_types=1);

namespace Drupal\simplytest_projects\ReleaseHistory;

use Drupal\update\UpdateFetcher;
use GuzzleHttp\ClientInterface;

/**
 * Fetches release history.
 *
 * Based off of core's UpdateFetcher, without the statistics added.
 *
 * @see \Drupal\update\UpdateFetcher
 */
final class Fetcher {

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private $httpClient;

  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * Fetch project release data
   *
   * @param string $project
   *   The project machine name.
   * @param string $channel
   *   The release channel (current or 7.x).
   *
   * @return string
   *   The release history XML as a string.
   */
  public function getProjectData(string $project, string $channel): string {
    if ($channel !== 'current' && $channel !== '7.x') {
      throw new \InvalidArgumentException("Only the 'current' and '7.x' channel are supported, {$channel} was provided.");
    }
    $url = UpdateFetcher::UPDATE_DEFAULT_URL . '/' . $project . '/' . $channel;
    $response = $this->httpClient->get($url, ['headers' => ['Accept' => 'text/xml']]);
    $data = (string) $response->getBody();
    return $data;
  }

}
