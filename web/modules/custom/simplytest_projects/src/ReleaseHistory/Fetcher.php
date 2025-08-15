<?php declare(strict_types=1);

namespace Drupal\simplytest_projects\ReleaseHistory;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\State\StateInterface;
use Drupal\simplytest_projects\Exception\ReleaseHistoryNotModifiedException;
use Drupal\update\UpdateFetcher;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;

/**
 * Fetches release history.
 *
 * Based off of core's UpdateFetcher, without the statistics added.
 *
 * @see \Drupal\update\UpdateFetcher
 */
final readonly class Fetcher {

  public function __construct(
      /**
       * The HTTP client to fetch the feed data with.
       */
      private ClientInterface $httpClient,
      /**
       * The state.
       */
      private StateInterface $state
  )
  {
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
   *
   * @throws \Drupal\simplytest_projects\Exception\ReleaseHistoryNotModifiedException
   */
  public function getProjectData(string $project, string $channel): string {
    if ($channel !== 'current' && $channel !== '7.x') {
      throw new \InvalidArgumentException("Only the 'current' and '7.x' channel are supported, {$channel} was provided.");
    }

    $url = UpdateFetcher::UPDATE_DEFAULT_URL . '/' . $project . '/' . $channel;
    $headers = [
      'Accept' => 'text/xml',
    ];
    // When sending an If-Modified-Since header, the server will return a 200
    // if the content has been modified, otherwise it returns 304.
    // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/If-Modified-Since
    $last_modified = $this->state->get("release_history_last_modified:$project:$channel");
    if ($last_modified) {
      $headers['If-Modified-Since'] = gmdate(DateTimePlus::RFC7231, $last_modified);
    }

    $response = $this->httpClient->get($url, ['headers' => $headers]);
    if ($response->getStatusCode() === 304) {
      throw new ReleaseHistoryNotModifiedException();
    }

    $this->state->set("release_history_last_modified:$project:$channel", strtotime($response->getHeaderLine('Last-Modified')));
    return (string) $response->getBody();
  }

}
