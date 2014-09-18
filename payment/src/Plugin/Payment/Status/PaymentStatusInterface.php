<?php

/**
 * Contains \Drupal\payment\plugin\payment\status\PaymentStatusInterface.
 */

namespace Drupal\payment\plugin\payment\status;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\payment\Entity\PaymentInterface;

/**
 * A payment status plugin.
 */
interface PaymentStatusInterface extends PluginInspectionInterface, ConfigurablePluginInterface {

  /**
   * Sets the created date and time.
   *
   * @param string $created
   *   A Unix timestamp.
   * *
   * @return static
   */
  public function setCreated($created);

  /**
   * Gets the created date and time.
   *
   * @return string
   *   A Unix timestamp.
   */
  public function getCreated();

  /**
   * Sets the payment the status belongs to.
   *
   * @param \Drupal\payment\Entity\PaymentInterface $payment
   *
   * @return static
   */
  public function setPayment(PaymentInterface $payment);

  /**
   * Gets the payment this status belongs to.
   *
   * @return \Drupal\payment\Entity\PaymentInterface
   */
  public function getPayment();

  /**
   * Sets the ID.
   *
   * @param int $id
   * *
   * @return static
   */
  public function setId($id);

  /**
   * Gets the ID.
   *
   * @return int
   */
  public function getId();

  /**
   * Gets this payment status's ancestors.
   *
   * @return array
   *   The plugin IDs of this status's ancestors.
   */
  function getAncestors();

  /**
   * Gets this payment status's children.
   *
   * @return array
   *   The plugin IDs of this status's children.
   */
  public function getChildren();

  /**
   * Get this payment status's descendants.
   *
   * @return array
   *   The machine names of this status's descendants.
   */
  function getDescendants();

  /**
   * Checks if the status has a given other status as one of its ancestors.
   *.
   * @param string $plugin_id
   *   The payment status plugin ID to check against.
   *
   * @return boolean
   */
  function hasAncestor($plugin_id);

  /**
   * Checks if the status is equal to a given other status or has it one of
   * its ancestors.
   *
   * @param string $plugin_id
   *   The payment status plugin ID to check against.
   *
   * @return boolean
   */
  function isOrHasAncestor($plugin_id);

  /**
   * Gets this payment status's human-readable label.
   *
   * @return string
   */
  function getLabel();
}
