<?php

/**
 * Contains \Drupal\payment\PaymentMethod\PaymentMethodBase.
 */

namespace Drupal\payment\Plugin\Payment\Method;

use Drupal\Core\Access\AccessInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\payment\Entity\PaymentInterface;
use Drupal\payment\Event\PaymentEvents;
use Drupal\payment\Event\PaymentExecuteAccess;
use Drupal\payment\Event\PaymentPreCapture;
use Drupal\payment\Event\PaymentPreExecute;
use Drupal\payment\Event\PaymentPreRefund;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A base payment method plugin.
 *
 * Plugins that extend this class must have the following two keys in their
 * plugin definitions:
 * - message_text: The translated human-readable text to display in the payment
 *   form.
 * - message_text_format: The ID of the text format to format message_text with.
 */
abstract class PaymentMethodBase extends PluginBase implements ContainerFactoryPluginInterface, PaymentMethodInterface, PaymentMethodCapturePaymentInterface, PaymentMethodRefundPaymentInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The payment this payment method is for.
   *
   * @var \Drupal\payment\Entity\PaymentInterface
   */
  protected $payment;

  /**
   * The token API.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Utility\Token $token
   *   The token API.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EventDispatcherInterface $event_dispatcher, Token $token) {
    $configuration += $this->defaultConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->eventDispatcher = $event_dispatcher;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('event_dispatcher'), $container->get('token'));
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * Gets the payer message text.
   *
   * @return string
   */
  public function getMessageText() {
    return $this->pluginDefinition['message_text'];
  }

  /**
   * Gets the payer message text format.
   *
   * @return string
   */
  public function getMessageTextFormat() {
    return $this->pluginDefinition['message_text_format'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPayment() {
    return $this->payment;
  }

  /**
   * {@inheritdoc}
   */
  public function setPayment(PaymentInterface $payment) {
    $this->payment = $payment;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Do not use the module handler, as we only need check_markup() anyway,
    $message = function_exists('check_markup') ? check_markup($this->getMessageText(), $this->getMessageTextFormat()) : $this->getMessageText();
    $message = $this->token->replace($message, array(
      'payment' => $this->getPayment(),
    ), array(
      'clear' => TRUE,
    ));
    $elements = array();
    $elements['message'] = array(
      '#type' => 'markup',
      '#markup' => $message,
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function isPaymentExecutionInterruptive() {
    // To be on the safe side, we assume any payment method is interruptive.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function executePaymentAccess(AccountInterface $account) {
    if (!$this->getPayment()) {
      throw new \LogicException('Trying to check access for a non-existing payment. A payment must be set trough self::setPayment() first.');
    }

    return $this->pluginDefinition['active']
    && $this->executePaymentAccessCurrency($account)
    && $this->executePaymentAccessEvent($account)
    && $this->doExecutePaymentAccess($account);
  }

  /**
   * Performs a payment method-specific access check for payment execution.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return bool
   */
  protected function doExecutePaymentAccess(AccountInterface $account) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function executePayment() {
    if (!$this->getPayment()) {
      throw new \LogicException('Trying to execute a non-existing payment. A payment must be set trough self::setPayment() first.');
    }
    $event = new PaymentPreExecute($this->getPayment());
    $this->eventDispatcher->dispatch(PaymentEvents::PAYMENT_PRE_EXECUTE, $event);
    // @todo invoke Rules event.
    $this->doExecutePayment();
  }

  /**
   * Performs the actual payment execution.
   */
  abstract protected function doExecutePayment();

  /**
   * {@inheritdoc}
   */
  public function capturePaymentAccess(AccountInterface $account) {
    if (!$this->getPayment()) {
      throw new \LogicException('Trying to check access for a non-existing payment. A payment must be set trough self::setPayment() first.');
    }

    return $this->doCapturePaymentAccess($account);
  }

  /**
   * Performs a payment method-specific access check for payment capture.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return bool
   */
  abstract protected function doCapturePaymentAccess(AccountInterface $account);

  /**
   * {@inheritdoc}
   */
  public function capturePayment() {
    if (!$this->getPayment()) {
      throw new \LogicException('Trying to capture a non-existing payment. A payment must be set trough self::setPayment() first.');
    }
    $event = new PaymentPreCapture($this->getPayment());
    $this->eventDispatcher->dispatch(PaymentEvents::PAYMENT_PRE_CAPTURE, $event);
    // @todo invoke Rules event.
    $this->doCapturePayment();
  }

  /**
   * Performs the actual payment capture.
   */
  abstract protected function doCapturePayment();

  /**
   * {@inheritdoc}
   */
  public function refundPaymentAccess(AccountInterface $account) {
    if (!$this->getPayment()) {
      throw new \LogicException('Trying to check access for a non-existing payment. A payment must be set trough self::setPayment() first.');
    }

    return $this->doRefundPaymentAccess($account);
  }

  /**
   * Performs a payment method-specific access check for payment refunds.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return bool
   */
  abstract protected function doRefundPaymentAccess(AccountInterface $account);

  /**
   * {@inheritdoc}
   */
  public function refundPayment() {
    if (!$this->getPayment()) {
      throw new \LogicException('Trying to refund a non-existing payment. A payment must be set trough self::setPayment() first.');
    }
    $event = new PaymentPreRefund($this->getPayment());
    $this->eventDispatcher->dispatch(PaymentEvents::PAYMENT_PRE_REFUND, $event);
    // @todo invoke Rules event.
    $this->doRefundPayment();
  }

  /**
   * Performs the actual payment refund.
   */
  abstract protected function doRefundPayment();

  /**
   * Checks a payment's currency against this plugin.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return bool
   */
  protected function executePaymentAccessCurrency(AccountInterface $account) {
    $currencies = $this->getSupportedCurrencies();
    $payment_currency_code = $this->getPayment()->getCurrencyCode();
    $payment_amount = $this->getPayment()->getAmount();
    // If all currencies are allowed, grant access.
    if ($currencies === TRUE) {
      return TRUE;
    }
    // If the payment's currency is not specified, access is denied.
    if (!isset($currencies[$payment_currency_code])) {
      return FALSE;
    }
    // Confirm the payment amount is higher than the supported minimum.
    elseif (isset($currencies[$payment_currency_code]['minimum']) && $payment_amount < $currencies[$payment_currency_code]['minimum']) {
      return FALSE;
    }
    // Confirm the payment amount does not exceed the maximum.
    elseif (isset($currencies[$payment_currency_code]['maximum']) && $payment_amount > $currencies[$payment_currency_code]['maximum']) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Returns the supported currencies.
   *
   * @return array|true
   *   Keys are ISO 4217 currency codes. Values are arrays with two keys:
   *   - minimum (optional): The minimum amount in this currency that is
   *     supported.
   *   - maximum (optional): The maximum amount in this currency that is
   *     supported.
   *   Return TRUE to allow all currencies and amounts.
   */
  abstract protected function getSupportedCurrencies();

  /**
   * Invokes events for self::executePaymentAccess().
   *
   * @todo Invoke Rules event.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return bool
   */
  protected function executePaymentAccessEvent(AccountInterface $account) {
    $event = new PaymentExecuteAccess($this->getPayment(), $this, $account);
    $this->eventDispatcher->dispatch(PaymentEvents::PAYMENT_EXECUTE_ACCESS, $event);
    $access_results = $event->getAccessResults();

    // If there are no results, grant access.
    return empty($access_results) || in_array(self::ALLOW, $access_results, TRUE) && !in_array(self::KILL, $access_results, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->pluginDefinition['label'];
  }

}
