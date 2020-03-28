<?php

namespace Drupal\simplytest_tugboat;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the instanceurl entity type.
 */
class StmTugboatInstanceUrlAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view instanceurl');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit instanceurl', 'administer instanceurl'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete instanceurl', 'administer instanceurl'], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create instanceurl', 'administer instanceurl'], 'OR');
  }

}
