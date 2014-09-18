<?php

/**
 * @file
 * Contains \Drupal\Tests\payment\Unit\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationFormUnitTest.
 */

namespace Drupal\Tests\payment\Unit\Entity\PaymentMethodConfiguration {

  use Drupal\Core\Form\FormState;
  use Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationForm;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationForm
 *
 * @group Payment
 */
class PaymentMethodConfigurationFormUnitTest extends UnitTestCase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentUser;

  /**
   * The form under test.
   *
   * @var \Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationForm
   */
  protected $form;

  /**
   * The payment method configuration.
   *
   * @var \Drupal\payment\Entity\PaymentMethodConfiguration|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentMethodConfiguration;

  /**
   * The payment method configuration manager.
   *
   * @var \Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentMethodConfigurationManager;

  /**
   * The payment method configuration storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentMethodConfigurationStorage;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $stringTranslation;

  /**
   * The user storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $userStorage;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  public function setUp() {
    $this->currentUser = $this->getMock('\Drupal\Core\Session\AccountInterface');

    $this->userStorage = $this->getMock('\Drupal\Core\Entity\EntityStorageInterface');

    $this->paymentMethodConfigurationManager = $this->getMock('\Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationManagerInterface');

    $this->paymentMethodConfigurationStorage = $this->getMock('\Drupal\Core\Entity\EntityStorageInterface');

    $this->paymentMethodConfiguration = $this->getMockBuilder('\Drupal\payment\Entity\PaymentMethodConfiguration')
      ->disableOriginalConstructor()
      ->getMock();

    $this->stringTranslation = $this->getMock('\Drupal\Core\StringTranslation\TranslationInterface');
    $this->stringTranslation->expects($this->any())
      ->method('translate')
      ->will($this->returnArgument(0));

    $this->form = new PaymentMethodConfigurationForm($this->stringTranslation, $this->currentUser, $this->userStorage, $this->paymentMethodConfigurationStorage, $this->paymentMethodConfigurationManager);
    $this->form->setEntity($this->paymentMethodConfiguration);
  }

  /**
   * @covers ::create
   */
  function testCreate() {
    $entity_manager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');
    $map = array(
      array('payment_method_configuration', $this->paymentMethodConfigurationStorage),
      array('user', $this->userStorage),
    );
    $entity_manager->expects($this->exactly(2))
      ->method('getStorage')
      ->will($this->returnValueMap($map));

    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('current_user', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->currentUser),
      array('entity.manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $entity_manager),
      array('plugin.manager.payment.method_configuration', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->paymentMethodConfigurationManager),
      array('string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation),
    );
    $container->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($map));

    $form = PaymentMethodConfigurationForm::create($container);
    $this->assertInstanceOf('\Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationForm', $form);
  }

  /**
   * @covers ::form
   */
  public function testForm() {
    $owner_label = $this->randomMachineName();
    $payment_method_configuration_entity_id = $this->randomMachineName();
    $payment_method_configuration_entity_is_new = FALSE;
    $payment_method_configuration_entity_label = $this->randomMachineName();
    $payment_method_configuration_entity_status = TRUE;
    $payment_method_configuration_plugin_form = array(
      '#type' => $this->randomMachineName(),
    );
    $payment_method_configuration_plugin_id = $this->randomMachineName();
    $payment_method_configuration_plugin_configuration = array(
      'foo' => $this->randomMachineName(),
    );
    $payment_method_configuration_plugin_label = $this->randomMachineName();
    $payment_method_configuration_plugin_definition = array(
      'label' => $payment_method_configuration_plugin_label,
    );

    $owner = $this->getMockBuilder('\Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock();
    $owner->expects($this->any())
      ->method('label')
      ->will($this->returnValue($owner_label));

    $payment_method_configuration_plugin = $this->getMock('\Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationInterface');

    $form = array(
      'plugin_form' => array(),
    );
    $form_state = new FormState();

    $payment_method_configuration_plugin->expects($this->atLeastOnce())
      ->method('buildConfigurationForm')
      ->with(array(), $form_state)
      ->will($this->returnValue($payment_method_configuration_plugin_form));

    $this->paymentMethodConfigurationManager->expects($this->atLeastOnce())
      ->method('getDefinition')
      ->will($this->returnValue($payment_method_configuration_plugin_definition));

    $language = $this->getMockBuilder('\Drupal\Core\Language\Language')
      ->disableOriginalConstructor()
      ->getMock();

    $this->paymentMethodConfiguration->expects($this->any())
      ->method('getOwner')
      ->will($this->returnValue($owner));
    $this->paymentMethodConfiguration->expects($this->any())
      ->method('getPluginConfiguration')
      ->will($this->returnValue($payment_method_configuration_plugin_configuration));
    $this->paymentMethodConfiguration->expects($this->any())
      ->method('getPluginId')
      ->will($this->returnValue($payment_method_configuration_plugin_id));
    $this->paymentMethodConfiguration->expects($this->any())
      ->method('id')
      ->will($this->returnValue($payment_method_configuration_entity_id));
    $this->paymentMethodConfiguration->expects($this->any())
      ->method('label')
      ->will($this->returnValue($payment_method_configuration_entity_label));
    $this->paymentMethodConfiguration->expects($this->any())
      ->method('language')
      ->will($this->returnValue($language));
    $this->paymentMethodConfiguration->expects($this->any())
      ->method('status')
      ->will($this->returnValue($payment_method_configuration_entity_status));

    $this->paymentMethodConfigurationManager->expects($this->once())
      ->method('createInstance')
      ->with($payment_method_configuration_plugin_id, $payment_method_configuration_plugin_configuration)
      ->will($this->returnValue($payment_method_configuration_plugin));

    $build = $this->form->form($form, $form_state);
    // Make sure the payment method configuration plugin is instantiated only
    // once by building the form twice.
    $this->form->form($form, $form_state);
    unset($build['#process']);
    unset($build['langcode']);
    $expected_build = array(
      'type' => array(
        '#type' => 'item',
        '#title' => 'Type',
        '#markup' => $payment_method_configuration_plugin_label,
      ),
      'status' => array(
        '#type' => 'checkbox',
        '#title' => 'Enabled',
        '#default_value' => $payment_method_configuration_entity_status,
      ),
      'label' => array(
        '#type' => 'textfield',
        '#title' => 'Label',
        '#default_value' => $payment_method_configuration_entity_label,
        '#maxlength' => 255,
        '#required' => TRUE,
      ),
      'id' => array(
        '#type' => 'machine_name',
        '#default_value' => $payment_method_configuration_entity_id,
        '#maxlength' => 255,
        '#required' => TRUE,
        '#machine_name' => array(
          'source' => array('label'),
          'exists' => array($this->form, 'paymentMethodConfigurationIdExists'),
        ),
        '#disabled' => !$payment_method_configuration_entity_is_new,
      ),
      'owner' => array(
        '#type' => 'textfield',
        '#title' => 'Owner',
        '#default_value' => $owner_label,
        '#maxlength' => 255,
        '#autocomplete_route_name' => 'user.autocomplete',
        '#required' => TRUE,
      ),
      'plugin_form' => array(
          '#tree' => TRUE,
        ) + $payment_method_configuration_plugin_form,
    );
    $this->assertEquals($expected_build, $build);
  }

  /**
   * @covers ::copyFormValuesToEntity
   */
  public function testCopyFormValuesToEntity() {
    $label = $this->randomMachineName();
    $owner_id = mt_rand();
    $owner_label = $this->randomMachineName();
    $plugin_configuration = array(
      'bar' => $this->randomMachineName(),
    );
    $status = TRUE;

    $this->paymentMethodConfiguration->expects($this->once())
      ->method('setLabel')
      ->with($label);
    $this->paymentMethodConfiguration->expects($this->once())
      ->method('setOwnerId')
      ->with($owner_id);
    $this->paymentMethodConfiguration->expects($this->once())
      ->method('setPluginConfiguration')
      ->with($plugin_configuration);
    $this->paymentMethodConfiguration->expects($this->once())
      ->method('setStatus')
      ->with($status);

    $owner = $this->getMockBuilder('\Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock();
    $owner->expects($this->any())
      ->method('id')
      ->will($this->returnValue($owner_id));

    $this->userStorage->expects($this->once())
      ->method('loadByProperties')
      ->with(array(
        'name' => $owner_label,
      ))
      ->will($this->returnValue(array(
        mt_rand() => $owner,
      )));

    $plugin = $this->getMock('\Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationInterface');
    $plugin->expects($this->atLeastOnce())
      ->method('getConfiguration')
      ->will($this->returnValue($plugin_configuration));

    $form = array();
    // @todo Mock FormStateInterface once EntityForm no longer uses ArrayAccess.
    $form_state = $this->getMockBuilder('\Drupal\Core\Form\FormState')
      ->setMethods(array('get', 'getValues'))
      ->getMock();
    $map = array(
      array('payment_method_configuration', $plugin),
      array('values', array(
        'label' => $label,
        'owner' => $owner_label,
        'status' => $status,
      )),
    );
    $form_state->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnMap($map);
    $form_state->expects($this->atLeastOnce())
      ->method('getValues')
      ->willReturn(array(
        'label' => $label,
        'owner' => $owner_label,
        'status' => $status,
      ));

    $method = new \ReflectionMethod($this->form, 'copyFormValuesToEntity');
    $method->setAccessible(TRUE);

    $method->invokeArgs($this->form, array($this->paymentMethodConfiguration, $form, $form_state));
  }

  /**
   * @covers ::paymentMethodConfigurationIdExists
   */
  public function testPaymentMethodConfigurationIdExists() {
    $payment_method_configuration_id = $this->randomMachineName();

    $this->paymentMethodConfigurationStorage->expects($this->at(0))
      ->method('load')
      ->with($payment_method_configuration_id)
      ->will($this->returnValue($this->paymentMethodConfiguration));
    $this->paymentMethodConfigurationStorage->expects($this->at(1))
      ->method('load')
      ->with($payment_method_configuration_id)
      ->will($this->returnValue(NULL));

    $this->assertTrue($this->form->paymentMethodConfigurationIdExists($payment_method_configuration_id));
    $this->assertFalse($this->form->paymentMethodConfigurationIdExists($payment_method_configuration_id));
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithExistingOwner() {
    $owner_label = $this->randomMachineName();

    $owner = $this->getMockBuilder('\Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock();

    $this->userStorage->expects($this->once())
      ->method('loadByProperties')
      ->with(array(
        'name' => $owner_label,
      ))
      ->will($this->returnValue($owner));

    $payment_method_configuration_plugin = $this->getMock('\Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationInterface');

    $form = array(
      'plugin_form' => array(
        '#type' => $this->randomMachineName(),
      ),
    );
    // @todo Mock FormStateInterface once EntityForm no longer uses ArrayAccess.
    $form_state = $this->getMockBuilder('\Drupal\Core\Form\FormState')
      ->setMethods(array('get', 'getValues'))
      ->getMock();
    $map = array(
      array('payment_method_configuration', $payment_method_configuration_plugin),
    );
    $form_state->expects($this->any())
      ->method('get')
      ->willReturnMap($map);
    $form_state->expects($this->atLeastOnce())
      ->method('getValues')
      ->willReturn(array(
        'owner' => $owner_label,
      ));
    $form_state->expects($this->never())
      ->method('setError');

    $payment_method_configuration_plugin->expects($this->once())
      ->method('validateConfigurationForm')
      ->with($form['plugin_form'], $form_state);

    $this->form->validate($form, $form_state);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithoutExistingOwner() {
    $owner_label = $this->randomMachineName();

    $this->userStorage->expects($this->once())
      ->method('loadByProperties')
      ->with(array(
        'name' => $owner_label,
      ))
      ->willReturn(NULL);

    $payment_method_configuration_plugin = $this->getMock('\Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationInterface');

    $form = array(
      'owner' => array(
        '#parents' => array('owner'),
        '#type' => $this->randomMachineName(),
      ),
      'plugin_form' => array(
        '#parents' => array('plugin_form'),
        '#type' => $this->randomMachineName(),
      ),
    );
    // @todo Mock FormStateInterface once EntityForm no longer uses ArrayAccess.
    $form_state = $this->getMockBuilder('\Drupal\Core\Form\FormState')
      ->setMethods(array('get', 'getValues'))
      ->getMock();
    $map = array(
      array('payment_method_configuration', $payment_method_configuration_plugin),
    );
    $form_state->expects($this->any())
      ->method('get')
      ->willReturnMap($map);
    $form_state->expects($this->atLeastOnce())
      ->method('getValues')
      ->willReturn(array(
        'owner' => $owner_label,
      ));

    $payment_method_configuration_plugin->expects($this->once())
      ->method('validateConfigurationForm')
      ->with($form['plugin_form'], $form_state);

    $this->form->validate($form, $form_state);
  }

  /**
   * @covers ::save
   */
  public function testSave() {
    $form_state = $this->getMock('\Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('setRedirect')
      ->with('payment.payment_method_configuration.list');

    /** @var \Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationForm|\PHPUnit_Framework_MockObject_MockObject $form */
    $form = $this->getMockBuilder('\Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationForm')
      ->setConstructorArgs(array($this->stringTranslation, $this->currentUser, $this->userStorage, $this->paymentMethodConfigurationStorage, $this->paymentMethodConfigurationManager))
      ->setMethods(array('copyFormValuesToEntity'))
      ->getMock();
    $form->setEntity($this->paymentMethodConfiguration);

    $this->paymentMethodConfiguration->expects($this->once())
      ->method('save');

    $form->save(array(), $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitForm() {
    /** @var \Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationForm|\PHPUnit_Framework_MockObject_MockObject $form_object */
    $form_object = $this->getMockBuilder('\Drupal\payment\Entity\PaymentMethodConfiguration\PaymentMethodConfigurationForm')
      ->setConstructorArgs(array($this->stringTranslation, $this->currentUser, $this->userStorage, $this->paymentMethodConfigurationStorage, $this->paymentMethodConfigurationManager))
      ->setMethods(array('copyFormValuesToEntity'))
      ->getMock();
    $form_object->setEntity($this->paymentMethodConfiguration);

    $payment_method_configuration_plugin = $this->getMock('\Drupal\payment\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationInterface');

    $form = array(
      'plugin_form' => array(
        '#type' => $this->randomMachineName(),
      ),
    );
    // @todo Mock FormStateInterface once EntityForm no longer uses ArrayAccess.
    $form_state = $this->getMockBuilder('\Drupal\Core\Form\FormState')
      ->setMethods(array('get'))
      ->getMock();
    $map = array(
      array('payment_method_configuration', $payment_method_configuration_plugin),
    );
    $form_state->expects($this->any())
      ->method('get')
      ->willReturnMap($map);

    $payment_method_configuration_plugin->expects($this->once())
      ->method('submitConfigurationForm')
      ->with($form['plugin_form'], $form_state);

    $form_object->submitForm($form, $form_state);
  }

}

}

namespace {

if (!function_exists('drupal_set_message')) {
  function drupal_set_message() {}
}
if (!function_exists('form_execute_handlers')) {
  function form_execute_handlers() {}
}
if (!function_exists('form_state_values_clean')) {
  function form_state_values_clean() {}
}

}
