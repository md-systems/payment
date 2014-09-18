<?php

/**
 * Contains \Drupal\payment\Plugin\Payment\Status\PaymentStatusManager.
 */

namespace Drupal\payment\Plugin\Payment\Status;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\payment\Plugin\Payment\OperationsProviderPluginManagerTrait;

/**
 * Manages discovery and instantiation of payment status plugins.
 *
 * @see \Drupal\payment\Plugin\Payment\Status\PaymentStatusInterface
 */
class PaymentStatusManager extends DefaultPluginManager implements PaymentStatusManagerInterface {

  use OperationsProviderPluginManagerTrait;

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
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class_resolver.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ClassResolverInterface $class_resolver) {
    parent::__construct('Plugin/Payment/Status', $namespaces, $module_handler, '\Drupal\payment\plugin\payment\status\PaymentStatusInterface', '\Drupal\payment\Annotations\PaymentStatus');
    $this->alterInfo('payment_status');
    $this->setCacheBackend($cache_backend, 'payment_status');
    $this->classResolver = $class_resolver;
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
      return parent::createInstance('payment_unknown', $configuration);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function options(array $limit_plugin_ids = NULL) {
    return $this->optionsLevel($this->hierarchy($limit_plugin_ids), 0);
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchy(array $limit_plugin_ids = NULL) {
    static $hierarchy = NULL;

    if (is_null($hierarchy)) {
      $parents = array();
      $children = array();
      $definitions = $this->getDefinitions();
      if (is_array($limit_plugin_ids)) {
        $definitions = array_intersect_key($definitions, array_flip($limit_plugin_ids));
      }
      uasort($definitions, array($this, 'sort'));
      foreach ($definitions as $plugin_id => $definition) {
        if (!empty($definition['parent_id'])) {
          $children[$definition['parent_id']][] = $plugin_id;
        }
        else {
          $parents[] = $plugin_id;
        }
      }
      $hierarchy = $this->hierarchyLevel($parents, $children);
    }

    return $hierarchy;
  }

  /**
   * Helper function for self::hierarchy().
   *
   * @param array $parent_plugin_ids
   *   An array with IDs of plugins that are part of the same hierarchy level.
   * @param array $child_plugin_ids
   *   Keys are plugin IDs. Values are arrays with those plugin's child
   *   plugin IDs.
   *
   * @return array
   *   The return value is identical to that of self::hierarchy().
   */
  protected function hierarchyLevel(array $parent_plugin_ids, array $child_plugin_ids) {
    $hierarchy = array();
    foreach ($parent_plugin_ids as $plugin_id) {
      $hierarchy[$plugin_id] = isset($child_plugin_ids[$plugin_id]) ? $this->hierarchyLevel($child_plugin_ids[$plugin_id], $child_plugin_ids) : array();
    }

    return $hierarchy;
  }

  /**
   * Helper function for self::options().
   *
   * @param array $hierarchy
   *   A plugin ID hierarchy as returned by self::hierarchy().
   * @param integer $depth
   *   The depth of $hierarchy's top-level items as seen from the original
   *   hierarchy's root (this function is recursive), starting with 0.
   *
   * @return array
   *   The return value is identical to that of self::options().
   */
  protected function optionsLevel(array $hierarchy, $depth) {
    $definitions = $this->getDefinitions();
    $options = array();
    $prefix = $depth ? str_repeat('-', $depth) . ' ' : '';
    foreach ($hierarchy as $plugin_id => $child_plugin_ids) {
      $options[$plugin_id] = $prefix . $definitions[$plugin_id]['label'];
      $options += $this->optionsLevel($child_plugin_ids, $depth + 1);
    }

    return $options;
  }

  /**
   * Implements uasort() callback to sort plugin definitions by label.
   */
  protected function sort(array $definition_a, array $definition_b) {
    return strcmp($definition_a['label'], $definition_b['label']);
  }

  /**
   * {@inheritdoc}
   */
  public function getAncestors($plugin_id) {
    $definition = $this->getDefinition($plugin_id);
    if (isset($definition['parent_id'])) {
      $parent_id = $definition['parent_id'];
      return array_unique(array_merge(array($parent_id), $this->getAncestors($parent_id)));
    }
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren($plugin_id) {
    $child_plugin_ids = array();
    foreach ($this->getDefinitions() as $definition) {
      if (isset($definition['parent_id']) && $definition['parent_id'] == $plugin_id) {
        $child_plugin_ids[] = $definition['id'];
      }
    }

    return $child_plugin_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescendants($plugin_id) {
    $child_plugin_ids = $this->getChildren($plugin_id);
    $descendant_plugin_ids = $child_plugin_ids;
    foreach ($child_plugin_ids as $child_plugin_id) {
      $descendant_plugin_ids = array_merge($descendant_plugin_ids, $this->getDescendants($child_plugin_id));
    }

    return array_unique($descendant_plugin_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function hasAncestor($plugin_id, $ancestor_plugin_id) {
    return in_array($ancestor_plugin_id, $this->getAncestors($plugin_id));
  }

  /**
   * {@inheritdoc}
   */
  public function isOrHasAncestor($plugin_id, $ancestor_plugin_id) {
    return $plugin_id == $ancestor_plugin_id || $this->hasAncestor($plugin_id, $ancestor_plugin_id);
  }

}
