<?php

/**
 * @file
 * Contains \Drupal\payment_reference\Element\PaymentReferenceBase.
 */

namespace Drupal\payment_reference\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Random;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element\FormElementInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\payment\Entity\Payment;
use Drupal\payment\Entity\PaymentInterface;
use Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a base for payment reference elements.
 *
 * Modules that provide form elements that extend this class must also implement
 * hook_entity_extra_field_info() to expose the line_items, payment_method,
 * pay_button, and pay_link elements for the payment bundle the element plugin
 * represents.
 */
abstract class PaymentReferenceBase extends FormElement implements FormElementInterface, ContainerFactoryPluginInterface {

  /**
   * The number of seconds a payment should remain stored.
   */
  const KEY_VALUE_TTL = 3600;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The link generator.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * The payment method selector manager.
   *
   * @var \Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorManagerInterface
   */
  protected $paymentMethodSelectorManager;

  /**
   * The payment storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $paymentStorage;

  /**
   * The random generator.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Creates a new instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   * @param \Drupal\Core\Entity\EntityStorageInterface $payment_storage
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   * @param \Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorManagerInterface $payment_method_selector_manager
   * @param \Drupal\Component\Utility\Random $random
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, RequestStack $request_stack, EntityStorageInterface $payment_storage, TranslationInterface $string_translation, DateFormatter $date_formatter, LinkGeneratorInterface $link_generator, PaymentMethodSelectorManagerInterface $payment_method_selector_manager, Random $random) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dateFormatter = $date_formatter;
    $this->linkGenerator = $link_generator;
    $this->paymentMethodSelectorManager = $payment_method_selector_manager;
    $this->paymentStorage = $payment_storage;
    $this->random = $random;
    $this->requestStack = $request_stack;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $plugin_id = $this->getPluginId();

    return array(
      // The ID of a payment as the (default) value. Changing the value is not
      // supported, so #default_value must be NULL if the user should be allowed
      // to select/add a payment.
      '#default_value' => NULL,
      // An array with IDs of the payment methods the payer is allowed to pay the
      // payment with, or NULL to allow all.
      '#limit_allowed_payment_method_ids' => NULL,
      // The ID of the payment method selector plugin to use.
      '#payment_method_selector_id' => NULL,
      '#process' => array(function(array $element, FormStateInterface $form_state, array $form) use ($plugin_id) {
        /** @var \Drupal\Component\Plugin\PluginManagerInterface $element_info_manager */
        $element_info_manager = \Drupal::service('plugin.manager.element_info');
        /** @var \Drupal\payment_reference\Element\PaymentReferenceBase $element_plugin */
        $element_plugin = $element_info_manager->createInstance($plugin_id);

        return $element_plugin->process($element, $form_state, $form);
      }),
      // The payment that must be made if none are available in the queue yet. It
      // must be an instance of \Drupal\payment\Entity\PaymentInterface.
      '#prototype_payment' => NULL,
      // The ID of the queue category the element is used for. See
      // \Drupal\payment\QueueInterface::loadPaymentIds().
      '#queue_category_id' => NULL,
      // The ID of the account that must own the payment. See
      // \Drupal\payment\QueueInterface::loadPaymentIds().
      '#queue_owner_id' => NULL,
    );
  }

  /**
   * Implements form #process callback.
   */
  public function process(array &$element, FormStateInterface $form_state, array $form) {
    $plugin_id = $this->getPluginId();

    // Set internal configuration.
    $element['#available_payment_id'] = NULL;
    $element['#element_validate'] = array(function(array &$element, FormStateInterface $form_state, array &$form) use ($plugin_id) {
      /** @var \Drupal\Component\Plugin\PluginManagerInterface $element_info_manager */
      $element_info_manager = \Drupal::service('plugin.manager.element_info');
      /** @var \Drupal\payment_reference\Element\PaymentReferenceBase $element_plugin */
      $element_plugin = $element_info_manager->createInstance($plugin_id);

      $payment_method_selector = $element_plugin->getPaymentMethodSelector($element, $form_state);

      $payment_method_selector->validateConfigurationForm($element['container']['payment_form']['payment_method'], $form_state);
      $entity_form_display = $element_plugin->getEntityFormDisplay($element, $form_state);
      $entity_form_display->extractFormValues($payment_method_selector->getPayment(), $element['container']['payment_form'], $form_state);
      $entity_form_display->validateFormValues($payment_method_selector->getPayment(), $element['container']['payment_form'], $form_state);
    });
    $element['#theme_wrappers'] = array('form_element');
    $element['#tree'] = TRUE;

    // Validate the element's configuration.
    if (!is_int($element['#default_value']) && !is_null($element['#default_value'])) {
      throw new \InvalidArgumentException('#default_value must be an integer or NULL, but ' . gettype($element['#default_value']) . ' was given.');
    }
    if (!is_null($element['#limit_allowed_payment_method_ids']) && !is_array($element['#limit_allowed_payment_method_ids'])) {
      throw new \InvalidArgumentException('#limit_allowed_payment_method_ids must be an array or NULL, but ' . gettype($element['#limit_allowed_payment_method_ids']) . ' was given.');
    }
    if (!is_string($element['#queue_category_id'])) {
      throw new \InvalidArgumentException('#queue_category_id must be a string, but ' . gettype($element['#queue_category_id']) . ' was given.');
    }
    if (!is_int($element['#queue_owner_id'])) {
      throw new \InvalidArgumentException('#queue_owner_id must be an integer, but ' . gettype($element['#queue_owner_id']) . ' was given.');
    }
    if (!is_string($element['#payment_method_selector_id'])) {
      throw new \InvalidArgumentException('#payment_method_selector_id must be a string, but ' . gettype($element['#payment_method_selector_id']) . ' was given.');
    }
    if (!($element['#prototype_payment'] instanceof PaymentInterface)) {
      throw new \InvalidArgumentException('#prototype_payment must implement \Drupal\payment\Entity\PaymentInterface.');
    }

    // Find the default payment to use.
    if (!$element['#default_value']) {
      $payment_ids = $this->getPaymentQueue()->loadPaymentIds($element['#queue_category_id'], $element['#queue_owner_id']);
      $element['#available_payment_id'] = $payment_ids ? reset($payment_ids) : NULL;
    }

    // AJAX.
    $ajax_wrapper_id = Html::getClass('payment_reference-' . $element['#name']);
    $element['container'] = array(
      '#attached' => array(
        'js' => array(
          drupal_get_path('module', 'payment_reference') . '/js/payment_reference.js',
          array(
            'type' => 'setting',
            'data' => array(
              'PaymentReferencePaymentAvailable' => array(
                $ajax_wrapper_id => $element['#default_value'] || $element['#available_payment_id'],
              ),
            ),
          ),
        ),
      ),
      '#id' => $ajax_wrapper_id,
      '#type' => 'container',
    );
    $element['container']['payment_form'] = $this->buildPaymentForm($element, $form_state);
    $element['container']['payment_form']['#access'] = !$element['#default_value'] && !$element['#available_payment_id'];

    $element['container']['payment_view'] = $this->buildPaymentView($element, $form_state);
    $element['container']['payment_view']['#access'] = $element['#default_value'] || $element['#available_payment_id'];

    $element['container']['refresh'] = $this->buildRefreshButton($element, $form_state);

    return $element;
  }

  /**
   * Builds the payment form.
   *
   * @param array $element
   *   The root element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   *   A render array.
   */
  protected function buildPaymentForm(array $element, FormStateInterface $form_state) {
    $build = array(
      // Set #parents, so the entity form display does not override it.
      '#parents' => array_merge($element['#parents'], array('container', 'payment_form')),
      '#type' => 'container',
    );
    $payment_method_selector = $this->getPaymentMethodSelector($element, $form_state);
    $build['line_items'] = array(
      '#payment' => $payment_method_selector->getPayment(),
      '#type' => 'payment_line_items_display',
    );
    $build['payment_method'] = $payment_method_selector->buildConfigurationForm(array(), $form_state);
    if ($this->hasTemporaryPayment($element, $form_state)) {
      $this->disableChildren($build['payment_method']);
    }
    $this->getEntityFormDisplay($element, $form_state)->buildForm($payment_method_selector->getPayment(), $build, $form_state);
    $build['pay_link'] = $this->buildCompletePaymentLink($element, $form_state);
    $build['pay_link']['#access'] = $this->hasTemporaryPayment($element, $form_state);
    $build['pay_link']['#process'] = array(array(get_class($this), 'processMaxWeight'));
    $plugin_id = $this->getPluginId();
    $build['pay_button'] = array(
      '#ajax' => array(
        'callback' => function(array $form, FormStateInterface $form_state) use ($plugin_id) {
            /** @var \Drupal\Component\Plugin\PluginManagerInterface $element_info_manager */
            $element_info_manager = \Drupal::service('plugin.manager.element_info');
            /** @var \Drupal\payment_reference\Element\PaymentReferenceBase $element_plugin */
            $element_plugin = $element_info_manager->createInstance($plugin_id);

            return $element_plugin->ajaxPay($form, $form_state);
          },
      ),
      '#limit_validation_errors' => array(array_merge($element['#parents'], array('container', 'payment_form'))),
      '#submit' => array(function(array $form, FormStateInterface $form_state) use ($plugin_id) {
        /** @var \Drupal\Component\Plugin\PluginManagerInterface $element_info_manager */
        $element_info_manager = \Drupal::service('plugin.manager.element_info');
        /** @var \Drupal\payment_reference\Element\PaymentReferenceBase $element_plugin */
        $element_plugin = $element_info_manager->createInstance($plugin_id);
        $element_plugin->pay($form, $form_state);
      }),
      '#type' => 'submit',
      '#value' => $this->t('Pay'),
      '#process' => array(array(get_class($this), 'processMaxWeight')),
    );

    return $build;
  }

  /**
   * Implements form #process callback.
   */
  public static function processMaxWeight(array &$element, FormStateInterface $form_state, array $form) {
    $parent_element = NestedArray::getValue($form, array_slice($element['#array_parents'], 0, -1));
    $weights = array();
    foreach (Element::children($parent_element) as $sibling_key) {
      $weights[] = isset($parent_element[$sibling_key]['#weight']) ? $parent_element[$sibling_key]['#weight'] : 0;
    }
    $element['#weight'] = max($weights) + 1;

    return $element;
  }

  /**
   * Builds the refresh button.
   *
   * @param array $element
   *   The root element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   *   A render array.
   */
  protected function buildRefreshButton(array $element, FormStateInterface $form_state) {
    $class = array('payment_reference-refresh-button');
    if (!$element['#default_value'] && $element['#available_payment_id'] && !$this->hasTemporaryPayment($element, $form_state)) {
      $class[] = 'payment-reference-hidden';
    }
    $plugin_id = $this->getPluginId();
    $build = array(
      '#ajax' => array(
        'callback' => function(array $form, FormStateInterface $form_state) use ($plugin_id) {
          /** @var \Drupal\Component\Plugin\PluginManagerInterface $element_info_manager */
          $element_info_manager = \Drupal::service('plugin.manager.element_info');
          /** @var \Drupal\payment_reference\Element\PaymentReferenceBase $element_plugin */
          $element_plugin = $element_info_manager->createInstance($plugin_id);

          return $element_plugin->ajaxRefresh($form, $form_state);
        },
        'event' => 'mousedown',
        // The AJAX behavior itself does not need a wrapper, but
        // payment_reference.js does.
        'wrapper' => $element['container']['#id'],
      ),
      '#attached' => array(
        'css' => array(
          drupal_get_path('module', 'payment_reference') . '/css/payment_reference.css',
        ),
      ),
      '#attributes' => array(
        // system.module.css's .hidden class's is overridden by button styling,
        // so this needs a custom class.
        'class' => $class,
      ),
      '#limit_validation_errors' => array(),
      '#submit' => array(array($this->pluginDefinition['class'], 'refresh')),
      '#type' => 'submit',
      '#value' => $this->t('Re-check available payments'),
    );

    return $build;
  }

  /**
   * Builds the payment view.
   *
   * @param array $element
   *   The root element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   *   A render array.
   */
  protected function buildPaymentView(array $element, FormStateInterface $form_state) {
    $payment_id = $element['#default_value'] ?: $element['#available_payment_id'];
    /** @var \Drupal\payment\Entity\PaymentInterface|null $payment */
    $payment = $payment_id ? $this->paymentStorage->load($payment_id) : NULL;

    $build = array();
    if ($payment) {
      $currency = $payment->getCurrency();
      $status = $payment->getPaymentStatus();
      $status_definition = $status->getPluginDefinition();
      $build = array(
        '#empty' => $this->t('There are no line items.'),
        '#header' => array($this->t('Amount'), $this->t('Status'), $this->t('Last updated')),
        '#type' => 'table',
      );
      $build[0]['amount'] = array(
        '#markup' => $currency->formatAmount($payment->getAmount()),
      );
      $build[0]['status'] = array(
        '#markup' => $status_definition['label'],
      );
      $build[0]['updated'] = array(
        '#markup' => $this->dateFormatter->format($status->getCreated()),
      );
      if ($payment->access('view')) {
        $build['#header'][] = $this->t('Operations');
        $build[0]['view'] = array(
          '#markup' => $this->t('<a href="@url" target="_blank">View payment details</a> (opens in a new window)', array(
              '@url' => $payment->url(),
            )),
        );
      }
    }

    return $build;
  }

  /**
   * Builds the "Complete payment" link.
   *
   * @param array $element
   *   The root element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   *   A render array.
   */
  protected function buildCompletePaymentLink(array $element, FormStateInterface $form_state) {
    $payment_method_selector = $this->getPaymentMethodSelector($element, $form_state);
    $payment_method = $payment_method_selector->getPaymentMethod();

    $build = array();
    if ($payment_method) {
      $build['message'] = array(
        '#markup' => $this->t('@payment_method_label requires the payment to be completed in a new window.', array(
            '@payment_method_label' => $payment_method->getPluginLabel(),
          )),
      );
      $build['link'] = array(
        '#attributes' => array(
          'class' => array('button', 'payment_reference-complete-payment-link'),
          'target' => '_blank',
        ),
        '#route_name' => 'payment_reference.pay',
        '#route_parameters' => array(
          'storage_key' => $this->getTemporaryPaymentStorageKey($element, $form_state),
        ),
        '#title' => $this->t('Complete payment'),
        '#type' => 'link',
      );
    }

    return $build;
  }

  /**
   * Disables all child elements.
   *
   * @param mixed[] $elements
   */
  protected function disableChildren(array &$elements) {
    foreach (Element::children($elements) as $child_key) {
      $elements[$child_key]['#disabled'] = TRUE;
      $this->disableChildren($elements[$child_key]);
    }
  }

  /**
   * Implements form submit handler.
   */
  public function pay(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->get('triggering_element');
    $root_element_parents = array_slice($triggering_element['#array_parents'], 0, -3);
    $root_element = NestedArray::getValue($form, $root_element_parents);

    $payment_method_selector = $this->getPaymentMethodSelector($root_element, $form_state);
    $payment_method_selector->submitConfigurationForm($root_element['container']['payment_form']['payment_method'], $form_state);

    $this->getEntityFormDisplay($root_element, $form_state)->extractFormValues($payment_method_selector->getPayment(), $root_element['container']['payment_form'], $form_state);

    $payment = $payment_method_selector->getPayment();
    $payment_method = $payment_method_selector->getPaymentMethod();
    $payment->setPaymentMethod($payment_method);
    if ($payment_method->isPaymentExecutionInterruptive()) {
      $this->setTemporaryPayment($root_element, $form_state, $payment);
      if (!$this->requestStack->getCurrentRequest()->isXmlHttpRequest()) {
        $link = $this->linkGenerator->generate($this->t('Complete payment (opens in a new window).'), 'payment_reference.pay', array(
          'storage_key' => $this->getTemporaryPaymentStorageKey($root_element, $form_state),
        ), array(
          'attributes' => array(
            'target' => '_blank',
          ),
        ));
        drupal_set_message($link);
      }
    }
    else {
      $payment->save();
      $payment->execute();
    }
    $form_state->setRebuild();
  }

  /**
   * Implements form AJAX callback.
   */
  public function ajaxPay(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->get('triggering_element');
    $root_element_parents = array_slice($triggering_element['#array_parents'], 0, -3);
    $root_element = NestedArray::getValue($form, $root_element_parents);

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $root_element['container']['#id'], drupal_render($root_element['container'])));

    if ($this->getPaymentMethodSelector($root_element, $form_state)->getPaymentMethod()->isPaymentExecutionInterruptive()) {
      $link = $this->buildCompletePaymentLink($root_element, $form_state);
      $response->addCommand(new OpenModalDialogCommand($this->t('Complete payment'), drupal_render($link)));
    }

    return $response;
  }

  /**
   * Implements form submit handler.
   */
  public static function refresh(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Implements form AJAX callback.
   */
  public function ajaxRefresh(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->get('triggering_element');
    $root_element_parents = array_slice($triggering_element['#array_parents'], 0, -2);
    $root_element = NestedArray::getValue($form, $root_element_parents);

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $root_element['container']['#id'], drupal_render($root_element['container'])));

    return $response;
  }

  /**
   * Gets the payment method selector.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorInterface
   */
  protected function getPaymentMethodSelector(array $element, FormStateInterface $form_state) {
    $key = 'payment_reference.element.payment_reference.payment_method_selector.' . $element['#name'];
    if (!$form_state->has($key)) {
      /** @var \Drupal\payment\Entity\PaymentInterface $prototype_payment */
      $prototype_payment = $element['#prototype_payment'];
      $payment = $prototype_payment->createDuplicate();

      $payment_method_selector = $this->paymentMethodSelectorManager->createInstance($element['#payment_method_selector_id']);
      $payment_method_selector->setPayment($payment);
      $payment_method_selector->setRequired($element['#required']);
      if (!is_null($element['#limit_allowed_payment_method_ids'])) {
        $payment_method_selector->setAllowedPaymentMethods($element['#limit_allowed_payment_method_ids']);
      }

      $form_state->set($key, $payment_method_selector);
    }

    return $form_state->get($key);
  }

  /**
   * Gets the entity form display.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   */
  protected function getEntityFormDisplay(array $element, FormStateInterface $form_state) {
    $key = 'payment_reference.element.payment_reference.entity_form_display.' . $element['#name'];
    if (!$form_state->has($key)) {
      $entity_form_display = EntityFormDisplay::collectRenderDisplay($this->getPaymentMethodSelector($element, $form_state)->getPayment(), 'payment_reference');
      $form_state->set($key, $entity_form_display);
    }

    return $form_state->get($key);
  }

  /**
   * Gets the element temporary payment storage key.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return string
   */
  protected function getTemporaryPaymentStorageKey(array $element, FormStateInterface $form_state) {
    $key = 'payment_reference.element.payment_reference.temporary_storage_key.' . $element['#name'];
    if (!$form_state->has($key)) {
      $form_state->set($key, $this->random->name(128));
    }

    return $form_state->get($key);
  }

  /**
   * Stores the payment temporarily.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   */
  protected function setTemporaryPayment(array $element, FormStateInterface $form_state, PaymentInterface $payment) {
    $this->getTemporaryPaymentStorage()->setWithExpire($this->getTemporaryPaymentStorageKey($element, $form_state), $payment, static::KEY_VALUE_TTL);
  }

  /**
   * Checks if a temporary payment exists.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return bool
   */
  protected function hasTemporaryPayment(array $element, FormStateInterface $form_state) {
    return $this->getTemporaryPaymentStorage()->has($this->getTemporaryPaymentStorageKey($element, $form_state));
  }

  /**
   * Retrieves the temporarily stored payment.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\payment\Entity\PaymentInterface
   */
  protected function getTemporaryPayment(array $element, FormStateInterface $form_state) {
    return $this->getTemporaryPaymentStorage()->get($this->getTemporaryPaymentStorageKey($element, $form_state));
  }

  /**
   * Gets the payment queue.
   *
   * @return \Drupal\payment\QueueInterface
   */
  abstract protected function getPaymentQueue();

  /**
   * Gets the temporary payment storage.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  abstract protected function getTemporaryPaymentStorage();

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {

    // Ignore $input, because the element's value does not come from submitted
    // form values, but from the payment queue.
    if ($element['#default_value']) {
      return $element['#default_value'];
    }
    else {
      /** @var \Drupal\Component\Plugin\PluginManagerInterface $element_info_manager */
      $element_info_manager = \Drupal::service('plugin.manager.element_info');
      /** @var \Drupal\payment_reference\Element\PaymentReferenceBase $element_plugin */
      $element_plugin = $element_info_manager->createInstance($element['#type']);
      $payment_ids = $element_plugin->getPaymentQueue()->loadPaymentIds($element['#queue_category_id'], $element['#queue_owner_id']);

      return $payment_ids ? (int) reset($payment_ids) : NULL;
    }
  }

}
