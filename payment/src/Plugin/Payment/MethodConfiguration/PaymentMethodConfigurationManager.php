<?php

/**
 * Contains \Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationManager.
 */

namespace Drupal\payment\Plugin\Payment\MethodConfiguration;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages discovery and instantiation of payment method configuration plugins.
 *
 * @see \Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationInterface
 */
class PaymentMethodConfigurationManager extends DefaultPluginManager implements PaymentMethodConfigurationManagerInterface {

  /**
   * Constructs a new class instance.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Payment/MethodConfiguration', $namespaces, $module_handler, '\Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationInterface', '\Drupal\payment\Annotations\PaymentMethodConfiguration');
    $this->alterInfo('payment_method_configuration');
    $this->setCacheBackend($cache_backend, 'payment_method_configuration');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = array()) {
    // If a plugin is missing, use the default.
    try {
      return parent::createInstance($plugin_id, $configuration);
    }
    catch (PluginException $e) {
      return parent::createInstance('payment_unavailable', $configuration);
    }
  }
}
