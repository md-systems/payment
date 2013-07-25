<?php

/**
 * @file
 * Contains class \Drupal\payment\Tests\Plugin\payment\status\BaseUnitTest.
 */

namespace Drupal\payment\Tests\Plugin\payment\status;

use Drupal\simpletest\DrupalUnitTestBase;
use Drupal\payment\Plugin\payment\status\PaymentStatusInterface;

/**
 * Tests \Drupal\payment\Plugin\payment\status\Base.
 */
class BaseUnitTest extends DrupalUnitTestBase {

  public static $modules = array('payment');

  /**
   * {@inheritdoc}
   */
  static function getInfo() {
    return array(
      'description' => '',
      'name' => '\Drupal\payment\Plugin\payment\status\Base unit test',
      'group' => 'Payment',
    );
  }

  /**
   * {@inheritdoc
   */
  function setup() {
    parent::setUp();
    $this->manager = \Drupal::service('plugin.manager.payment.status');
    $this->status = $this->manager->createInstance('payment_created');
  }

  /**
   * Tests setCreated() and getCreated().
   */
  function testGetCreated() {
    $created = 123;
    $this->assertTrue($this->status->setCreated($created) instanceof PaymentStatusInterface);
    $this->assertIdentical($this->status->getCreated(), $created);
  }

  /**
   * Tests setPaymentId() and getPaymentId().
   */
  function testGetPaymentId() {
    $id = 7;
    $this->assertTrue($this->status->setPaymentId($id) instanceof PaymentStatusInterface);
    $this->assertIdentical($this->status->getPaymentId(), $id);
  }

  /**
   * Tests setId() and getId().
   */
  function testGetId() {
    $id= 7;
    $this->assertTrue($this->status->setId($id) instanceof PaymentStatusInterface);
    $this->assertIdentical($this->status->getId(), $id);
  }

  /**
   * Tests getChildren().
   */
  function testGetChildren() {
    $status = $this->manager->createInstance('payment_no_money_transferred');
    $expected = array('payment_created', 'payment_failed', 'payment_pending');
    $this->assertEqual($status->getChildren(), $expected);
  }

  /**
   * Tests getDescendants().
   */
  function testGetDescendants() {
    $status = $this->manager->createInstance('payment_no_money_transferred');
    $expected = array('payment_created', 'payment_failed', 'payment_pending', 'payment_authorization_failed', 'payment_cancelled', 'payment_expired');
    $this->assertEqual($status->getDescendants(), $expected);
  }

  /**
   * Tests getAncestors().
   */
  function testGetAncestors() {
    $status = $this->manager->createInstance('payment_authorization_failed');
    $expected = array('payment_failed', 'payment_no_money_transferred');
    $this->assertEqual($status->getAncestors(), $expected);
  }

  /**
   * Tests hasAncestor().
   */
  function testHasAncestor() {
    $status = $this->manager->createInstance('payment_failed');
    $this->assertTrue($status->isOrHasAncestor('payment_no_money_transferred'));
  }

  /**
   * Tests isOrHasAncestor().
   */
  function testIsOrHasAncestor() {
    $status = $this->manager->createInstance('payment_failed');
    $this->assertTrue($status->isOrHasAncestor('payment_failed'));
    $this->assertTrue($status->isOrHasAncestor('payment_no_money_transferred'));
  }
}
