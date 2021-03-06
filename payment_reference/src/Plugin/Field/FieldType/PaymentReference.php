<?php

/**
 * @file
 * Contains \Drupal\payment_reference\Plugin\Field\FieldType\PaymentReference.
 */

namespace Drupal\payment_reference\Plugin\Field\FieldType;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\currency\Entity\Currency;
use Drupal\entity_reference\ConfigurableEntityReferenceItem;
use Drupal\payment\Element\PaymentLineItemsInput;
use Drupal\payment\Payment;
use Drupal\payment_reference\PaymentReference as PaymentReferenceServiceWrapper;

/**
 * Provides a configurable payment reference field.
 *
 * @FieldType(
 *   configurable = "true",
 *   constraints = {
 *     "ValidReference" = {}
 *   },
 *   default_formatter = "entity_reference_label",
 *   default_widget = "payment_reference",
 *   id = "payment_reference",
 *   label = @Translation("Payment reference"),
 *   list_class = "\Drupal\payment_reference\Plugin\Field\FieldType\PaymentReferenceItemList"
 * )
 */
class PaymentReference extends ConfigurableEntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'target_bundle' => 'payment_reference',
      'target_type' => 'payment',
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultInstanceSettings() {
    return parent::defaultInstanceSettings() + array(
      'currency_code' => '',
      'line_items_data' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_storage_definition) {
    return array(
      'columns' => array(
        'target_id' => array(
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
        ),
      ),
      'indexes' => array(
        'target_id' => array('target_id'),
      ),
      'foreign keys' => array(
        'target_id' => array(
          'table' => 'payment',
          'columns' => array(
            'target_id' => 'id',
          ),
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function instanceSettingsForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\currency\FormHelperInterface $form_helper */
    $form_helper = \Drupal::service('currency.form_helper');

    $form['#element_validate'] = array(get_class() . '::instanceSettingsFormValidate');
    $form['currency_code'] = array(
      '#empty_value' => '',
      '#type' => 'select',
      '#title' => $this->t('Payment currency'),
      '#options' => $form_helper->getCurrencyOptions(),
      '#default_value' => $this->getSetting('currency_code'),
      '#required' => TRUE,
    );
    $line_items = array();
    foreach ($this->getSetting('line_items_data') as $line_item_data) {
      $line_items[] = Payment::lineItemManager()->createInstance($line_item_data['plugin_id'], $line_item_data['plugin_configuration']);
    }
    $form['line_items'] = array(
      '#type' => 'payment_line_items_input',
      '#title' => $this->t('Line items'),
      '#default_value' => $line_items,
      '#required' => TRUE,
      '#currency_code' => '',
    );

    return $form;
  }

  /**
   * Implements #element_validate callback for self::instanceSettingsForm().
   */
  public static function instanceSettingsFormValidate(array $element, FormStateInterface $form_state) {
    $add_more_button_form_parents = array_merge($element['#array_parents'], array('line_items', 'add_more', 'add'));
    // Only set the field settings as a value when it is not the "Add more"
    // button that has been clicked.
    $triggering_element = $form_state->get('triggering_element');
    if ($triggering_element['#array_parents'] != $add_more_button_form_parents) {
      $values = $form_state->getValues();
      $values = NestedArray::getValue($values, $element['#array_parents']);
      $line_items_data = array();
      foreach (PaymentLineItemsInput::getLineItems($element['line_items'], $form_state) as $line_item) {
        $line_items_data[] = array(
          'plugin_id' => $line_item->getPluginId(),
          'plugin_configuration' => $line_item->getConfiguration(),
        );
      }
      $value = array(
        'currency_code' => $values['currency_code'],
        'line_items_data' => $line_items_data,
      );
      $form_state->setValueForElement($element, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $entity_type_id = $this->getFieldDefinition()->getFieldStorageDefinition()->getTargetEntityTypeId();
    $entity_storage = \Drupal::entityManager()->getStorage($entity_type_id);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $current_entity */
    $current_entity = $this->getRoot();
    $unchanged_payment_id = NULL;
    if ($current_entity->id()) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $unchanged_entity */
      $unchanged_entity = $entity_storage->loadUnchanged($current_entity->id());
      if ($unchanged_entity) {
        $unchanged_payment_id = $unchanged_entity->get($this->getFieldDefinition()->getName())->get($this->name)->get('target_id')->getValue();
      }
    }
    $current_payment_id = $this->get('target_id')->getValue();

    // Only claim the payment if the payment ID in this field has changed since
    // the field's target entity was last saved or if the entity is new.
    if (!$current_entity->id() || $current_payment_id != $unchanged_payment_id) {
      $queue = PaymentReferenceServiceWrapper::queue();
      $acquisition_code = $queue->claimPayment($current_payment_id);
      if ($acquisition_code !== FALSE) {
        $queue->acquirePayment($current_payment_id, $acquisition_code);
      }
      else {
        $this->get('target_id')->setValue(0);
      }
    }
  }

}
