<?php

/**
 * @file
 * Contains \Drupal\payment\Event\PaymentExecuteAccess.
 */

namespace Drupal\payment\Event;

use Drupal\Core\Session\AccountInterface;
use Drupal\payment\Entity\PaymentInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Provides an event that is dispatched when access is checked for payment
 * execution.
 *
 * @see \Drupal\payment\Event\PaymentEvents::PAYMENT_EXECUTE_ACCESS
 */
class PaymentExecuteAccess extends Event {

  /**
   * The access check results.
   *
   * @var string[]
   *   An array with self::ALLOW, self::DENY, or self::KILL.
   */
  protected $accessResults = array();

  /**
   * The account to check access for.
   *
   * @var \Drupal\payment\Entity\PaymentInterface
   */
  protected $account;

  /**
   * The payment.
   *
   * @var \Drupal\payment\Entity\PaymentInterface
   */
  protected $payment;

  /**
   * The payment method.
   *
   * @var \Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface
   */
  protected $paymentMethod;

  /**-
   * Constructs a new class instance.
   *
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *   The payment for which the context will be resumed
   * @param \Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface $payment_method
   *
   * @param \Drupal\Core\Session\AccountInterface
   */
  public function __construct(PaymentInterface $payment, PaymentMethodInterface $payment_method, AccountInterface $account) {
    $this->payment = $payment;
    $this->paymentMethod = $payment_method;
    $this->account = $account;
  }

  /**
   * Gets the payment for which execution access is checked.
   *
   * @return \Drupal\payment\Entity\PaymentInterface
   *   $payment->getPaymentMethod() contains the method currently configured,
   *   but NOT the method that $payment should be tested against, which is
   *   $payment_method.
   */
  public function getPayment() {
    return $this->payment;
  }

  /**
   * Gets the payment method that should execute the payment.
   *
   * @return \Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface
   */
  public function getPaymentMethod() {
    return $this->paymentMethod;
  }

  /**
   * Gets the account for which to check access.
   *
   * @return \Drupal\Core\Session\AccountInterface
   */
  public function getAccount() {
    return $this->account;
  }

  /**
   * Gets the access check results.
   *
   * @return string[]
   *   An array with self::ALLOW, self::DENY, or self::KILL.
   */
  public function getAccessResults() {
    return $this->accessResults;
  }

  /**
   * Sets an access check result.
   *
   * @param string
   *   self::ALLOW, self::DENY, or self::KILL.
   *
   * @return $this
   */
  public function setAccessResult($access_result) {
    $this->accessResults[] = $access_result;

    return $this;
  }

}
