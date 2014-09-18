<?php

/**
 * @file
 * Contains \Drupal\Tests\payment_form\Unit\Entity\Payment\PaymentFormUnitTest.
 */

namespace Drupal\Tests\payment_form\Unit\Entity\Payment;

use Drupal\Core\Form\FormState;
use Drupal\payment_form\Entity\Payment\PaymentForm;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\payment_form\Entity\Payment\PaymentForm
 *
 * @group Payment Form Field
 */
class PaymentFormUnitTest extends UnitTestCase {

  /**
   * The config factory used for testing.
   *
   * @var \Drupal\Core\Config\ConfigFactory|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $configFactory;

  /**
   * The configuration the config factory returns.
   *
   * @see self::__construct
   *
   * @var array
   */
  protected $configFactoryConfiguration = array();

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The form under test.
   *
   * @var \Drupal\payment_form\Entity\Payment\PaymentForm
   */
  protected $form;

  /**
   * The form display.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $formDisplay;

  /**
   * A payment entity used for testing.
   *
   * @var \Drupal\payment\Entity\Payment|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $payment;

  /**
   * The payment method selector used for testing.
   *
   * @var \Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentMethodSelector;

  /**
   * The payment method selector manager used for testing.
   *
   * @var \Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentMethodSelectorManager;

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
  protected function setUp() {
    $this->entityManager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');

    $this->formDisplay = $this->getMock('\Drupal\Core\Entity\Display\EntityFormDisplayInterface');

    $this->payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();

    $this->paymentMethodSelector = $this->getMock('\Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorInterface');

    $this->paymentMethodSelectorManager = $this->getMock('\Drupal\payment\Plugin\Payment\MethodSelector\PaymentMethodSelectorManagerInterface');

    $this->stringTranslation = $this->getStringTranslationStub();

    $this->configFactoryConfiguration = array(
      'payment_form.payment_type' => array(
        'limit_allowed_payment_methods' => TRUE,
        'allowed_payment_method_ids' => array($this->randomMachineName()),
        'payment_method_selector_id' => $this->randomMachineName(),
      ),
    );

    $this->configFactory = $this->getConfigFactoryStub($this->configFactoryConfiguration);

    $this->form = new PaymentForm($this->entityManager, $this->stringTranslation, $this->paymentMethodSelectorManager);
    $this->form->setConfigFactory($this->configFactory);
    $this->form->setEntity($this->payment);
  }

  /**
   * @covers ::create
   */
  function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('entity.manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->entityManager),
      array('plugin.manager.payment.method_selector', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->paymentMethodSelectorManager),
      array('string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation),
    );
    $container->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($map));

    $form = PaymentForm::create($container);
    $this->assertInstanceOf('\Drupal\payment_form\Entity\Payment\PaymentForm', $form);
  }

  /**
   * @covers ::form
   */
  public function testForm() {
    $payment_method_selector_build = array(
      '#type' => $this->randomMachineName(),
    );
    $this->paymentMethodSelector->expects($this->atLeastOnce())
      ->method('buildConfigurationForm')
      ->will($this->returnValue($payment_method_selector_build));
    $this->paymentMethodSelector->expects($this->once())
      ->method('setAllowedPaymentMethods')
      ->with($this->configFactoryConfiguration['payment_form.payment_type']['allowed_payment_method_ids']);

    $this->paymentMethodSelectorManager->expects($this->once())
      ->method('createInstance')
      ->with($this->configFactoryConfiguration['payment_form.payment_type']['payment_method_selector_id'])
      ->will($this->returnValue($this->paymentMethodSelector));

    $payment_type = $this->getMock('\Drupal\payment\Plugin\Payment\Type\PaymentTypeInterface');
    $this->payment->expects($this->any())
      ->method('getPaymentType')
      ->will($this->returnValue($payment_type));
    $entity_type = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');
    $this->payment->expects($this->any())
      ->method('entityInfo')
      ->will($this->returnValue($entity_type));

    $form = array(
      'langcode' => array(),
    );
    // @todo Mock FormStateInterface once ContentEntityForm no longer uses
    //   ArrayAccess.
    $form_state = new FormState();
    $this->form->setFormDisplay($this->formDisplay, $form_state);
    $build = $this->form->form($form, $form_state);
    // Build the form a second time to make sure the payment method selector is
    // only instantiated once.
    $this->form->form($form, $form_state);
    $this->assertInternalType('array', $build);
    $this->assertArrayHasKey('line_items', $build);
    $this->assertSame($this->payment, $build['line_items']['#payment']);
    $this->assertArrayHasKey('payment_method', $build);
    $this->assertSame($payment_method_selector_build, $build['payment_method']);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateForm() {
    $form = array(
      'payment_method' => array(
        '#type' => $this->randomMachineName(),
      ),
    );
    $form_state = $this->getMock('\Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->atLeastOnce())
      ->method('get')
      ->with('payment_method_selector')
      ->willReturn($this->paymentMethodSelector);

    $this->paymentMethodSelector->expects($this->once())
      ->method('validateConfigurationForm')
      ->with($form['payment_method'], $form_state);

    $this->form->validateForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitForm() {
    $form = array(
      'payment_method' => array(
        '#type' => $this->randomMachineName(),
      ),
    );
    $form_state = $this->getMock('\Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->atLeastOnce())
      ->method('get')
      ->with('payment_method_selector')
      ->willReturn($this->paymentMethodSelector);

    $payment_method = $this->getMock('\Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface');

    $this->paymentMethodSelector->expects($this->once())
      ->method('getPaymentMethod')
      ->will($this->returnValue($payment_method));
    $this->paymentMethodSelector->expects($this->once())
      ->method('submitConfigurationForm')
      ->with($form['payment_method'], $form_state);

    $this->payment->expects($this->once())
      ->method('setPaymentMethod')
      ->with($payment_method);
    $this->payment->expects($this->once())
      ->method('save');
    $this->payment->expects($this->once())
      ->method('execute');

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
