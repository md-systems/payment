<?php

/**
 * @file
 * Contains \Drupal\Tests\payment\Unit\Hook\EntityBundleInfoUnitTest.
 */

namespace Drupal\Tests\payment\Unit\Hook;

use Drupal\payment\Hook\EntityBundleInfo;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\payment\Hook\EntityBundleInfo
 *
 * @group Payment
 */
class EntityBundleInfoUnitTest extends UnitTestCase {

  /**
   * The payment method configuration manager used for testing.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentMethodConfigurationManager;

  /**
   * The payment type manager used for testing.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentTypeManager;

  /**
   * The service under test.
   *
   * @var \Drupal\payment\Hook\EntityBundleInfo.
   */
  protected $service;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  public function setUp() {
    $this->paymentMethodConfigurationManager = $this->getMock('\Drupal\Component\Plugin\PluginManagerInterface');

    $this->paymentTypeManager = $this->getMock('\Drupal\Component\Plugin\PluginManagerInterface');

    $this->service = new EntityBundleInfo($this->paymentTypeManager, $this->paymentMethodConfigurationManager);
  }

  /**
   * @covers ::invoke
   */
  public function testInvoke() {
    $payment_type_plugin_id = $this->randomMachineName();
    $payment_type_label = $this->randomMachineName();
    $payment_type_definitions = array(
      $payment_type_plugin_id => array(
        'label' => $payment_type_label
      ),
    );
    $this->paymentTypeManager->expects($this->once())
      ->method('getDefinitions')
      ->will($this->returnValue($payment_type_definitions));

    $payment_method_configuration_plugin_id = $this->randomMachineName();
    $payment_method_configuration_label = $this->randomMachineName();
    $payment_method_configuration_definitions = array(
      $payment_method_configuration_plugin_id => array(
        'label' => $payment_method_configuration_label
      ),
    );
    $this->paymentMethodConfigurationManager->expects($this->once())
      ->method('getDefinitions')
      ->will($this->returnValue($payment_method_configuration_definitions));

    $entity_types = array(
      'payment' => $payment_type_definitions,
      'payment_method_configuration' => $payment_method_configuration_definitions,
    );
    $entity_types_bundles_info = $this->service->invoke();
    $this->assertSame(count($entity_types), count($entity_types_bundles_info));
    foreach ($entity_types as $entity_type => $plugin_definitions) {
      $entity_type_bundles_info = $entity_types_bundles_info[$entity_type];
      $this->assertInternalType('array', $entity_type_bundles_info);
      foreach ($plugin_definitions as $plugin_id => $plugin_definition) {
        $this->assertArrayHasKey('label', $entity_type_bundles_info[$plugin_id]);
        $this->assertSame($plugin_definition['label'], $entity_type_bundles_info[$plugin_id]['label']);
      }
    }
  }
}
