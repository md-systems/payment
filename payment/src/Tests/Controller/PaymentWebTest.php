<?php

/**
 * @file
 * Contains \Drupal\payment\Tests\Controller\PaymentWebTest.
 */

namespace Drupal\payment\Tests\Controller;

use Drupal\payment\Tests\Generate;
use Drupal\payment\Payment;
use Drupal\simpletest\WebTestBase ;

/**
 * Payment UI.
 *
 * @group Payment
 */
class PaymentWebTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = array('payment', 'payment_test');

  /**
   * Tests the payment UI.
   */
  protected function testPaymentUi() {
    $payment_method = Payment::methodManager()->createInstance('payment_test');
    $payment = Generate::createPayment(2, $payment_method);
    $payment->save();
    $payment = entity_load_unchanged('payment', $payment->id());

    // View the administrative listing.
    $this->drupalLogin($this->drupalCreateUser(array('access administration pages')));
    $this->drupalGet('admin/content');
    $this->assertResponse('200');
    $this->assertNoLinkByHref('admin/content/payment');
    $this->drupalGet('admin/content/payment');
    $this->assertResponse('403');
    $this->drupalLogin($this->drupalCreateUser(array('access administration pages', 'payment.payment.view.any')));
    $this->drupalGet('admin/content');
    $this->clickLink(t('Payments'));
    if ($this->assertResponse('200')) {
      $this->assertTitle(t('Payments | Drupal'));
      $this->assertText(t('Last updated'));
      $this->assertText(t('Payment method'));
      $this->assertText(t('EUR 24.20'));
      $this->assertText($payment_method->getPluginLabel());
    }
    $this->drupalLogout();

    // View the payment.
    $path = 'payment/' . $payment->id();
    $this->drupalGet($path);
    $this->assertResponse('403');
    $this->drupalLogin($this->drupalCreateUser(array('payment.payment.view.any')));
    $this->drupalGet($path);
    if ($this->assertResponse('200')) {
      $this->assertText(t('Payment method'));
      $this->assertText(t('Status'));
    }

    // Edit the payment.
    $path = 'payment/' . $payment->id() . '/edit';
    $this->drupalGet($path);
    $this->assertResponse('403');
    $this->drupalLogin($this->drupalCreateUser(array('payment.payment.update.any')));
    $this->drupalGet($path);
    if ($this->assertResponse('200')) {
      $this->assertFieldByXPath('//select[@name="payment_currency_code"]');
      $this->assertFieldByXPath('//select[@name="payment_line_items[line_items][bar][plugin_form][amount][currency_code]"]');
      $this->drupalPostForm(NULL, array(
        'payment_currency_code' => 'XXX',
        'payment_line_items[line_items][bar][plugin_form][amount][currency_code]' => 'XXX',
      ), t('Save'));
    }
    $this->assertUrl('payment/' . $payment->id());
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = entity_load_unchanged('payment', $payment->id());
    $this->assertEqual($payment->getCurrencyCode(), 'XXX');
    $this->assertEqual($payment->getLineItem('bar')->getCurrencyCode(), 'XXX');

    // Delete a payment.
    $path = 'payment/' . $payment->id() . '/delete';
    $this->drupalGet($path);
    $this->assertResponse('403');
    $this->drupalLogin($this->drupalCreateUser(array('payment.payment.delete.any', 'payment.payment.view.any')));
    $this->drupalGet($path);
    if ($this->assertResponse('200')) {
      $this->clickLink(t('Cancel'));
      $this->assertUrl('payment/' . $payment->id());
      $this->drupalGet($path);
      $this->drupalPostForm(NULL, array(), t('Delete'));
      $this->assertResponse('200');
      $this->assertFalse((bool) \Drupal::entityManager()->getStorage('payment')->loadUnchanged($payment->id()));
    }
  }
}
