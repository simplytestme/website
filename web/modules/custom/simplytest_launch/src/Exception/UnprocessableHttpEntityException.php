<?php

namespace Drupal\simplytest_launch\Exception;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * A class to represent a 422 - Unprocessable Entity Exception.
 *
 * The HTTP 422 status code is used when the server sees:-
 *
 *  The content type of the request is correct.
 *  The syntax of the request is correct.
 *  BUT was unable to process the contained instruction.
 *
 * Copied from JSON:API.
 */
class UnprocessableHttpEntityException extends HttpException {

  use DependencySerializationTrait;

  /**
   * The constraint violations associated with this exception.
   *
   * @var \Symfony\Component\Validator\ConstraintViolationListInterface
   */
  protected $violations;

  /**
   * UnprocessableHttpEntityException constructor.
   *
   * @param \Exception|null $previous
   *   The pervious error, if any, associated with the request.
   * @param array $headers
   *   The headers associated with the request.
   * @param int $code
   *   The HTTP status code associated with the request. Defaults to zero.
   */
  public function __construct(\Exception $previous = NULL, array $headers = [], $code = 0) {
    parent::__construct(422, "Unprocessable Entity: validation failed.", $previous, $headers, $code);
  }

  /**
   * Gets the constraint violations associated with this exception.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   The constraint violations.
   */
  public function getViolations() {
    return $this->violations;
  }

  /**
   * Sets the constraint violations associated with this exception.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The constraint violations.
   */
  public function setViolations(ConstraintViolationListInterface $violations) {
    $this->violations = $violations;
  }

}
