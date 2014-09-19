<?php

/**
 * @file
 * Contains \Drupal\payment_reference\Controller\Pay.
 */

namespace Drupal\payment_reference\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles the "pay" route.
 */
class Pay extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The key/value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory) {
    $this->keyValueFactory = $key_value_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('keyvalue.expirable'));
  }

  /**
   * Executes a payment.
   *
   * @param string $storage_key
   *   The storage key with which the payment can be loaded.
   */
  public function execute($storage_key) {
    $storage = $this->keyValueFactory->get('payment.payment_type.payment_reference');
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $storage->get($storage_key);
    $storage->delete($storage_key);
    $payment->execute();
    $payment->getPaymentType()->resumeContext();
  }

  /**
   * Checks if the user has access to make a payment.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param string $storage_key
   *   The storage key with which the payment can be loaded.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Request $request, $storage_key) {
    return AccessResult::allowedIf($this->keyValueFactory->get('payment.payment_type.payment_reference')->has($storage_key));
  }

}
