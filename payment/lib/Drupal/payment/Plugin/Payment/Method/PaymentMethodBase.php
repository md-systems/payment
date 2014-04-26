<?php

/**
 * Contains \Drupal\payment\PaymentMethod\PaymentMethodBase.
 */

namespace Drupal\payment\Plugin\Payment\Method;

use Drupal\Core\Access\AccessInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\payment\Entity\PaymentInterface;
use Drupal\payment\Event\PaymentEvents;
use Drupal\payment\Event\PaymentExecuteAccess;
use Drupal\payment\Event\PaymentPreExecute;
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
abstract class PaymentMethodBase extends PluginBase implements AccessInterface, ContainerFactoryPluginInterface, PaymentMethodInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The payment method entity this plugin belongs to.
   *
   * @var \Drupal\payment\Entity\PaymentMethodConfigurationInterface
   */
  protected $paymentMethod;

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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Utility\Token $token
   *   The token API.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ModuleHandlerInterface $module_handler, EventDispatcherInterface $event_dispatcher, Token $token) {
    $configuration += $this->defaultConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->eventDispatcher = $event_dispatcher;
    $this->moduleHandler = $module_handler;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('module_handler'), $container->get('event_dispatcher'), $container->get('token'));
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
  public function formElements(array $form, array &$form_state, PaymentInterface $payment) {
    // Do not use the module handler, as we only need check_markup() anyway,
    $message = function_exists('check_markup') ? $this->checkMarkup($this->getMessageText(), $this->getMessageTextFormat()) : $this->getMessageText();
    $message = $this->token->replace($message, array(
      'payment' => $payment,
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
   * Wraps check_markup().
   */
  protected function checkMarkup($text, $format_id = NULL, $langcode = '', $cache = FALSE, $filter_types_to_skip = array()) {
    return check_markup($text, $format_id, $langcode, $cache, $filter_types_to_skip);
  }

  /**
   * {@inheritdoc}
   */
  public function executePaymentAccess(PaymentInterface $payment, AccountInterface $account) {
    return $this->pluginDefinition['active']
    && $this->executePaymentAccessCurrency($payment, $account)
    && $this->executePaymentAccessEvent($payment, $account)
    && $this->doExecutePaymentAccess($payment, $account);
  }

  /**
   * Performs a payment method-specific access check for payment execution.
   *
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return bool
   */
  protected function doExecutePaymentAccess(PaymentInterface $payment, AccountInterface $account) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function executePayment(PaymentInterface $payment) {
    $event = new PaymentPreExecute($payment);
    $this->eventDispatcher->dispatch(PaymentEvents::PAYMENT_PRE_EXECUTE, $event);
    $this->moduleHandler->invokeAll('payment_pre_execute', array($payment));
    // @todo invoke Rules event.
    $this->doExecutePayment($payment);
  }

  /**
   * Performs the actual payment execution.
   *
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   */
  abstract protected function doExecutePayment(PaymentInterface $payment);

  /**
   * Checks a payment's currency against this plugin.
   *
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return bool
   */
  protected function executePaymentAccessCurrency(PaymentInterface $payment, AccountInterface $account) {
    $currencies = $this->getSupportedCurrencies();
    $payment_currency_code = $payment->getCurrencyCode();
    $payment_amount = $payment->getAmount();
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
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return bool
   */
  protected function executePaymentAccessEvent(PaymentInterface $payment, AccountInterface $account) {
    $event = new PaymentExecuteAccess($payment, $this, $account);
    $this->eventDispatcher->dispatch(PaymentEvents::PAYMENT_EXECUTE_ACCESS, $event);

    $hook_access_results = $this->moduleHandler->invokeAll('payment_execute_access', array($payment, $this, $account));

    $access_results = array_merge($event->getAccessResults(), $hook_access_results);
    // If there are no results, grant access.
    return empty($access_results) || in_array(self::ALLOW, $access_results, TRUE) && !in_array(self::KILL, $access_results, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getOperations($plugin_id) {
    return array();
  }
}
