<?php

/**
 * Contains \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemManager.
 */

namespace Drupal\payment\Plugin\Payment\LineItem;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages discovery and instantiation of payment line item plugins.
 *
 * @see \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface
 */
class PaymentLineItemManager extends DefaultPluginManager implements PaymentLineItemManagerInterface {

  /**
   * Constructs a new class instance.
   *
   * @param \Traversable $namespaces
   *   The namespaces in which to look for plugins.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Payment/LineItem', $namespaces, $module_handler, '\Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface', '\Drupal\payment\Annotations\PaymentLineItem');
    $this->alterInfo('payment_line_item');
    $this->setCacheBackend($cache_backend, 'payment_line_item');
  }

  /**
   * {@inheritdoc}
   */
  public function options() {
    $options = array();
    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      $options[$plugin_id] = $definition['label'];
    }
    natcasesort($options);

    return $options;
  }

}
