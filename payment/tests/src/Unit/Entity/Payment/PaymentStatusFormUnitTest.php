<?php

/**
 * @file
 * Contains \Drupal\Tests\payment\Unit\Entity\Payment\PaymentStatusFormUnitTest.
 */

namespace Drupal\Tests\payment\Unit\Entity\Payment {

use Drupal\Core\Language\Language;
  use Drupal\Core\Url;
  use Drupal\payment\Entity\Payment\PaymentStatusForm;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodUpdatePaymentStatusInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

  /**
 * @coversDefaultClass \Drupal\payment\Entity\Payment\PaymentStatusForm
 *
 * @group Payment
 */
class PaymentStatusFormUnitTest extends UnitTestCase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentUser;

  /**
   * The default datetime.
   *
   * @var \Drupal\Core\Datetime\DrupalDateTime|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $defaultDateTime;

  /**
   * The class under test.
   *
   * @var \Drupal\payment\Entity\Payment\PaymentStatusForm
   */
  protected $form;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $languageManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moduleHandler;

  /**
   * The payment.
   *
   * @var \Drupal\payment\Entity\Payment|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $payment;

  /**
   * The payment status plugin manager.
   *
   * @var \Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentStatusManager;

  /**
   * The string translator.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $stringTranslation;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  public function setUp() {
    $this->currentUser = $this->getMock('\Drupal\Core\Session\AccountInterface');

    $this->defaultDateTime = $this->getMockBuilder('\Drupal\Core\Datetime\DrupalDateTime')
      ->disableOriginalConstructor()
      ->getMock();

    $this->languageManager = $this->getMock('\Drupal\Core\Language\LanguageManagerInterface');

    $this->moduleHandler = $this->getmock('\Drupal\Core\Extension\ModuleHandlerInterface');

    $this->paymentStatusManager = $this->getmock('\Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface');

    $this->urlGenerator = $this->getmock('\Drupal\Core\Routing\UrlGeneratorInterface');

    $this->stringTranslation = $this->getMock('\Drupal\Core\StringTranslation\TranslationInterface');

    $this->payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();

    $this->form = new PaymentStatusForm($this->moduleHandler, $this->currentUser, $this->urlGenerator, $this->stringTranslation, $this->paymentStatusManager, $this->defaultDateTime);
    $this->form->setEntity($this->payment);
  }

  /**
   * @covers ::create
   */
  function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    \Drupal::setContainer($container);
    $map = array(
      array('current_user', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->currentUser),
      array('language_manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->languageManager),
      array('module_handler', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->moduleHandler),
      array('plugin.manager.payment.status', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->paymentStatusManager),
      array('string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation),
      array('url_generator', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->urlGenerator),
    );
    $container->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($map));

    $language = $this->getMockBuilder('\Drupal\Core\Language\Language')
      ->disableOriginalConstructor()
      ->getMock();

    $this->languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->will($this->returnValue($language));

    $form = PaymentStatusForm::create($container);
    $this->assertInstanceOf('\Drupal\payment\Entity\Payment\PaymentStatusForm', $form);
  }

  /**
   * @covers ::form
   */
  public function testFormWithDateTimeModule() {
    $settable_payment_status_ids = array($this->randomMachineName());

    $this->paymentStatusManager->expects($this->once())
      ->method('options')
      ->with($settable_payment_status_ids);

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('datetime')
      ->will($this->returnValue(TRUE));

    $language = new Language();

    $payment_method = $this->getMock('\Drupal\Tests\payment\Unit\Entity\Payment\PaymentStatusFormUnitTestDummyPaymentMethodUpdateStatusInterface');
    $payment_method->expects($this->once())
      ->method('getSettablePaymentStatuses')
      ->with($this->currentUser)
      ->will($this->returnValue($settable_payment_status_ids));

    $this->payment->expects($this->atLeastOnce())
      ->method('getPaymentMethod')
      ->will($this->returnValue($payment_method));
    $this->payment->expects($this->any())
      ->method('language')
      ->will($this->returnValue($language));

    $form = array();
    $form_state = $this->getMock('\Drupal\Core\Form\FormStateInterface');
    $form = $this->form->form($form, $form_state);
    $this->assertInternalType('array', $form);
    $this->assertArrayHasKey('plugin_id', $form);
    $this->assertInternalType('array', $form['plugin_id']);
    $this->assertSame('select', $form['plugin_id']['#type']);
    $this->assertArrayHasKey('created', $form);
    $this->assertInternalType('array', $form['plugin_id']);
    $this->assertSame('datetime', $form['created']['#type']);
  }

  /**
   * @covers ::form
   */
  public function testFormWithoutDateTimeModuleWithoutPermission() {
    $this->paymentStatusManager->expects($this->once())
      ->method('options');

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('datetime')
      ->will($this->returnValue(FALSE));

    $this->currentUser->expects($this->once())
      ->method('hasPermission')
      ->with('administer modules')
      ->will($this->returnValue(FALSE));

    $this->urlGenerator->expects($this->never())
      ->method('generateFromRoute');

    $language = new Language();

    $this->payment->expects($this->any())
      ->method('language')
      ->will($this->returnValue($language));

    $form = array();
    $form_state = $this->getMock('\Drupal\Core\Form\FormStateInterface');
    $form = $this->form->form($form, $form_state);
    $this->assertInternalType('array', $form);
    $this->assertArrayHasKey('plugin_id', $form);
    $this->assertInternalType('array', $form['plugin_id']);
    $this->assertSame('select', $form['plugin_id']['#type']);
    $this->assertArrayHasKey('created', $form);
    $this->assertInternalType('array', $form['plugin_id']);
    $this->assertSame('value', $form['created']['#type']);
  }

  /**
   * @covers ::form
   */
  public function testFormWithoutDateTimeModuleWithPermission() {
    $this->paymentStatusManager->expects($this->once())
      ->method('options');

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('datetime')
      ->will($this->returnValue(FALSE));

    $this->currentUser->expects($this->once())
      ->method('hasPermission')
      ->with('administer modules')
      ->will($this->returnValue(TRUE));

    $this->urlGenerator->expects($this->once())
      ->method('generateFromRoute')
      ->with('system.modules_list');

    $language = new Language();

    $this->payment->expects($this->any())
      ->method('language')
      ->will($this->returnValue($language));

    $form = array();
    $form_state = $this->getMock('\Drupal\Core\Form\FormStateInterface');
    $form = $this->form->form($form, $form_state);
    $this->assertInternalType('array', $form);
    $this->assertArrayHasKey('plugin_id', $form);
    $this->assertInternalType('array', $form['plugin_id']);
    $this->assertSame('select', $form['plugin_id']['#type']);
    $this->assertArrayHasKey('created', $form);
    $this->assertInternalType('array', $form['plugin_id']);
    $this->assertSame('value', $form['created']['#type']);
    $this->assertArrayHasKey('created_message', $form);
    $this->assertInternalType('array', $form['plugin_id']);
    $this->assertSame('markup', $form['created_message']['#type']);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitForm() {
    $timestamp = $this->randomMachineName();
    $plugin_id = $this->randomMachineName();

    $payment_status = $this->getMock('\Drupal\payment\Plugin\Payment\Status\PaymentStatusInterface');
    $payment_status->expects($this->once())
      ->method('setCreated')
      ->with($timestamp);

    $this->defaultDateTime->expects($this->any())
      ->method('getTimestamp')
      ->will($this->returnValue($timestamp));

    $this->paymentStatusManager->expects($this->once())
      ->method('createInstance')
      ->with($plugin_id)
      ->will($this->returnValue($payment_status));

    $url = new Url($this->randomMachineName());

    $this->payment->expects($this->once())
      ->method('setPaymentStatus')
      ->with($payment_status);
    $this->payment->expects($this->once())
      ->method('save');
    $this->payment->expects($this->once())
      ->method('urlInfo')
      ->with('canonical')
      ->willReturn($url);

    $form = array();
    $form_state = $this->getMock('\Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->atLeastOnce())
      ->method('getValues')
      ->willReturn(array(
        'created' => $this->defaultDateTime,
        'plugin_id' => $plugin_id,
      ));
    $form_state->expects($this->once())
      ->method('setRedirectUrl')
      ->with($url);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::actions
   */
  public function testActions() {
    $form = array();
    $form_state = $this->getMock('\Drupal\Core\Form\FormStateInterface');

    $method = new \ReflectionMethod($this->form, 'actions');
    $method->setAccessible(TRUE);
    $method->invokeArgs($this->form, array($form, $form_state));
  }

}

/**
 * Extends two interfaces, because we can only mock one.
 */
interface PaymentStatusFormUnitTestDummyPaymentMethodUpdateStatusInterface extends PaymentMethodUpdatePaymentStatusInterface, PaymentMethodInterface {
}

}

namespace {

if (!function_exists('drupal_get_user_timezone')) {
  function drupal_get_user_timezone() {
  }
}

}
