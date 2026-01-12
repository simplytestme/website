<?php

namespace Drupal\simplytest_projects\Http\Middleware;

use GuzzleRetry\GuzzleRetryMiddleware;

/**
 * Provides a factory for the Guzzle Retry middleware.
 */
class RetryMiddlewareFactory
{

    /**
     * Invokes the retry middleware factory.
     */
    public function __invoke()
    {
        return GuzzleRetryMiddleware::factory([
            'max_retry_attempts' => 3,
            'retry_on_status' => [429, 503],
        ]);
    }
}
