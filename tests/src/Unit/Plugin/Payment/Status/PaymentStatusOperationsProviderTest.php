<?php

/**
 * @file
 * Contains \Drupal\Tests\payment\Unit\Plugin\Payment\Status\PaymentStatusOperationsProviderTest.
 */

namespace Drupal\Tests\plugin\Unit;

use Drupal\payment\Plugin\Payment\Status\PaymentStatusOperationsProvider;

/**
 * @coversDefaultClass \Drupal\payment\Plugin\Payment\Status\PaymentStatusOperationsProvider
 *
 * @group Plugin
 */
class PaymentStatusOperationsProviderTest extends DefaultPluginTypeOperationsProviderTest {

  /**
   * The class under test.
   *
   * @var \Drupal\payment\Plugin\Payment\Status\PaymentStatusOperationsProvider
   */
  protected $sut;

  public function setUp() {
    parent::setUp();

    $this->sut = new PaymentStatusOperationsProvider($this->stringTranslation);
  }

}
