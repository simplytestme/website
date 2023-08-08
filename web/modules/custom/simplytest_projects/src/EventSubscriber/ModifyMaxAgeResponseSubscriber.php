<?php

declare(strict_types=1);

namespace Drupal\simplytest_projects\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ModifyMaxAgeResponseSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      // Run after FinishResponseSubscriber.
      KernelEvents::RESPONSE => ['onResponse', 10],
    ];
  }

  public function onResponse(ResponseEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }
    $response = $event->getResponse();
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }
    $route_name = $event->getRequest()->attributes->get(RouteObjectInterface::ROUTE_NAME, '');
    if (!str_starts_with($route_name, 'simplytest_projects')) {
      return;
    }
    // Change the response max-age to 300, but do not modify Expires so that
    // page_cache is still permanent.
    $response->setMaxAge(300);
  }

}
