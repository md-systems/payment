<?php

/**
 * Contains \Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorManager.
 */

namespace Drupal\payment\Plugin\Payment\MethodSelector;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages discovery and instantiation of payment method selector plugins.
 *
 * @see \Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorInterface
 */
class PaymentMethodSelectorManager extends DefaultPluginManager implements PaymentMethodSelectorManagerInterface {

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
    parent::__construct('Plugin/Payment/MethodSelector', $namespaces, $module_handler, '\Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorInterface', '\Drupal\payment\Annotations\PaymentMethodSelector');
    $this->alterInfo('payment_method_selector');
    $this->setCacheBackend($cache_backend, 'payment_method_selector');
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
      return parent::createInstance('payment_select_list', $configuration);
    }
  }

  /**
   * {@inheritdoc}
   */
  function options() {
    $options = array();
    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      $options[$plugin_id] = $definition['label'];
    }
    natcasesort($options);

    return $options;
  }
}
