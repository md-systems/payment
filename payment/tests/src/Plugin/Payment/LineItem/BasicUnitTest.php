<?php

/**
 * @file Contains \Drupal\payment\Tests\Plugin\Payment\LineItem\BasicUnitTest.
 */

namespace Drupal\payment\Tests\Plugin\Payment\LineItem;

use Drupal\payment\Plugin\Payment\LineItem\Basic;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\payment\Plugin\Payment\LineItem\Basic
 */
class BasicUnitTest extends UnitTestCase {

  /**
   * The database connection used for testing.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $database;

  /**
   * The form builder used for testing.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $formBuilder;

  /**
   * The line item under test.
   *
   * @var \Drupal\payment\Plugin\Payment\LineItem\Basic
   */
  protected $lineItem;

  /**
   * The math service used for testing.
   *
   * @var \Drupal\currency\MathInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $math;

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
      'name' => '\Drupal\payment\Plugin\Payment\LineItem\Basic unit test',
      'group' => 'Payment',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->database = $this->getMockBuilder('\Drupal\Core\Database\Connection')
      ->disableOriginalConstructor()
      ->getMock();

    $this->formBuilder = $this->getMock('\Drupal\Core\Form\FormBuilderInterface');

    $this->math = $this->getMock('\Drupal\currency\MathInterface');

    $this->stringTranslation = $this->getMock('\Drupal\Core\StringTranslation\TranslationInterface');

    $configuration = array();
    $plugin_id = $this->randomName();
    $plugin_definition = array();
    $this->lineItem = new Basic($configuration, $plugin_id, $plugin_definition, $this->math, $this->stringTranslation, $this->database, $this->formBuilder);
  }

  /**
   * @covers ::create
   */
  function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('currency.math', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->math),
      array('database', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->database),
      array('form_builder', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->formBuilder),
      array('string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation),
    );
    $container->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($map));

    $configuration = array();
    $plugin_definition = array();
    $plugin_id = $this->randomName();
    $form = Basic::create($container, $configuration, $plugin_id, $plugin_definition);
    $this->assertInstanceOf('\Drupal\payment\Plugin\Payment\LineItem\Basic', $form);
  }

  /**
   * @covers ::setAmount
   * @covers ::getAmount
   */
  public function testGetAmount() {
    $amount = mt_rand();
    $this->assertSame(spl_object_hash($this->lineItem), spl_object_hash($this->lineItem->setAmount($amount)));
    $this->assertSame($amount, $this->lineItem->getAmount());
  }

  /**
   * @covers ::setCurrencyCode
   * @covers ::getCurrencyCode
   */
  public function testGetCurrencyCode() {
    $currency_code = $this->randomName();
    $this->assertSame(spl_object_hash($this->lineItem), spl_object_hash($this->lineItem->setCurrencyCode($currency_code)));
    $this->assertSame($currency_code, $this->lineItem->getCurrencyCode());
  }

  /**
   * @covers ::setDescription
   * @covers ::getDescription
   */
  public function testGetDescription() {
    $description = $this->randomName();
    $this->assertSame(spl_object_hash($this->lineItem), spl_object_hash($this->lineItem->setDescription($description)));
    $this->assertSame($description, $this->lineItem->getDescription());
  }

  /**
   * @covers ::formElements
   */
  public function testFormElements() {
    $form = array();
    $form_state = array();
    $form_elements = $this->lineItem->formElements($form, $form_state);
    $this->assertInternalType('array', $form_elements);
    $this->assertFileExists($form_elements['#attached']['css'][0]);
  }
}