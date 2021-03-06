<?php

/**
 * @file Contains \Drupal\Tests\payment\Unit\Plugin\Payment\LineItem\PaymentLineItemManagerUnitTest.
 */

namespace Drupal\Tests\payment\Unit\Plugin\Payment\LineItem;

use Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemManager;
use Drupal\Tests\UnitTestCase;
use Zend\Stdlib\ArrayObject;

/**
 * @coversDefaultClass \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemManager
 *
 * @group Payment
 */
class PaymentLineItemManagerUnitTest extends UnitTestCase {

  /**
   * The cache backend used for testing.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $cache;

  /**
   * The plugin discovery used for testing.
   *
   * @var \Drupal\Component\Plugin\Discovery\DiscoveryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $discovery;

  /**
   * The plugin factory used for testing.
   *
   * @var \Drupal\Component\Plugin\Factory\DefaultFactory|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $factory;

  /**
   * The module handler used for testing.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moduleHandler;

  /**
   * The payment line_item plugin manager under test.
   *
   * @var \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemManagerInterface
   */
  public $paymentLineItemManager;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  public function setUp() {
    $this->discovery = $this->getMock('\Drupal\Component\Plugin\Discovery\DiscoveryInterface');

    $this->factory = $this->getMockBuilder('\Drupal\Component\Plugin\Factory\DefaultFactory')
      ->disableOriginalConstructor()
      ->getMock();

    $this->moduleHandler = $this->getMock('\Drupal\Core\Extension\ModuleHandlerInterface');

    $this->cache = $this->getMock('\Drupal\Core\Cache\CacheBackendInterface');

    $namespaces = new ArrayObject();

    $this->paymentLineItemManager = new PaymentLineItemManager($namespaces, $this->cache, $this->moduleHandler);
    $discovery_property = new \ReflectionProperty($this->paymentLineItemManager, 'discovery');
    $discovery_property->setAccessible(TRUE);
    $discovery_property->setValue($this->paymentLineItemManager, $this->discovery);
    $factory_property = new \ReflectionProperty($this->paymentLineItemManager, 'factory');
    $factory_property->setAccessible(TRUE);
    $factory_property->setValue($this->paymentLineItemManager, $this->factory);
  }

  /**
   * @covers ::getDefinitions
   */
  public function testGetDefinitions() {
    $definitions = array(
      'foo' => array(
        'label' => $this->randomMachineName(),
      ),
    );
    $this->discovery->expects($this->once())
      ->method('getDefinitions')
      ->will($this->returnValue($definitions));
    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with('payment_line_item');
    $this->assertSame($definitions, $this->paymentLineItemManager->getDefinitions());
  }

  /**
   * @covers ::options
   * @depends testGetDefinitions
   */
  public function testOptions() {
    $label = $this->randomMachineName();
    $definitions = array(
      'foo' => array(
        'label' => $label,
      ),
    );
    $this->discovery->expects($this->once())
      ->method('getDefinitions')
      ->will($this->returnValue($definitions));
    $expected_options = array(
      'foo' => $label,
    );
    $this->assertSame($expected_options, $this->paymentLineItemManager->options());
  }
}
