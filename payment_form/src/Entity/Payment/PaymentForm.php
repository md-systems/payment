<?php

/**
 * @file
 * Contains \Drupal\payment\Entity\PaymentFormController.
 */

namespace Drupal\payment_form\Entity\Payment;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment form.
 */
class PaymentForm extends ContentEntityForm {

  /**
   * The payment method selector manager.
   *
   * @var \Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorManagerInterface
   */
  protected $paymentMethodSelectorManager;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   * @param \Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorManagerInterface $payment_method_selector_manager
   *   The payment method selector manager.
   */
  public function __construct(EntityManagerInterface $entity_manager, TranslationInterface $string_translation, PaymentMethodSelectorManagerInterface $payment_method_selector_manager) {
    parent::__construct($entity_manager);
    $this->paymentMethodSelectorManager = $payment_method_selector_manager;
    $this->stringTranslation = $string_translation;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity.manager'), $container->get('string_translation'), $container->get('plugin.manager.payment.method_selector'));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $payment = $this->getEntity();

    if ($form_state->has('payment_method_selector')) {
      $payment_method_selector = $form_state->get('payment_method_selector');
    }
    else {
      $config = $this->config('payment_form.payment_type');
      $payment_method_selector_id = $config->get('payment_method_selector_id');
      $limit_allowed_payment_methods = $config->get('limit_allowed_payment_methods');
      $allowed_payment_method_ids = $config->get('allowed_payment_method_ids');
      $payment_method_selector = $this->paymentMethodSelectorManager->createInstance($payment_method_selector_id);
      if ($limit_allowed_payment_methods) {
        $payment_method_selector->setAllowedPaymentMethods($allowed_payment_method_ids);
      }
      $payment_method_selector->setPayment($payment);
      $payment_method_selector->setRequired();
      $form_state->set('payment_method_selector', $payment_method_selector);
    }

    $form['line_items'] = array(
      '#payment' => $payment,
      '#type' => 'payment_line_items_display',
    );
    $form['payment_method'] = $payment_method_selector->buildConfigurationForm(array(), $form_state);

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorInterface $payment_method_selector */
    $payment_method_selector = $form_state->get('payment_method_selector');
    $payment_method_selector->validateConfigurationForm($form['payment_method'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $this->getEntity();
    /** @var \Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorInterface $payment_method_selector */
    $payment_method_selector = $form_state->get('payment_method_selector');
    $payment_method_selector->submitConfigurationForm($form['payment_method'], $form_state);
    $payment->setPaymentMethod($payment_method_selector->getPaymentMethod());
    $payment->save();
    $payment->execute();
  }

  /**
   * Returns an array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Only use the existing submit action.
    $actions = parent::actions($form, $form_state);
    $actions = array(
      'submit' => $actions['submit'],
    );
    $actions['submit']['#value'] = $this->t('Pay');

    return $actions;
  }

}
