<?php

namespace Drupal\simplytest_launch\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\DrupalKernelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The Drupal kernel.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  private $drupalKernel;

  /**
   * Constructs a new ConfigSubscriber object.
   *
   * @param \Drupal\Core\DrupalKernelInterface $drupal_kernel
   *   The Drupal kernel.
   */
  public function __construct(DrupalKernelInterface $drupal_kernel) {
    $this->drupalKernel = $drupal_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

  /**
   * React to config saving.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *  The event.
   */
  public function onSave(ConfigCrudEvent $event) {
    if (
      $event->isChanged('allowed_hosts') &&
      $event->getConfig()->getName() === 'simplytest_launch.settings'
    ) {
      $this->drupalKernel->rebuildContainer();
    }
  }

}
