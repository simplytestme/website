<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Unit\ReleaseHistory;

use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

abstract class ReleaseHistoryUnitTestBase extends UnitTestCase {

  protected function getMockedHttpClient(): Client {
    $client = $this->prophesize(Client::class);

    $pathauto_current_response = $this->prophesize(ResponseInterface::class);
    $pathauto_current_response->getBody()->willReturn(
      file_get_contents(__DIR__ . '/../../../fixtures/release-history/current/pathauto.xml')
    );
    $client->get(
      'https://updates.drupal.org/release-history/pathauto/current',
      ['headers' => ['Accept' => 'text/xml']])
      ->willReturn($pathauto_current_response->reveal());

    $pathauto_7x_response = $this->prophesize(ResponseInterface::class);
    $pathauto_7x_response->getBody()->willReturn(
      file_get_contents(__DIR__ . '/../../../fixtures/release-history/7.x/pathauto.xml')
    );
    $client->get(
      'https://updates.drupal.org/release-history/pathauto/7.x',
      ['headers' => ['Accept' => 'text/xml']])
      ->willReturn($pathauto_7x_response->reveal());

    return $client->reveal();
  }

}
