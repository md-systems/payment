<?php

/**
 * @file
 * Contains \Drupal\Tests\payment\Unit\Controller\PaymentMethodUnitTest.
 */

namespace Drupal\Tests\payment\Unit\Controller {
  use Drupal\Core\Access\AccessResult;
  use Drupal\payment\Controller\PaymentMethod;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\payment\Controller\PaymentMethod
 *
 * @group Payment
 */
class PaymentMethodUnitTest extends UnitTestCase {

  /**
   * The class under test.
   *
   * @var \Drupal\payment\Controller\PaymentMethod
   */
  protected $controller;

  /**
   * The current user used for testing.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentUser;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityFormBuilder;

  /**
   * The entity manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityManager;

  /**
   * The payment method configuration plugin manager used for testing.
   *
   * @var \Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentMethodConfigurationManager;

  /**
   * The payment method plugin manager used for testing.
   *
   * @var \Drupal\payment\Plugin\Payment\Method\PaymentMethodManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentMethodManager;

  /**
   * The string translator.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $stringTranslation;

  /**
   * The URL generator used for testing.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  protected function setUp() {
    $this->currentUser = $this->getMock('\Drupal\Core\Session\AccountInterface');

    $this->entityFormBuilder = $this->getMock('\Drupal\Core\Entity\EntityFormBuilderInterface');

    $this->entityManager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');

    $this->paymentMethodConfigurationManager = $this->getMock('\Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationManagerInterface');

    $this->paymentMethodManager = $this->getMock('\Drupal\payment\Plugin\Payment\Method\PaymentMethodManagerInterface');

    $this->stringTranslation = $this->getMock('\Drupal\Core\StringTranslation\TranslationInterface');

    $this->urlGenerator = $this->getMock('\Drupal\Core\Routing\UrlGeneratorInterface');
    $this->urlGenerator->expects($this->any())
      ->method('generateFromRoute')
      ->will($this->returnValue('http://example.com'));

    $this->controller = new PaymentMethod($this->stringTranslation, $this->entityManager, $this->paymentMethodManager, $this->paymentMethodConfigurationManager, $this->entityFormBuilder, $this->urlGenerator, $this->currentUser);
  }

  /**
   * @covers ::create
   */
  function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('current_user', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->currentUser),
      array('entity.form_builder', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->entityFormBuilder),
      array('entity.manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->entityManager),
      array('plugin.manager.payment.method', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->paymentMethodManager),
      array('plugin.manager.payment.method_configuration', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->paymentMethodConfigurationManager),
      array('string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation),
      array('url_generator', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->urlGenerator),
    );
    $container->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($map));

    $form = PaymentMethod::create($container);
    $this->assertInstanceOf('\Drupal\payment\Controller\PaymentMethod', $form);
  }

  /**
   * @covers ::enable
   */
  public function testEnable() {
    $payment_method = $this->getMock('\Drupal\payment\Entity\PaymentMethodConfigurationInterface');
    $payment_method->expects($this->once())
      ->method('enable');
    $payment_method->expects($this->once())
      ->method('save');
    $this->controller->enable($payment_method);
  }

  /**
   * @covers ::disable
   */
  public function testDisable() {
    $payment_method = $this->getMock('\Drupal\payment\Entity\PaymentMethodConfigurationInterface');
    $payment_method->expects($this->once())
      ->method('disable');
    $payment_method->expects($this->once())
      ->method('save');
    $this->controller->disable($payment_method);
  }

  /**
   * @covers ::select
   */
  public function testSelect() {
    $definitions = array(
      'payment_unavailable' => array(),
      'foo' => array(
        'description' => $this->randomMachineName(),
        'label' => $this->randomMachineName(),
      ),
      'bar' => array(
        'description' => $this->randomMachineName(),
        'label' => $this->randomMachineName(),
      ),
    );
    $this->paymentMethodConfigurationManager->expects($this->once())
      ->method('getDefinitions')
      ->will($this->returnValue($definitions));

    $access_controller = $this->getMock('\Drupal\Core\Entity\EntityAccessControlHandlerInterface');
    $access_controller->expects($this->any())
      ->method('createAccess')
      ->will($this->returnValue(TRUE));

    $this->entityManager->expects($this->once())
      ->method('getAccessControlHandler')
      ->with('payment_method_configuration')
      ->will($this->returnValue($access_controller));

    $this->controller->select();
  }

  /**
   * @covers ::selectAccess
   */
  public function testSelectAccess() {
    $definitions = array(
      'payment_unavailable' => array(),
      'foo' => array(
        'description' => $this->randomMachineName(),
        'label' => $this->randomMachineName(),
      ),
      'bar' => array(
        'description' => $this->randomMachineName(),
        'label' => $this->randomMachineName(),
      ),
    );
    $this->paymentMethodConfigurationManager->expects($this->exactly(2))
      ->method('getDefinitions')
      ->will($this->returnValue($definitions));

    $access_controller = $this->getMock('\Drupal\Core\Entity\EntityAccessControlHandlerInterface');
    $access_controller->expects($this->at(0))
      ->method('createAccess')
      ->with('foo', $this->currentUser, array(), TRUE)
      ->will($this->returnValue(AccessResult::allowed()));
    $access_controller->expects($this->at(1))
      ->method('createAccess')
      ->with('foo', $this->currentUser, array(), TRUE)
      ->will($this->returnValue(AccessResult::forbidden()));
    $access_controller->expects($this->at(2))
      ->method('createAccess')
      ->with('bar', $this->currentUser, array(), TRUE)
      ->will($this->returnValue(AccessResult::forbidden()));

    $this->entityManager->expects($this->exactly(2))
      ->method('getAccessControlHandler')
      ->with('payment_method_configuration')
      ->will($this->returnValue($access_controller));

    $request = new Request();

    $this->assertTrue($this->controller->selectAccess($request)->isAllowed());
    $this->assertFalse($this->controller->selectAccess($request)->isAllowed());
  }

  /**
   * @covers ::add
   */
  public function testAdd() {
    $plugin_id = $this->randomMachineName();

    $payment_method_configuration = $this->getMock('\Drupal\payment\Entity\PaymentMethodConfigurationInterface');

    $storage_controller = $this->getMock('\Drupal\Core\Entity\EntityStorageInterface');
    $storage_controller->expects($this->once())
      ->method('create')
      ->will($this->returnValue($payment_method_configuration));

    $form = $this->getMock('\Drupal\Core\Entity\EntityFormInterface');

    $this->entityManager->expects($this->once())
      ->method('getStorage')
      ->with('payment_method_configuration')
      ->will($this->returnValue($storage_controller));

    $this->entityFormBuilder->expects($this->once())
      ->method('getForm')
      ->with($payment_method_configuration, 'default')
      ->will($this->returnValue($form));

    $this->controller->add($plugin_id);
  }

  /**
   * @covers ::addAccess
   */
  public function testAddAccess() {
    $plugin_id = $this->randomMachineName();
    $request = new Request();
    $request->attributes->set('plugin_id', $plugin_id);

    $access_controller = $this->getMock('\Drupal\Core\Entity\EntityAccessControlHandlerInterface');
    $access_controller->expects($this->at(0))
      ->method('createAccess')
      ->with($plugin_id, $this->currentUser, array(), TRUE)
      ->will($this->returnValue(AccessResult::allowed()));
    $access_controller->expects($this->at(1))
      ->method('createAccess')
      ->with($plugin_id, $this->currentUser, array(), TRUE)
      ->will($this->returnValue(AccessResult::forbidden()));

    $this->entityManager->expects($this->exactly(2))
      ->method('getAccessControlHandler')
      ->with('payment_method_configuration')
      ->will($this->returnValue($access_controller));

    $this->assertTrue($this->controller->addAccess($request)->isAllowed());
    $this->assertFalse($this->controller->addAccess($request)->isAllowed());
  }

  /**
   * @covers ::duplicate
   */
  public function testDuplicate() {
    $payment_method = $this->getMock('\Drupal\payment\Entity\PaymentMethodConfigurationInterface');
    $payment_method->expects($this->once())
      ->method('createDuplicate')
      ->will($this->returnSelf());
    $payment_method->expects($this->once())
      ->method('setLabel')
      ->will($this->returnSelf());

    $form = $this->getMock('\Drupal\Core\Entity\EntityFormInterface');

    $this->entityFormBuilder->expects($this->once())
      ->method('getForm')
      ->with($payment_method, 'default')
      ->will($this->returnValue($form));

    $this->controller->duplicate($payment_method);
  }

  /**
   * @covers ::listPlugins
   */
  public function testListPlugins() {
    $plugin_id_a = $this->randomMachineName();
    $plugin_id_b = $this->randomMachineName();
    $definitions = array(
      $plugin_id_a => array(
        'active' => TRUE,
        'class' => $this->getMockClass('\Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface'),
        'label' => $this->randomMachineName(),
      ),
      $plugin_id_b => array(
        'active' => FALSE,
        'class' => $this->getMockClass('\Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface'),
        'label' => $this->randomMachineName(),
      ),
    );

    $this->paymentMethodManager->expects($this->once())
      ->method('getDefinitions')
      ->will($this->returnValue($definitions));

    $build = $this->controller->listPlugins();
    $this->assertInternalType('array', $build);
  }

  /**
   * @covers ::addTitle
   */
  public function testAddTitle() {
    $label = $this->randomMachineName();
    $plugin_id = $this->randomMachineName();
    $string = 'Add %label payment method configuration';

    $this->paymentMethodConfigurationManager->expects($this->once())
      ->method('getDefinition')
      ->with($plugin_id)
      ->will($this->returnValue(array(
        'label' => $label,
      )));

    $this->stringTranslation->expects($this->any())
      ->method('translate')
      ->with($string, array(
        '%label' => $label,
      ))
      ->will($this->returnArgument(0));

    $this->assertSame($string, $this->controller->addTitle($plugin_id));
  }

  /**
   * @covers ::editTitle
   */
  public function testEditTitle() {
    $label = $this->randomMachineName();
    $string = 'Edit %label';

    $payment_method_configuration = $this->getMockBuilder('\Drupal\payment\Entity\PaymentMethodConfiguration')
      ->disableOriginalConstructor()
      ->getMock();
    $payment_method_configuration->expects($this->once())
      ->method('label')
      ->will($this->returnValue($label));

    $this->stringTranslation->expects($this->any())
      ->method('translate')
      ->with($string, array(
        '%label' => $label,
      ))
      ->will($this->returnArgument(0));

    $this->assertSame($string, $this->controller->editTitle($payment_method_configuration));
  }

  /**
   * @covers ::duplicateTitle
   */
  public function testDuplicateTitle() {
    $label = $this->randomMachineName();
    $string = 'Duplicate %label';

    $payment_method_configuration = $this->getMockBuilder('\Drupal\payment\Entity\PaymentMethodConfiguration')
      ->disableOriginalConstructor()
      ->getMock();
    $payment_method_configuration->expects($this->once())
      ->method('label')
      ->will($this->returnValue($label));

    $this->stringTranslation->expects($this->any())
      ->method('translate')
      ->with($string, array(
        '%label' => $label,
      ))
      ->will($this->returnArgument(0));

    $this->assertSame($string, $this->controller->duplicateTitle($payment_method_configuration));
  }

}

}

namespace {

  if (!function_exists('drupal_get_path')) {
    function drupal_get_path() {
    }
  }

}

