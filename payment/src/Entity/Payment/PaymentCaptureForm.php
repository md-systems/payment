<?php

/**
 * @file
 * Contains \Drupal\payment\Entity\Payment\PaymentCaptureForm.
 */

namespace Drupal\payment\Entity\Payment;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment capture form.
 */
class PaymentCaptureForm extends ContentEntityConfirmFormBase {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *    The string translator.
   */
  function __construct(EntityManagerInterface $entity_manager, TranslationInterface $string_translation) {
    parent::__construct($entity_manager);
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity.manager'), $container->get('string_translation'));
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you really want to capture payment #!payment_id?', array(
      '!payment_id' => $this->getEntity()->id(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Capture');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->getEntity()->urlInfo();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $this->getEntity();
    /** @var \Drupal\payment\Plugin\Payment\Method\PaymentMethodCapturePaymentInterface $payment_method */
    $payment_method = $payment->getPaymentMethod();
    $payment_method->capturePayment();

    $form_state->setRedirectUrl($payment->urlInfo());
  }

}
