<?php

namespace Drupal\simplytest_launch\EventSubscriber;

use Drupal\Core\EventSubscriber\ExceptionJsonSubscriber;
use Drupal\simplytest_launch\Exception\UnprocessableHttpEntityException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Validator\ConstraintViolationInterface;

final class UnprocessableHttpExceptionSubscriber extends ExceptionJsonSubscriber {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected static function getPriority() {
    return parent::getPriority() + 25;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function getHandledFormats() {
    return ['json'];
  }

  #[\Override]
  public function on4xx(ExceptionEvent $event) {
    $exception = $event->getThrowable();
    if ($exception instanceof UnprocessableHttpEntityException) {
      $messages = array_map(static fn(ConstraintViolationInterface $violation) => sprintf("%s: %s", $violation->getPropertyPath(), $violation->getMessage()), \iterator_to_array($exception->getViolations()));

        $response = new JsonResponse([
          'message' => $exception->getMessage(),
          'errors' => $messages
        ],
        $exception->getStatusCode(),
        $exception->getHeaders()
      );
      $event->setResponse($response);
    }
    else {
      parent::on4xx($event);
    }
  }

}
