<?php

declare(strict_types=1);

namespace Drupal\simplytest_projects\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Shortens the browser max-age on the site's frequently changing routes.
 *
 * Project and version data changes far more often than the site-wide
 * `system.performance` max-age of 32 days, so a browser that caches these
 * routes for that long keeps serving stale releases.
 */
final class ModifyMaxAgeResponseSubscriber implements EventSubscriberInterface {

  /**
   * How long a browser may reuse one of these responses, in seconds.
   */
  private const int MAX_AGE = 300;

  /**
   * Route name prefix for this module's own routes.
   */
  private const string ROUTE_PREFIX = 'simplytest_projects';

  /**
   * Routes outside this module that need the same treatment.
   *
   * The launch statistics report is rebuilt on a similar cadence and is served
   * to anonymous visitors through the same caches. These are matched by name
   * only, so a route that is not registered simply never matches.
   */
  private const array EXTRA_ROUTES = [
    'simplytest_tugboat.statistics',
  ];

  #[\Override]
  public static function getSubscribedEvents(): array {
    return [
      // In Symfony a higher priority runs earlier, so this has to be negative
      // to run last. Core's FinishResponseSubscriber writes Cache-Control at
      // priority 0 and http_cache_control adds s-maxage at -10; running before
      // either of them means writing a header they then rebuild.
      KernelEvents::RESPONSE => ['onResponse', -100],
    ];
  }

  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $response = $event->getResponse();
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }
    $route_name = (string) $event->getRequest()->attributes->get(RouteObjectInterface::ROUTE_NAME, '');
    if (!str_starts_with($route_name, self::ROUTE_PREFIX)
      && !in_array($route_name, self::EXTRA_ROUTES, TRUE)) {
      return;
    }
    // Core marks a response `public` only once its request and response
    // policies agree it may be stored by a shared cache. Anything else is an
    // authenticated or otherwise private response, and must not gain a max-age
    // here.
    if (!$response->headers->hasCacheControlDirective('public')) {
      return;
    }
    // Only ever shorten. A stricter max-age set upstream stays as it is. The
    // directive is read straight off the header because Response::getMaxAge()
    // reports s-maxage in preference to max-age.
    $max_age = $response->headers->getCacheControlDirective('max-age');
    if (is_numeric($max_age) && (int) $max_age <= self::MAX_AGE) {
      return;
    }
    // s-maxage is left alone, so the CDN keeps the lifetime configured in
    // http_cache_control. Expires is untouched as well, which keeps the
    // internal page cache permanent.
    $response->setMaxAge(self::MAX_AGE);

    // The fastly module copies Cache-Control into Surrogate-Control at
    // priority 0, before this subscriber runs, so by now that header carries
    // the site-wide max-age. Fastly prefers Surrogate-Control over s-maxage,
    // which would pin the edge cache to the 32 day lifetime. Rewrite its
    // max-age to the CDN lifetime configured in http_cache_control.
    $surrogate_control = $response->headers->get('Surrogate-Control');
    if ($surrogate_control !== NULL) {
      $s_maxage = $response->headers->getCacheControlDirective('s-maxage');
      $cdn_max_age = is_numeric($s_maxage) ? (int) $s_maxage : self::MAX_AGE;
      $response->headers->set('Surrogate-Control', preg_replace(
        '/max-age=\d+/',
        'max-age=' . $cdn_max_age,
        $surrogate_control,
      ));
    }
  }

}
