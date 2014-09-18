<?php

/**
 * @file
 * Contains \Drupal\payment\Entity\Payment\PaymentEditForm.
 */

namespace Drupal\payment\Entity\Payment;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\currency\Entity\Currency;
use Drupal\currency\FormHelperInterface;
use Drupal\payment\Element\PaymentLineItemsInput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment edit form.
 */
class PaymentEditForm extends ContentEntityForm {

  /**
   * The Currency form helper.
   *
   * @var \Drupal\currency\FormHelperInterface
   */
  protected $currencyFormHelper;

  /**
   * The payment line item plugin manager.
   *
   * @var \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemManagerInterface
   */
  protected $paymentLineItemManager;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translator.
   * @param \Drupal\currency\FormHelperInterface
   *   The Currency form helper.
   */
  function __construct(EntityManagerInterface $entity_manager, TranslationInterface $string_translation, FormHelperInterface $currency_form_helper) {
    parent::__construct($entity_manager);
    $this->currencyFormHelper = $currency_form_helper;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity.manager'), $container->get('string_translation'), $container->get('currency.form_helper'));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $this->getEntity();

    $form['payment_currency_code'] = array(
      '#type' => 'select',
      '#title' => $this->t('Currency'),
      '#options' => $this->currencyFormHelper->getCurrencyOptions(),
      '#default_value' => $payment->getCurrencyCode(),
      '#required' => TRUE,
    );
    $form['payment_line_items'] = array(
      '#type' => 'payment_line_items_input',
      '#title' => $this->t('Line items'),
      '#default_value' => $payment->getLineItems(),
      '#required' => TRUE,
      '#currency_code' => '',
    );

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $payment, array $form, FormStateInterface $form_state) {
    parent::copyFormValuesToEntity($payment, $form, $form_state);
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $values = $form_state->getValues();
    $payment->setCurrencyCode($values['payment_currency_code']);
    foreach (PaymentLineItemsInput::getLineItems($form['payment_line_items'], $form_state) as $line_item) {
      $payment->setLineItem($line_item);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $this->getEntity();

    $form_state->setRedirectUrl($payment->urlInfo());
  }

}
