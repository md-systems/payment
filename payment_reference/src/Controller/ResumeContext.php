<?php

/**
 * @file
 * Contains \Drupal\payment_reference\Controller\ResumeContext.
 */

namespace Drupal\payment_reference\Controller;

use Drupal\Core\Access\AccessInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\payment\Entity\PaymentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles the "resume context" route.
 */
class ResumeContext extends ControllerBase implements ContainerInjectionInterface, AccessInterface {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   */
  public function __construct(AccountInterface $current_user, TranslationInterface $string_translation) {
    $this->currentUser = $current_user;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('current_user'), $container->get('string_translation'));
  }

  /**
   * Resumes the payment context.
   *
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *
   * @return array
   *   A renderable array.
   */
  public function execute(PaymentInterface $payment) {
    $message = $this->t('You can now <span class="payment_reference-window-close">close this window</span>.');
    if ($payment->access('view')) {
      $message = $this->t('Your payment is %status.', array(
          '%status' => $payment->getPaymentStatus()->getLabel(),
        )) . ' ' . $message;
    }

    return array(
      '#type' => 'markup',
      '#markup' => $message,
      '#attached' => array(
        'js' => array(drupal_get_path('module', 'payment_reference') . '/js/payment_reference.js'),
      ),
    );
  }

  /**
   * Returns the label of a field instance.
   *
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *
   * @return string
   */
  public function title(PaymentInterface $payment) {
    return $payment->label();
  }

  /**
   * Checks if the user has access to resume a payment's context.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *
   * @return string
   */
  public function access(Request $request, PaymentInterface $payment) {
    return AccessResult::allowedIf($payment->getPaymentType()->resumeContextAccess($this->currentUser));
  }

}
