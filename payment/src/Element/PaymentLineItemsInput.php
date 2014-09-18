<?php

/**
 * @file
 * Contains \Drupal\payment\Element\PaymentLineItemsInput.
 */

namespace Drupal\payment\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\payment\Payment;
use Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface;
use Drupal\Core\Render\Element\ElementInterface;
use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a payment line items configuration element.
 *
 * Use \Drupal\payment\Element\PaymentLineItemsInput::getLineItemsData() to get
 * the 'return' value.
 *
 * @FormElement("payment_line_items_input")
 *
 */
class PaymentLineItemsInput extends RenderElement implements ElementInterface {

  /**
   * An unlimited cardinality.
   */
  const CARDINALITY_UNLIMITED = -1;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return array(
      // The number of values this element allows, which must be at least as
      // many as the number of line items in the default value. For unlimited
      // values, use
      // \Drupal\payment\Element\PaymentLineItemsInput::CARDINALITY_UNLIMITED.
      '#cardinality' => self::CARDINALITY_UNLIMITED,
      // Values are arrays with two keys:
      // - plugin_id: the ID of the line item plugin instance.
      // - plugin_configuration: the configuration of the line item plugin
      //   instance.
      '#default_value' => array(),
      '#element_validate' => array(array(get_class($this), 'validate')),
      '#input' => TRUE,
      '#process' => array(array('\Drupal\Core\Render\Element\Container', 'processContainer'), array(get_class($this), 'process')),
      '#tree' => TRUE,
      '#theme_wrappers' => array('container'),
      '#value' => array(),
    );
  }

  /**
   * Implements form #process callback.
   */
  public static function process(array $element, FormStateInterface $form_state, array $form) {
    // Validate the element configuration.
    if ($element['#cardinality'] != self::CARDINALITY_UNLIMITED && count($element['#default_value']) > $element['#cardinality']) {
      throw new \InvalidArgumentException('The number of default line items can not be higher than the cardinality.');
    }
    foreach ($element['#default_value'] as $line_item) {
      if (!($line_item instanceof PaymentLineItemInterface)) {
        throw new \InvalidArgumentException('A default line item does not implement \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface.');
      }
    }

    static::initializeLineItems($element, $form_state);
    /** @var \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface[] $line_items */
    $line_items = array_values(static::getLineItems($element, $form_state));

    // Build the line items.
    $element['line_items'] = array(
      '#empty' => t('There are no line items yet.'),
      '#header' => array(t('Line item'), t('Weight'), t('Operations')),
      '#tabledrag' => array(array(
        'action' => 'order',
        'relationship' => 'self',
        'group' => 'payment-line-item-weight',
      )),
      '#type' => 'table',
      '#tree' => TRUE,
    );

    foreach ($line_items as $delta => $line_item) {
      $element['line_items'][$line_item->getName()] = array(
        '#attributes' => array(
          'class' => array(
            'payment-line-item',
            'payment-line-item-name-' . $line_item->getName(),
            'payment-line-item-plugin-' . $line_item->getPluginId(),
          ),
        ),
      );
      $element['line_items'][$line_item->getName()]['plugin_form'] = $line_item->buildConfigurationForm($form, $form_state);
      $element['line_items'][$line_item->getName()]['weight'] = array(
        '#attributes' => array(
          'class' => array('payment-line-item-weight'),
        ),
        '#default_value' => $delta,
        '#delta' => count($line_items),
        '#title' => t('Weight'),
        '#type' => 'weight',
      );
      $element['line_items'][$line_item->getName()]['delete'] = array(
        '#ajax' => array(
          'callback' => array(get_class(), 'deleteAjaxSubmit'),
          'effect' => 'fade',
          'event' => 'mousedown',
        ),
        '#limit_validation_errors' => array(),
        '#submit' => array(array(get_class(), 'deleteSubmit')),
        '#type' => 'submit',
        '#value' => t('Delete'),
      );
    }

    // "Add more line items" button.
    $element['add_more'] = array(
      '#access' => $element['#cardinality'] == self::CARDINALITY_UNLIMITED || count($line_items) < $element['#cardinality'],
      '#attributes' => array(
        'class' => array('payment-add-more'),
      ),
      '#id' => drupal_html_id('payment-add-more'),
      '#type' => 'container',
    );
    $manager = Payment::lineItemManager();
    $element['add_more']['type'] = array(
      '#options' => $manager->options(),
      '#title' => t('Type'),
      '#type' => 'select',
    );
    $element['add_more']['add'] = array(
      '#ajax' => array(
        'callback' => array(get_class(), 'addMoreAjaxSubmit'),
        'effect' => 'fade',
        'event' => 'mousedown',
        'wrapper' => $element['#id'],
      ),
      '#limit_validation_errors' => array(
        array_merge($element['#parents'], array('add_more', 'type')),
      ),
      '#submit' => array(array(get_class(), 'addMoreSubmit')),
      '#type' => 'submit',
      '#value' => t('Add a line item'),
    );

    return $element;
  }

  /**
   * Implements form #element_validate callback.
   */
  public static function validate(array $element, FormStateInterface $form_state, array &$form) {
    // Reorder line items based on their weight elements.
    $line_items = array();
    $values = $form_state->getValues();
    $values = NestedArray::getValue($values, $element['#parents']);
    if ($values['line_items']) {
      foreach ($values['line_items'] as $name => $line_item_values) {
        $line_items[$name] = $line_item_values['weight'];
      }
      asort($line_items);
      foreach (static::getLineItems($element, $form_state) as $line_item) {
        $line_items[$line_item->getName()] = $line_item;
        $line_item->validateConfigurationForm($element['line_items'][$line_item->getName()]['plugin_form'], $form_state);
        // @todo Find a way to call the submit handler after the validation
        //   stage.
        $line_item->submitConfigurationForm($element['line_items'][$line_item->getName()]['plugin_form'], $form_state);
      }
      static::setLineItems($element, $form_state, array_values($line_items));
    }
  }

  /**
   * Implements form #submit callback.
   */
  public static function addMoreSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->get('triggering_element');
    $parents = array_slice($triggering_element['#array_parents'], 0, -2);
    $root_element = NestedArray::getValue($form, $parents);
    $values = $form_state->getValues();
    $values = NestedArray::getValue($values, array_slice($triggering_element['#parents'], 0, -2));
    $line_items = static::getLineItems($root_element, $form_state);
    $line_items[] = Payment::lineItemManager()
      ->createInstance($values['add_more']['type'])
      ->setName(static::createLineItemName($root_element, $form_state, $values['add_more']['type']));
    static::setLineItems($root_element, $form_state, $line_items);
    $form_state->setRebuild();
  }

  /**
   * Implements form AJAX callback.
   */
  public static function addMoreAjaxSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->get('triggering_element');
    $parents = array_slice($triggering_element['#array_parents'], 0, -2);
    $root_element = NestedArray::getValue($form, $parents);

    return array_intersect_key($root_element, array_flip(Element::Children($root_element)));
  }

  /**
   * Implements form #submit callback.
   */
  public static function deleteSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->get('triggering_element');
    $root_element_parents  = array_slice($triggering_element['#array_parents'], 0, -3);
    $root_element = NestedArray::getValue($form, $root_element_parents);
    $parents = $triggering_element['#array_parents'];
    $line_item_name = $parents[count($parents) - 2];
    /** @var \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface[] $line_items */
    $line_items = array_values(static::getLineItems($root_element, $form_state));
    foreach ($line_items as $i => $line_item) {
      if ($line_item->getName() == $line_item_name) {
        unset($line_items[$i]);
      }
    }
    static::setLineItems($root_element, $form_state, $line_items);
    $form_state->setRebuild();
  }

  /**
   * Implements form AJAX callback.
   */
  public static function deleteAjaxSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->get('triggering_element');
    $root_element_parents  = array_slice($triggering_element['#array_parents'], 0, -3);
    $root_element = NestedArray::getValue($form, $root_element_parents);
    $parents = $triggering_element['#array_parents'];
    $line_item_name = $parents[count($parents) - 2];
    $response = new AjaxResponse();
    $response->addCommand(new RemoveCommand('#' . $root_element['#id'] . ' .payment-line-item-name-' . $line_item_name));

    return $response;
  }

  /**
   * Creates a unique line item name.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param string $name
   *   The preferred name.
   *
   * @return string
   */
  protected static function createLineItemName(array $element, FormStateInterface $form_state, $name) {
    $counter = NULL;
    while (static::lineItemExists($element, $form_state, $name . $counter)) {
      $counter++;
    }

    return $name . $counter;
  }

  /**
   * Checks if a line item name already exists.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param string $name
   *
   * @return bool
   */
  protected static function lineItemExists(array $element, FormStateInterface $form_state, $name) {
    foreach (static::getLineItems($element, $form_state) as $line_item) {
      if ($line_item->getName() == $name) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Stores the line items in the form's state.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface[] $line_items
   */
  protected static function setLineItems(array $element, FormStateInterface $form_state, array $line_items) {
    $payment_line_item = $form_state->get('payment_line_item');
    $payment_line_item[$element['#name']] = $line_items;
    $form_state->set('payment_line_item', $payment_line_item);
  }

  /**
   * Retrieves the line items from the form's state.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface[]
   */
  public static function getLineItems(array $element, FormStateInterface $form_state) {
    $payment_line_item = $form_state->get('payment_line_item');

    return $payment_line_item[$element['#name']];
  }

  /**
   * Check if the form's state has been initialized for an element.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return bool
   */
  protected static function initializeLineItems(array $element, FormStateInterface $form_state) {
    if (!($form_state->get('payment_line_item') && array_key_exists($element['#name'], $form_state->get('payment_line_item')))) {
      self::setLineItems($element, $form_state, $element['#default_value']);
    }
  }
}
