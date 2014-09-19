<?php

/**
 * @file
 * Contains \Drupal\Tests\payment_reference\Unit\Controller\ResumeContextUnitTest.
 */

namespace Drupal\Tests\payment_reference\Unit\Controller {

use Drupal\Core\Access\AccessInterface;
use Drupal\payment_reference\Controller\ResumeContext;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\payment_reference\Controller\ResumeContext
 *
 * @group Payment Reference Field
 */
class ResumeContextUnitTest extends UnitTestCase {

  /**
   * The controller under test.
   *
   * @var \Drupal\payment_reference\Controller\ResumeContext
   */
  protected $controller;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentUser;

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
    $this->currentUser = $this->getMock('\Drupal\Core\Session\AccountInterface');

    $this->stringTranslation = $this->getStringTranslationStub();

    $this->controller = new ResumeContext($this->currentUser, $this->stringTranslation);
  }

  /**
   * @covers ::create
   */
  function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('current_user', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->currentUser),
      array('string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation),
    );
    $container->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($map));

    $form = ResumeContext::create($container);
    $this->assertInstanceOf('\Drupal\payment_reference\Controller\ResumeContext', $form);
  }

  /**
   * @covers ::execute
   */
  public function testExecute() {
    $payment_status = $this->getMock('\Drupal\payment\Plugin\Payment\Status\PaymentStatusInterface');

    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $payment->expects($this->once())
      ->method('access')
      ->with('view')
      ->will($this->returnValue(TRUE));
    $payment->expects($this->once())
      ->method('getPaymentStatus')
      ->will($this->returnValue($payment_status));

    $build = $this->controller->execute($payment);
    $this->assertInternalType('array', $build);
  }

  /**
   * @covers ::title
   */
  public function testTitle() {
    $label = $this->randomMachineName();

    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $payment->expects($this->once())
      ->method('label')
      ->willReturn($label);

    $this->assertSame($label, $this->controller->title($payment));
  }

  /**
   * @covers ::access
   *
   * @dataProvider providerTestAccess
   */
  public function testAccess($expected, $payment_type_access) {
    $payment_type = $this->getMock('\Drupal\payment\Plugin\Payment\Type\PaymentTypeInterface');
    $payment_type->expects($this->once())
      ->method('resumeContextAccess')
      ->willReturn($payment_type_access);

    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $payment->expects($this->once())
      ->method('getPaymentType')
      ->willReturn($payment_type);

    $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
      ->disableOriginalConstructor()
      ->getMock();

    $this->assertSame($expected, $this->controller->access($request, $payment)->isAllowed());
  }

  /**
   * Provides data to testResumeContextAccess().
   */
  public function providerTestAccess() {
    return array(
      array(TRUE, TRUE),
      array(FALSE, FALSE),
    );
  }

}

}

namespace {

if (!function_exists('drupal_get_path')) {
  function drupal_get_path() {
  }
}

}
