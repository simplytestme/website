<?php

namespace Drupal\simplytest_launch\EventSubscriber;

use Drupal\Core\EventSubscriber\ExceptionJsonSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ServiceUnavailableHttpExceptionSubscriber extends ExceptionJsonSubscriber {

  /**
   * {@inheritdoc}
   */
  protected static function getPriority() {
    return parent::getPriority() + 25;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['json'];
  }

  public function on5xx(ExceptionEvent $event) {
    $exception = $event->getThrowable();
    assert($exception instanceof HttpExceptionInterface);
    $response = new JsonResponse(['message' => $event->getThrowable()->getMessage()], $exception->getStatusCode(), $exception->getHeaders());
    $event->setResponse($response);
  }

}
