<?php

/**
 * @file
 * Definition of Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationAccessControlHandler.
 */

namespace Drupal\payment\Entity\PaymentMethodConfiguration;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks access for payment method configurations.
 */
class PaymentMethodConfigurationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $payment_method, $operation, $langcode, AccountInterface $account) {
    /** @var \Drupal\payment\Entity\PaymentMethodConfigurationInterface $payment_method */
    if ($operation == 'enable') {
      return AccessResult::forbiddenIf($payment_method->status())->andIf($payment_method->access('update', $account))->cacheUntilEntityChanges($payment_method);
    }
    elseif ($operation == 'disable') {
      return AccessResult::allowedIf($payment_method->status())->andIf($payment_method->access('update', $account))->cacheUntilEntityChanges($payment_method);
    }
    elseif ($operation == 'duplicate') {
      return $this->createAccess($payment_method->bundle(), $account, array(), TRUE)->andIf($payment_method->access('view', $account));
    }
    else {
      $permission = 'payment.payment_method_configuration.' . $operation;
      return AccessResult::allowedIfHasPermission($account, $permission . '.any')
        ->orIf(
          AccessResult::allowedIfHasPermission($account, $permission . '.own')
            ->andIf(AccessResult::allowedIf($account->id() == $payment_method->getOwnerId())->cacheUntilEntityChanges($payment_method))
        );
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'payment.payment_method_configuration.create.' . $bundle);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCache($cid, $operation, $langcode, AccountInterface $account) {
    // Disable the cache, because the intensive operations are cached elsewhere
    // already and the results of all other operations are too volatile to
    // cache.
  }
}
