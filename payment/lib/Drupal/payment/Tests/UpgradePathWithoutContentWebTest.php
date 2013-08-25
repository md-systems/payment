<?php

/**
 * @file
 * Contains class Drupal\payment\Tests\UpgradePathWithContentWebTest.
 */

namespace Drupal\payment\Tests;

use Drupal\system\Tests\Upgrade\UpgradePathTestBase;

/**
 * Tests Payment's upgrade path.
 */
class UpgradePathWithoutContentWebTest extends UpgradePathTestBase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'description' => '',
      'name'  => 'Upgrade path (without existing content and configuration)',
      'group' => 'Payment',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->databaseDumpFiles[] = drupal_get_path('module', 'payment') . '/../payment-database-dump.php';
    parent::setUp();
  }

  /**
   * Tests a successful upgrade.
   */
  protected function testPaymentUpgrade() {
    $this->assertTrue($this->performUpgrade(), 'The upgrade was completed successfully.');
  }
}
