<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Traits;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Prophecy\PhpUnit\ProphecyTrait;


trait MockedReleaseHttpClientTrait {

  use ProphecyTrait;

  protected function getMockedHttpClient(): Client {
    $client = $this->prophesize(Client::class);

    $not_modified_response = $this->prophesize(ResponseInterface::class);
    $not_modified_response->getStatusCode()->willReturn(304);

    $pathauto_current_response = $this->prophesize(ResponseInterface::class);
    $pathauto_current_response->getHeaderLine('Last-Modified')->willReturn('Wed, 21 Apr 2021 00:36:14 GMT');
    $pathauto_current_response->getStatusCode()->willReturn(200);
    $pathauto_current_response->getBody()->willReturn(
      file_get_contents(__DIR__ . '/../../fixtures/release-history/current/pathauto.xml')
    );
    $client->get(
      'https://updates.drupal.org/release-history/pathauto/current',
      ['headers' => ['Accept' => 'text/xml']])
      ->willReturn($pathauto_current_response->reveal());

    $client->get(
      'https://updates.drupal.org/release-history/pathauto/current',
      ['headers' => ['Accept' => 'text/xml', 'If-Modified-Since' => 'Wed, 21 Apr 2021 00:36:14 GMT']])
      ->willReturn($not_modified_response->reveal());

    $pathauto_7x_response = $this->prophesize(ResponseInterface::class);
    $pathauto_7x_response->getHeaderLine('Last-Modified')->willReturn('Wed, 21 Apr 2021 00:36:14 GMT');
    $pathauto_7x_response->getStatusCode()->willReturn(200);
    $pathauto_7x_response->getBody()->willReturn(
      file_get_contents(__DIR__ . '/../../fixtures/release-history/7.x/pathauto.xml')
    );
    $client->get(
      'https://updates.drupal.org/release-history/pathauto/7.x',
      ['headers' => ['Accept' => 'text/xml']])
      ->willReturn($pathauto_7x_response->reveal());

    $client->get(
      'https://updates.drupal.org/release-history/pathauto/7.x',
      ['headers' => ['Accept' => 'text/xml', 'If-Modified-Since' => 'Wed, 21 Apr 2021 00:36:14 GMT']])
      ->willReturn($not_modified_response->reveal());

    $drupalbin_7x_response = $this->prophesize(ResponseInterface::class);
    $drupalbin_7x_response->getHeaderLine('Last-Modified')->willReturn('Wed, 21 Apr 2021 00:36:14 GMT');
    $drupalbin_7x_response->getStatusCode()->willReturn(200);
    $drupalbin_7x_response->getBody()->willReturn(
      file_get_contents(__DIR__ . '/../../fixtures/release-history/7.x/drupalbin.xml')
    );
    $client->get(
      'https://updates.drupal.org/release-history/drupalbin/7.x',
      ['headers' => ['Accept' => 'text/xml']])
      ->willReturn($drupalbin_7x_response->reveal());

    $drupalbin_current_response = $this->prophesize(ResponseInterface::class);
    $drupalbin_current_response->getHeaderLine('Last-Modified')->willReturn('Wed, 21 Apr 2021 00:36:14 GMT');
    $drupalbin_current_response->getStatusCode()->willReturn(200);
    $drupalbin_current_response->getBody()->willReturn(
      file_get_contents(__DIR__ . '/../../fixtures/release-history/current/drupalbin.xml')
    );
    $client->get(
      'https://updates.drupal.org/release-history/drupalbin/current',
      ['headers' => ['Accept' => 'text/xml']])
      ->willReturn($drupalbin_current_response->reveal());

    return $client->reveal();
  }

}
