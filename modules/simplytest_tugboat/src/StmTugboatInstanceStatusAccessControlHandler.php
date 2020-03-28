<?php

namespace Drupal\simplytest_tugboat;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the instance status entity type.
 */
class StmTugboatInstanceStatusAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view instance status');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit instance status', 'administer instance status'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete instance status', 'administer instance status'], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create instance status', 'administer instance status'], 'OR');
  }

}
