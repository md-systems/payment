<?php

/**
 * @file
 * Contains \Drupal\payment\Controller\PaymentMethod.
 */

namespace Drupal\payment\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\payment\Entity\PaymentMethodConfigurationInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodManagerInterface;
use Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for payment method routes.
 */
class PaymentMethod extends ControllerBase {

  /**
   * The payment method configuration plugin manager.
   *
   * @var \Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationManagerInterface
   */
  protected $paymentMethodConfigurationManager;

  /**
   * The payment method plugin manager used for testing.
   *
   * @var \Drupal\payment\Plugin\Payment\Method\PaymentMethodManagerInterface
   */
  protected $paymentMethodManager;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translator.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   * @param \Drupal\payment\Plugin\Payment\Method\PaymentMethodManagerInterface $payment_method_manager
   * @param \Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationManagerInterface $payment_method_configuration_manager
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   * @param \Drupal\Core\Session\AccountInterface $current_user
   */
  public function __construct(TranslationInterface $string_translation, EntityManagerInterface $entity_manager, PaymentMethodManagerInterface $payment_method_manager, PaymentMethodConfigurationManagerInterface $payment_method_configuration_manager, EntityFormBuilderInterface $entity_form_builder, UrlGeneratorInterface $url_generator, AccountInterface $current_user) {
    $this->currentUser = $current_user;
    $this->entityManager = $entity_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->paymentMethodManager = $payment_method_manager;
    $this->paymentMethodConfigurationManager = $payment_method_configuration_manager;
    $this->stringTranslation = $string_translation;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('string_translation'), $container->get('entity.manager'), $container->get('plugin.manager.payment.method'), $container->get('plugin.manager.payment.method_configuration'), $container->get('entity.form_builder'), $container->get('url_generator'), $container->get('current_user'));
  }

  /**
   * Enables a payment method configuration.
   *
   * @param \Drupal\payment\Entity\PaymentMethodConfigurationInterface $payment_method_configuration
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function enable(PaymentMethodConfigurationInterface $payment_method_configuration) {
    $payment_method_configuration->enable();
    $payment_method_configuration->save();

    return new RedirectResponse($this->urlGenerator->generateFromRoute('payment.payment_method_configuration.list', array(), array(
      'absolute' => TRUE,
    )));
  }

  /**
   * Disables a payment method configuration.
   *
   * @param \Drupal\payment\Entity\PaymentMethodConfigurationInterface $payment_method_configuration
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function disable(PaymentMethodConfigurationInterface $payment_method_configuration) {
    $payment_method_configuration->disable();
    $payment_method_configuration->save();

    return new RedirectResponse($this->urlGenerator->generateFromRoute('payment.payment_method_configuration.list', array(), array(
      'absolute' => TRUE,
    )));
  }

  /**
   * Lists all available payment method plugins.
   *
   * @return array
   *   A renderable array.
   */
  public function listPlugins() {
    $rows = array();
    foreach ($this->paymentMethodManager->getDefinitions() as $plugin_id => $definition) {
      $operations_provider = $this->paymentMethodManager->getOperationsProvider($plugin_id);
      $row = array(
        'label' => array(
          '#markup' => $definition['label'],
        ),
        'status' => array(
          '#markup' => $definition['active'] ? $this->t('Enabled') : $this->t('Disabled'),
        ),
        'operations' => array(
          '#type' => 'operations',
          '#links' => $operations_provider ? $operations_provider->getOperations($plugin_id) : array(),
        ),
      );
      if (!$definition['active']) {
        $row['#attributes']['class'] = array('payment-method-disabled');
      }
      $rows[$plugin_id] = $row;
    }

    return array(
      '#attached' => array(
        'css' => array(
          drupal_get_path('module', 'payment') . '/css/payment.css',
        ),
      ),
      '#attributes' => array(
        'class' => array('payment-method-list'),
      ),
      '#header' => array($this->t('Name'), $this->t('Status'), $this->t('Operations')),
      '#type' => 'table',
    ) + $rows;
  }

  /**
   * Displays a list of available payment method plugins.
   *
   * @return string
   */
  public function select() {
    $definitions = $this->paymentMethodConfigurationManager->getDefinitions();
    unset($definitions['payment_unavailable']);
    $access_controller = $this->entityManager->getAccessControlHandler('payment_method_configuration');
    $items = array();
    foreach ($definitions as $plugin_id => $definition) {
      $access = $access_controller->createAccess($plugin_id);
      if ($access) {
        $items[] = array(
          'title' => $definition['label'],
          'description' => $definition['description'],
          'localized_options' => array(),
          'url' => new Url('payment.payment_method_configuration.add', array(
              'plugin_id' => $plugin_id,
            )),
        );
      }
    }

    return array(
      '#theme' => 'admin_block_content',
      '#content' => $items,
    );
  }

  /**
   * Checks access to self::select().
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return string
   *   self::ALLOW, self::DENY, or self::KILL.
   */
  public function selectAccess(Request $request) {
    $definitions = $this->paymentMethodConfigurationManager->getDefinitions();
    unset($definitions['payment_unavailable']);
    $access_controller = $this->entityManager->getAccessControlHandler('payment_method_configuration');
    foreach (array_keys($definitions) as $plugin_id) {
      if ($access_controller->createAccess($plugin_id, $this->currentUser)) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

  /**
   * Displays a payment method configuration add form.
   *
   * @param string $plugin_id
   *
   * @return array
   */
  public function add($plugin_id) {
    $payment_method_configuration = $this->entityManager->getStorage('payment_method_configuration')->create(array(
      'pluginId' => $plugin_id,
    ));

    return $this->entityFormBuilder->getForm($payment_method_configuration, 'default');
  }

  /**
   * Returns the title for the payment method configuration add form.
   *
   * @param string $plugin_id
   *
   * @return string
   */
  public function addTitle($plugin_id) {
    $plugin_definition = $this->paymentMethodConfigurationManager->getDefinition($plugin_id);

    return $this->t('Add %label payment method configuration', array(
      '%label' => $plugin_definition['label'],
    ));
  }

  /**
   * Checks access to self::add().
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return string
   *   self::ALLOW, self::DENY, or self::KILL.
   */
  public function addAccess(Request $request) {
    $plugin_id = $request->attributes->get('plugin_id');

    return $this->entityManager->getAccessControlHandler('payment_method_configuration')->createAccess($plugin_id, $this->currentUser, TRUE);
  }

  /**
   * Returns the title for the payment method configuration edit form.
   *
   * @param \Drupal\payment\Entity\PaymentMethodConfigurationInterface $payment_method_configuration
   *
   * @return string
   */
  public function editTitle(PaymentMethodConfigurationInterface $payment_method_configuration) {
    return $this->t('Edit %label', array(
      '%label' => $payment_method_configuration->label(),
    ));
  }

  /**
   * Returns the title for the payment method configuration duplicate form.
   *
   * @param \Drupal\payment\Entity\PaymentMethodConfigurationInterface $payment_method_configuration
   *
   * @return string
   */
  public function duplicateTitle(PaymentMethodConfigurationInterface $payment_method_configuration) {
    return $this->t('Duplicate %label', array(
      '%label' => $payment_method_configuration->label(),
    ));
  }

  /**
   * Displays a payment method clone form.
   *
   * @param \Drupal\payment\Entity\PaymentMethodConfigurationInterface $payment_method_configuration
   *
   * @return array
   */
  public function duplicate(PaymentMethodConfigurationInterface $payment_method_configuration) {
    /** @var \Drupal\payment\Entity\PaymentMethodConfigurationInterface $clone */
    $clone = $payment_method_configuration
      ->createDuplicate();
    $clone->setLabel($this->t('!label (duplicate)', array(
        '!label' => $payment_method_configuration->label(),
      )));

    return $this->entityFormBuilder->getForm($clone, 'default');
  }

}
