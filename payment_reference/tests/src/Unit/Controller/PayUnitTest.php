<?php

/**
 * @file
 * Contains \Drupal\Tests\payment_reference\Unit\Controller\PayUnitTest.
 */

namespace Drupal\Tests\payment_reference\Unit\Controller;

use Drupal\payment_reference\Controller\Pay;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\payment_reference\Controller\Pay
 *
 * @group Payment Reference Field
 */
class PayUnitTest extends UnitTestCase {

  /**
   * The controller under test.
   *
   * @var \Drupal\payment_reference\Controller\Pay
   */
  protected $controller;

  /**
   * The key/value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $keyValueFactory;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  protected function setUp() {
    $this->keyValueFactory = $this->getMock('\Drupal\Core\KeyValueStore\KeyValueFactoryInterface');

    $this->controller = new Pay($this->keyValueFactory);
  }

  /**
   * @covers ::create
   */
  function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('keyvalue.expirable', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->keyValueFactory),
    );
    $container->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($map));

    $form = Pay::create($container);
    $this->assertInstanceOf('\Drupal\payment_reference\Controller\Pay', $form);
  }

  /**
   * @covers ::execute
   */
  public function testExecute() {
    $payment_type = $this->getMock('\Drupal\payment\Plugin\Payment\Type\PaymentTypeInterface');
    $payment_type->expects($this->once())
      ->method('resumeContext');

    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $payment->expects($this->once())
      ->method('execute');
    $payment->expects($this->once())
      ->method('getPaymentType')
      ->willReturn($payment_type);

    $storage_key = $this->randomMachineName();

    $storage = $this->getMock('\Drupal\Core\KeyValueStore\KeyValueStoreInterface');
    $storage->expects($this->once())
      ->method('get')
      ->with($storage_key)
      ->willReturn($payment);

    $this->keyValueFactory->expects($this->once())
      ->method('get')
      ->with('payment.payment_type.payment_reference')
      ->willReturn($storage);

    $this->controller->execute($storage_key);
  }

  /**
   * @covers ::access
   *
   * @dataProvider providerTestAccess
   */
  public function testAccess($expected, $payment_exists) {
    $storage_key = $this->randomMachineName();

    $storage = $this->getMock('\Drupal\Core\KeyValueStore\KeyValueStoreInterface');
    $storage->expects($this->once())
      ->method('has')
      ->with($storage_key)
      ->willReturn($payment_exists);

    $this->keyValueFactory->expects($this->once())
      ->method('get')
      ->with('payment.payment_type.payment_reference')
      ->willReturn($storage);

    $request = $this->getMockBuilder('\Symfony\Component\HttpFoundation\Request')
      ->disableOriginalConstructor()
      ->getMock();

    $this->assertSame($expected, $this->controller->access($request, $storage_key)->isAllowed());
  }

  /**
   * Provides data to testAccess().
   */
  public function droviderTestAccess() {
    return array(
      array(TRUE, TRUE),
      array(FALSE, FALSE),
    );
  }

}
