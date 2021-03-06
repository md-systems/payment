<?php

/**
 * @file
 * Contains \Drupal\Tests\payment\Unit\Plugin\Payment\Status\PaymentStatusBaseUnitTest.
 */

namespace Drupal\Tests\payment\Unit\Plugin\Payment\Status;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\payment\Plugin\Payment\Status\PaymentStatusBase
 *
 * @group Payment
 */
class PaymentStatusBaseUnitTest extends UnitTestCase {

  /**
   * The payment status plugin manager used for testing.
   *
   * @var \Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  public $paymentStatusManager;

  /**
   * The definition of the payment status under test.
   *
   * @var array
   */
  public $pluginDefinition;

  /**
   * The ID of the payment status under test.
   *
   * @var string
   */
  public $pluginId;

  /**
   * The payment status under test.
   *
   * @var \Drupal\payment\Plugin\Payment\Status\PaymentStatusBase|\PHPUnit_Framework_MockObject_MockObject
   */
  public $status;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  public function setup() {
    $this->paymentStatusManager = $this->getMock('\Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface');

    $configuration = array();
    $this->pluginId = $this->randomMachineName();
    $this->pluginDefinition = array(
      'label' => $this->randomMachineName(),
    );
    $this->status = $this->getMockBuilder('\Drupal\payment\Plugin\Payment\Status\PaymentStatusBase')
      ->setConstructorArgs(array($configuration, $this->pluginId, $this->pluginDefinition, $this->paymentStatusManager))
      ->getMockForAbstractClass();
  }

  /**
   * @covers ::create
   */
  public function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('plugin.manager.payment.status', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->paymentStatusManager),
    );
    $container->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($map));

    /** @var \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemBase $class_name */
    $class_name = get_class($this->status);

    $line_item = $class_name::create($container, array(), $this->randomMachineName(), array());
    $this->assertInstanceOf('\Drupal\payment\Plugin\Payment\Status\PaymentStatusBase', $line_item);
  }

  /**
   * @covers ::calculateDependencies
   */
  public function testCalculateDependencies() {
    $this->assertSame(array(), $this->status->calculateDependencies());
  }

  /**
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfiguration() {
    $expected_configuration = array(
      'created' => time(),
      'id' => 0,
    );
    $this->assertSame($expected_configuration, $this->status->defaultConfiguration());
  }

  /**
   * @covers ::setConfiguration
   * @covers ::getConfiguration
   */
  public function testGetConfiguration() {
    $configuration = array(
      $this->randomMachineName() => mt_rand(),
    );
    $this->assertNull($this->status->setConfiguration($configuration));
    $this->assertSame($configuration, $this->status->getConfiguration());
  }

  /**
   * @covers ::setCreated
   * @covers ::getCreated
   */
  public function testGetCreated() {
    $created = mt_rand();
    $this->assertSame($this->status, $this->status->setCreated($created));
    $this->assertSame($created, $this->status->getCreated());
  }

  /**
   * @covers ::setPayment
   * @covers ::getPayment
   */
  public function testGetPayment() {
    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $this->assertSame($this->status, $this->status->setPayment($payment));
    $this->assertSame($payment, $this->status->getPayment());
  }

  /**
   * @covers ::setId
   * @covers ::getId
   */
  public function testGetId() {
    $created = mt_rand();
    $this->assertSame($this->status, $this->status->setId($created));
    $this->assertSame($created, $this->status->getId());
  }

  /**
   * @covers ::getChildren
   */
  public function testGetChildren() {
    $children = array($this->randomMachineName());
    $this->paymentStatusManager->expects($this->once())
      ->method('getChildren')
      ->with($this->pluginId)
      ->will($this->returnValue($children));
    $this->assertSame($children, $this->status->getChildren());
  }

  /**
   * @covers ::getDescendants
   */
  public function testGetDescendants() {
    $descendants = array($this->randomMachineName());
    $this->paymentStatusManager->expects($this->once())
      ->method('getDescendants')
      ->with($this->pluginId)
      ->will($this->returnValue($descendants));
    $this->assertSame($descendants, $this->status->getDescendants());
  }

  /**
   * @covers ::getAncestors
   */
  public function testGetAncestors() {
    $ancestors = array($this->randomMachineName());
    $this->paymentStatusManager->expects($this->once())
      ->method('getAncestors')
      ->with($this->pluginId)
      ->will($this->returnValue($ancestors));
    $this->assertSame($ancestors, $this->status->getAncestors());
  }

  /**
   * @covers ::hasAncestor
   */
  public function testHasAncestor() {
    $expected = TRUE;
    $this->paymentStatusManager->expects($this->once())
      ->method('hasAncestor')
      ->with($this->pluginId)
      ->will($this->returnValue($expected));
    $this->assertSame($expected, $this->status->hasAncestor($this->pluginId));
  }

  /**
   * @covers ::isOrHasAncestor
   */
  public function testIsOrHasAncestor() {
    $expected = TRUE;
    $this->paymentStatusManager->expects($this->once())
      ->method('isOrHasAncestor')
      ->with($this->pluginId)
      ->will($this->returnValue($expected));
    $this->assertSame($expected, $this->status->isOrHasAncestor($this->pluginId));
  }

  /**
   * @covers ::getLabel
   */
  public function testGetLabel() {
    $this->assertSame($this->pluginDefinition['label'], $this->status->getLabel());
  }

}
