<?php

/**
 * @file
 * Contains \Drupal\payment\Entity\Payment\PaymentStatusForm.
 */

namespace Drupal\payment\Entity\Payment;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodUpdatePaymentStatusInterface;
use Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment status update form.
 */
class PaymentStatusForm extends EntityForm {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The default datetime.
   *
   * @var \Drupal\Core\Datetime\DrupalDateTime
   */
  protected $defaultDateTime;

  /**
   * The payment status plugin manager.
   *
   * @var \Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface
   */
  protected $paymentStatusManager;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountInterface
   *   The current user.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *    The string translator.
   * @param \Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface $payment_status_manager
   *   The payment status plugin manager.
   * @param \Drupal\Core\Datetime\DrupalDateTime $default_datetime
   *   The default datetime of the new status.
   */
  function __construct(ModuleHandlerInterface $module_handler, AccountInterface $current_user, UrlGeneratorInterface $url_generator, TranslationInterface $string_translation, PaymentStatusManagerInterface $payment_status_manager, DrupalDateTime $default_datetime) {
    $this->currentUser = $current_user;
    $this->defaultDateTime = $default_datetime;
    $this->moduleHandler = $module_handler;
    $this->paymentStatusManager = $payment_status_manager;
    $this->stringTranslation = $string_translation;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('module_handler'), $container->get('current_user'), $container->get('url_generator'), $container->get('string_translation'), $container->get('plugin.manager.payment.status'), new DrupalDateTime());
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $limit_plugin_ids = NULL;
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $this->getEntity();
    $payment_method = $payment->getPaymentMethod();
    if ($payment_method instanceof PaymentMethodUpdatePaymentStatusInterface) {
      $limit_plugin_ids = $payment_method->getSettablePaymentStatuses($this->currentUser, $payment);
    }
    $form['plugin_id'] = array(
      '#options' => $this->paymentStatusManager->options($limit_plugin_ids),
      '#required' => TRUE,
      '#title' => $this->t('New status'),
      '#type' => 'select',
    );
    if ($this->moduleHandler->moduleExists('datetime')) {
      $form['created'] = array(
        '#default_value' => $this->defaultDateTime,
        '#required' => TRUE,
        '#title' => $this->t('Date and time'),
        '#type' => 'datetime',
      );
    }
    else {
      $form['created'] = array(
        '#default_value' => $this->defaultDateTime,
        '#type' => 'value',
      );
      if ($this->currentUser->hasPermission('administer modules')) {
        $form['created_message'] = array(
          '#type' => 'markup',
          '#markup' => $this->t('Enable the <a href="@url">Datetime</a> module to set the date and time of the new payment status.', array(
              '@url' => $this->urlGenerator->generateFromRoute('system.modules_list', array(), array(
                  'fragment' => 'module-datetime',
                ))
            )),
        );
      }
    }

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions = array($actions['submit']);

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $payment_status = $this->paymentStatusManager->createInstance($values['plugin_id']);
    /** @var \Drupal\Core\Datetime\DrupalDateTime $created */
    $created = $values['created'];
    $payment_status->setCreated($created->getTimestamp());

    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $this->getEntity();
    $payment->setPaymentStatus($payment_status);
    $payment->save();

    $form_state->setRedirectUrl($payment->urlInfo());
  }
}
