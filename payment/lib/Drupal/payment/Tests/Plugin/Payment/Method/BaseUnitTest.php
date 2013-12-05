<?php

/**
 * @file
 * Contains \Drupal\payment\Tests\Plugin\Payment\Method\BaseUnitTest.
 */

namespace Drupal\payment\Tests\Plugin\Payment\Method;

use Drupal\Core\Access\AccessInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests \Drupal\payment\Plugin\Payment\Method\Base.
 */
class BaseUnitTest extends UnitTestCase {

  /**
   * The module handler used for testing.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moduleHandler;

  /**
   * The token API used for testing.
   *
   * @var \Drupal\Core\Utility\Token|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $token;

  /**
   * The payment method plugin under test.
   *
   * @var \Drupal\payment\Plugin\Payment\Method\Base|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $plugin;

  /**
   * The definition of the payment method plugin under test.
   *
   * @var array
   */
  protected $pluginDefinition = array();

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'description' => '',
      'name' => '\Drupal\payment\Plugin\Payment\Method\Base unit test',
      'group' => 'Payment',
    );
  }

  /**
   * {@inheritdoc
   */
  public function setUp() {
    parent::setUp();

    $this->moduleHandler = $this->getMock('\Drupal\Core\Extension\ModuleHandlerInterface');

    $this->token = $this->getMockBuilder('\Drupal\Core\Utility\Token')
      ->disableOriginalConstructor()
      ->getMock();

    $this->pluginDefinition = array(
      'active' => TRUE,
      'message_text' => $this->randomName(),
      'message_text_format' => $this->randomName(),
    );

    $this->plugin = $this->getMockBuilder('\Drupal\payment\Plugin\Payment\Method\Base')
      ->setConstructorArgs(array(array(), '', $this->pluginDefinition, $this->moduleHandler, $this->token))
      ->setMethods(array('currencies', 'checkMarkup', 't'))
      ->getMock();
    $this->plugin->expects($this->any())
      ->method('checkMarkup')
      ->will($this->returnArgument(0));
    $this->plugin->expects($this->any())
      ->method('t')
      ->will($this->returnArgument(0));
  }

  /**
   * Tests defaultConfiguration().
   */
  public function testDefaultConfiguration() {
    $this->assertInternalType('array', $this->plugin->defaultConfiguration());
  }

  /**
   * Tests setConfiguration() and getConfiguration().
   */
  public function testGetConfiguration() {
    $configuration = array(
      $this->randomName() => mt_rand(),
    );
    $this->assertNull($this->plugin->setConfiguration($configuration));
    $this->assertSame($configuration, $this->plugin->getConfiguration());
  }

  /**
   * Tests getMessageText().
   */
  public function testGetMessageText() {
    $this->assertSame($this->pluginDefinition['message_text'], $this->plugin->getMessageText());
  }

  /**
   * Tests getMessageTextFormat().
   */
  public function testGetMessageTextFormat() {
    $this->assertSame($this->pluginDefinition['message_text_format'], $this->plugin->getMessageTextFormat());
  }

  /**
   * Tests formElements().
   */
  public function testFormElements() {
    $form = array();
    $form_state = array();
    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $elements = $this->plugin->formElements($form, $form_state, $payment);
    $this->assertInternalType('array', $elements);
    $this->assertArrayHasKey('message', $elements);
    $this->assertInternalType('array', $elements['message']);
  }

  /**
   * Tests executePayment().
   */
  public function testExecutePayment() {
    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $this->moduleHandler->expects($this->once())
      ->method('invokeAll')
      ->with('payment_pre_execute');
    $this->plugin->executePayment($payment);
  }

  /**
   * Tests executePaymentAccess().
   */
  public function testExecutePaymentAccess() {
    $currency_code = 'EUR';
    $valid_amount = 12.34;
    $minimum_amount = 10;
    $maximum_amount = 20;

    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $this->plugin->expects($this->any())
      ->method('currencies')
      ->will($this->returnValue(array(
        $currency_code => array(
          'minimum' => $minimum_amount,
          'maximum' => $maximum_amount,
        ),
      )));

    // Test granted access.
    // @todo Check how to test denial of access reliably.
    $payment->expects($this->exactly(2))
      ->method('getCurrencyCode')
      ->will($this->returnValue($currency_code));
    $payment->expects($this->exactly(2))
      ->method('getAmount')
      ->will($this->returnValue($valid_amount));
    $this->moduleHandler->expects($this->at(0))
      ->method('invokeAll')
      ->will($this->returnValue(array(AccessInterface::ALLOW, AccessInterface::DENY)));
    $this->moduleHandler->expects($this->at(1))
      ->method('invokeAll')
      ->will($this->returnValue(array()));
    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $this->assertTrue($this->plugin->executePaymentAccess($payment, $account));
    $this->assertTrue($this->plugin->executePaymentAccess($payment, $account));
  }
}
