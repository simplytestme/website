<?php

declare(strict_types=1);

namespace Drupal\simplytest_tugboat;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The response to a sandbox launch, shared by every launch endpoint.
 */
final class LaunchResponse extends JsonResponse {

  /**
   * @param array{tugboat: array{preview_id: string, job_id: string, job_url: list<string>}, expiresAt: string} $instance
   *   The launched instance, from InstanceManagerInterface::launchInstance().
   */
  public static function fromInstance(array $instance): self {
    $route_parameters = [
      'instance_id' => $instance['tugboat']['preview_id'],
      'job_id' => $instance['tugboat']['job_id'],
    ];
    return new self([
      'status' => 'OK',
      'progress' => Url::fromRoute('simplytest_tugboat.progress', $route_parameters)->setAbsolute()->toString(),
      'statusUrl' => Url::fromRoute('simplytest_tugboat.state', $route_parameters)->setAbsolute()->toString(),
      'expiresAt' => $instance['expiresAt'],
      'tugboat' => $instance['tugboat'],
    ]);
  }

}
