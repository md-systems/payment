<?php

/**
 * @file
 * Contains \Drupal\Tests\payment\Unit\Entity\PaymentStatus\PaymentStatusDeleteFormUnitTest.
 */

namespace Drupal\Tests\payment\Unit\Entity\PaymentStatus {

use Drupal\payment\Entity\PaymentStatus\PaymentStatusDeleteForm;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\payment\Entity\PaymentStatus\PaymentStatusDeleteForm
 *
 * @group Payment
 */
class PaymentStatusDeleteFormUnitTest extends UnitTestCase {

  /**
   * The payment.
   *
   * @var \Drupal\payment\Entity\PaymentStatusInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $payment;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $stringTranslation;

  /**
   * The form under test.
   *
   * @var \Drupal\payment\Entity\PaymentStatus\PaymentStatusDeleteForm
   */
  protected $form;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  public function setUp() {
    $this->payment = $this->getMockBuilder('\Drupal\payment\Entity\PaymentStatus')
      ->disableOriginalConstructor()
      ->getMock();

    $this->stringTranslation = $this->getMock('\Drupal\Core\StringTranslation\TranslationInterface');
    $this->stringTranslation->expects($this->any())
      ->method('translate')
      ->will($this->returnArgument(0));

    $this->form = new PaymentStatusDeleteForm($this->stringTranslation);
    $this->form->setEntity($this->payment);
  }

  /**
   * @covers ::create
   */
  function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $container->expects($this->once())
      ->method('get')
      ->with('string_translation')
      ->will($this->returnValue($this->stringTranslation));

    $form = PaymentStatusDeleteForm::create($container);
    $this->assertInstanceOf('\Drupal\payment\Entity\PaymentStatus\PaymentStatusDeleteForm', $form);
  }

  /**
   * @covers ::getQuestion
   */
  function testGetQuestion() {
    $label = $this->randomMachineName();
    $string = 'Do you really want to delete %label?';

    $this->payment->expects($this->once())
      ->method('label')
      ->will($this->returnValue($label));

    $this->stringTranslation->expects($this->once())
      ->method('translate')
      ->with($string, array(
        '%label' => $label,
      ));

    $this->assertSame($string, $this->form->getQuestion());
  }

  /**
   * @covers ::getConfirmText
   */
  function testGetConfirmText() {
    $string = 'Delete';

    $this->stringTranslation->expects($this->once())
      ->method('translate')
      ->with($string);

    $this->assertSame($string, $this->form->getConfirmText());
  }

  /**
   * @covers ::getCancelUrl
   */
  function testGetCancelUrl() {
    $url = $this->form->getCancelUrl();
    $this->assertInstanceOf('\Drupal\Core\Url', $url);
    $this->assertSame('payment.payment_status.list', $url->getRouteName());
  }

  /**
   * @covers ::submitForm
   */
  function testSubmitForm() {
    $this->payment->expects($this->once())
      ->method('delete');

    $form = array();
    $form_state = $this->getMock('\Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('setRedirect')
      ->with('payment.payment_status.list');

    $this->form->submitForm($form, $form_state);
  }

}

}

namespace {

if (!function_exists('drupal_set_message')) {
  function drupal_set_message() {}
}

}
