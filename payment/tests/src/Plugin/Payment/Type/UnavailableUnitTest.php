<?php

/**
 * @file
 * Contains \Drupal\payment\Tests\Plugin\Payment\Type\UnavailableUnitTest.
 */

namespace Drupal\payment\Tests\Plugin\Payment\Type;

use Drupal\payment\Plugin\Payment\Type\Unavailable;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\payment\Plugin\Payment\Type\Unavailable
 */
class UnavailableUnitTest extends UnitTestCase {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $eventDispatcher;

  /**
   * The module handler used for testing.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moduleHandler;

  /**
   * The payment type under test.
   *
   * @var \Drupal\payment\Plugin\Payment\Type\Unavailable|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentType;

  /**
   * The string translator.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'description' => '',
      'name' => '\Drupal\payment\Plugin\Payment\Type\Unavailable unit test',
      'group' => 'Payment',
    );
  }

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  public function setUp() {
    $this->eventDispatcher = $this->getMock('\Symfony\Component\EventDispatcher\EventDispatcherInterface');

    $this->moduleHandler = $this->getMock('\Drupal\Core\Extension\ModuleHandlerInterface');

    $this->stringTranslation = $this->getMock('\Drupal\Core\StringTranslation\TranslationInterface');
    $this->stringTranslation->expects($this->any())
      ->method('translate')
      ->will($this->returnArgument(0));

    $configuration = array();
    $plugin_id = $this->randomName();
    $plugin_definition = array();
    $this->paymentType = new Unavailable($configuration, $plugin_id, $plugin_definition, $this->moduleHandler, $this->eventDispatcher, $this->stringTranslation);
  }

  /**
   * @covers ::create
   */
  function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('event_dispatcher', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->eventDispatcher),
      array('module_handler', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->moduleHandler),
      array('string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation),
    );
    $container->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($map));

    $configuration = array();
    $plugin_definition = array();
    $plugin_id = $this->randomName();
    $form = Unavailable::create($container, $configuration, $plugin_id, $plugin_definition);
    $this->assertInstanceOf('\Drupal\payment\Plugin\Payment\Type\Unavailable', $form);
  }

  /**
   * @covers ::doResumeContext
   * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function testDoResumeContext() {
    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $this->paymentType->setPayment($payment);

    $this->paymentType->resumeContext();
  }

  /**
   * @covers ::paymentDescription
   */
  public function testPaymentDescription() {
    $this->assertInternalType('string', $this->paymentType->paymentDescription());
  }
}
