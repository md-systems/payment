<?php

/**
 * @file
 * Contains \Drupal\Tests\payment\Unit\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationListBuilderUnitTest.
 */

namespace Drupal\Tests\payment\Unit\Entity\PaymentMethodConfiguration {

use Drupal\Core\Url;
use Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationListBuilder;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationListBuilder
 *
 * @group Payment
 */
class PaymentMethodConfigurationListBuilderUnitTest extends UnitTestCase {

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityStorage;

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityType;

  /**
   * The list builder under test.
   *
   * @var \Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationListBuilder
   */
  protected $listBuilder;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moduleHandler;

  /**
   * The payment method configuration manager.
   *
   * @var \Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentMethodConfigurationManager;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  public function setUp() {
    $this->entityStorage = $this->getMock('\Drupal\Core\Entity\EntityStorageInterface');

    $this->entityType = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');

    $this->moduleHandler = $this->getMock('\Drupal\Core\Extension\ModuleHandlerInterface');

    $this->paymentMethodConfigurationManager = $this->getMock('\Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationManagerInterface');

    $this->stringTranslation = $this->getMock('\Drupal\Core\StringTranslation\TranslationInterface');
    $this->stringTranslation->expects($this->any())
      ->method('translate')
      ->will($this->returnArgument(0));

    $this->listBuilder = new PaymentMethodConfigurationListBuilder($this->entityType, $this->entityStorage, $this->stringTranslation, $this->moduleHandler, $this->paymentMethodConfigurationManager);
  }

  /**
   * @covers ::createInstance
   */
  function testCreateInstance() {
    $entity_manager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');
    $entity_manager->expects($this->once())
      ->method('getStorage')
      ->with('payment_method_configuration')
      ->will($this->returnValue($this->entityStorage));

    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('entity.manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $entity_manager),
      array('module_handler', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->moduleHandler),
      array('plugin.manager.payment.method_configuration', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->paymentMethodConfigurationManager),
      array('string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation),
    );
    $container->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($map));

    $form = PaymentMethodConfigurationListBuilder::createInstance($container, $this->entityType);
    $this->assertInstanceOf('\Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationListBuilder', $form);
  }

  /**
   * @covers ::buildHeader
   */
  function testBuildHeader() {
    $header = $this->listBuilder->buildHeader();
    $expected = array(
      'label' => 'Name',
      'plugin' => 'Type',
      'owner' => array(
        'data' => 'Owner',
        'class' => array(RESPONSIVE_PRIORITY_LOW),
      ),
      'status' => array(
        'data' => 'Status',
        'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
      ),
      'operations' => 'Operations',
    );
    $this->assertSame($expected, $header);
  }

  /**
   * @covers ::buildRow
   */
  function testBuildRow() {
    $payment_method_configuration_entity_label = $this->randomMachineName();
    $payment_method_configuration_entity_status = FALSE;
    $payment_method_configuration_plugin_id = $this->randomMachineName();
    $payment_method_configuration_plugin_label = $this->randomMachineName();

    $payment_method_configuration_plugin_definition = array(
      'label' => $payment_method_configuration_plugin_label,
    );

    $this->paymentMethodConfigurationManager->expects($this->any())
      ->method('getDefinition')
      ->with($payment_method_configuration_plugin_id)
      ->will($this->returnValue($payment_method_configuration_plugin_definition));

    $owner = $this->getMockBuilder('\Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock();

    $payment_method_configuration = $this->getMockBuilder('\Drupal\payment\Entity\PaymentMethodConfiguration')
      ->disableOriginalConstructor()
      ->getMock();
    $payment_method_configuration->expects($this->any())
      ->method('getOwner')
      ->will($this->returnValue($owner));
    $payment_method_configuration->expects($this->any())
      ->method('getPluginId')
      ->will($this->returnValue($payment_method_configuration_plugin_id));
    $payment_method_configuration->expects($this->any())
      ->method('getPaymentStatus')
      ->will($this->returnValue($payment_method_configuration_entity_status));
    $payment_method_configuration->expects($this->any())
      ->method('label')
      ->will($this->returnValue($payment_method_configuration_entity_label));

    $this->moduleHandler->expects($this->any())
      ->method('invokeAll')
      ->will($this->returnValue(array()));

    $build = $this->listBuilder->buildRow($payment_method_configuration);
    unset($build['data']['operations']['data']['#attached']);
    $expected_build = array(
      'data' => array(
        'label' => $payment_method_configuration_entity_label,
        'plugin' => $payment_method_configuration_plugin_label,
        'owner' => array(
          'data' => array(
            '#theme' => 'username',
            '#account' => $owner,
          )
        ),
        'status' => 'Disabled',
        'operations' => array(
          'data' => array(
            '#type' => 'operations',
            '#links' => array(),
          ),
        ),
      ),
      'class' => array('payment-method-configuration-disabled'),
    );
    $this->assertSame($expected_build, $build);
  }

  /**
   * @covers ::render
   *
   * @depends testBuildHeader
   */
  public function testRender() {
    $this->entityType->expects($this->any())
      ->method('getClass')
      ->will($this->returnValue('Drupal\Core\Config\Entity\ConfigEntityBase'));

    $this->entityStorage->expects($this->once())
      ->method('loadMultiple')
      ->will($this->returnValue(array()));

    $build = $this->listBuilder->render();
    unset($build['#attached']);
    unset($build['#header']);
    $expected_build = array(
      '#type' => 'table',
      '#title' => NULL,
      '#rows' => array(),
      '#empty' => 'There is no payment method configuration yet.',
      '#attributes' => array(
        'class' => array('payment-method-configuration-list'),
      ),
    );
    $this->assertSame($expected_build, $build);
  }

  /**
   * @covers ::getDefaultOperations
   */
  public function testGetDefaultOperationsWithoutAccess() {
    $method = new \ReflectionMethod($this->listBuilder, 'getDefaultOperations');
    $method->setAccessible(TRUE);

    $payment_method_configuration = $this->getMockBuilder('\Drupal\payment\Entity\PaymentMethodConfiguration')
      ->disableOriginalConstructor()
      ->getMock();

    $operations = $method->invoke($this->listBuilder, $payment_method_configuration);
    $this->assertEmpty($operations);
  }

  /**
   * @covers ::getDefaultOperations
   */
  public function testGetDefaultOperationsWithAccess() {
    $method = new \ReflectionMethod($this->listBuilder, 'getDefaultOperations');
    $method->setAccessible(TRUE);

    $url_duplicate_form = new Url($this->randomMachineName());

    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $map = array(
      array('duplicate', NULL, FALSE, TRUE),
    );
    $payment->expects($this->any())
      ->method('access')
      ->will($this->returnValueMap($map));
    $map = array(
      array('duplicate-form', $url_duplicate_form),
    );
    $payment->expects($this->any())
      ->method('urlInfo')
      ->will($this->returnValueMap($map));

    $operations = $method->invoke($this->listBuilder, $payment);
    $expected_operations = array(
      'duplicate' => array(
        'title' => 'Duplicate',
        'weight' => 99,
        'route_name' => $url_duplicate_form->getRouteName(),
        'route_parameters' => array(),
        'options' => array(),
      ),
    );
    $this->assertSame($expected_operations, $operations);
  }

}

}

namespace {

  if (!defined('RESPONSIVE_PRIORITY_LOW')) {
    define('RESPONSIVE_PRIORITY_LOW', 'priority-low');
  }
  if (!defined('RESPONSIVE_PRIORITY_MEDIUM')) {
    define('RESPONSIVE_PRIORITY_MEDIUM', 'priority-medium');
  }
  if (!function_exists('drupal_get_path')) {
    function drupal_get_path() {
    }
  }

}
