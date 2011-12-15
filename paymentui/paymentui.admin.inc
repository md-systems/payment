<?php

/**
 * @file
 * The Payment administration interface.
 */

/**
 * Shows a page with controllers payment methods can be added for.
 *
 * @return string
 */
function payment_page_payment_method_add_select_controller() {
  $controllers = payment_method_controller_load_multiple();
  unset($controllers['PaymentMethodControllerUnavailable']);
  if ($controllers) {
    $items = array();
    foreach ($controllers as $controller) {
      $items[] = array(
        'title' => $controller->title,
        'href' => 'admin/config/services/payment/method/add/' . get_class($controller),
        'description' => $controller->description,
        'localized_options' => array(),
      );
    }
    return theme('admin_block_content', array(
      'content' => $items,
    ));
  }
  else {
    return t('There are no payment method types available.');
  }
}

/**
 * Create a blank payment method and return its payment form.
 *
 * @param $controller_class_name string
 *   The name of the controller class for which to create a payment method.
 *
 * @return array
 *   A Drupal form.
 */
function paymentui_payment_method_form_add($controller) {
  $payment_method = new PaymentMethod(array(
    'controller' => $controller,
  ));

  return paymentui_payment_method_form($payment_method);
}

/**
 * Return a payment method's settings form.
 *
 * @param $payment_method PaymentMethod
 *
 * @return array
 *   A Drupal form.
 */
function paymentui_payment_method_form(PaymentMethod $payment_method) {
  return drupal_get_form('payment_method_form_' . $payment_method->controller->payment_method_form_id, $payment_method);
}

/**
 * Implements form build callback: the payment method add/edit form.
 *
 * @see paymentui_forms()
 * @see paymentui_payment_method_form()
 *
 * @param $payment_method PaymentMethod
 */
function paymentui_form_payment_method(array $form, array &$form_state, PaymentMethod $payment_method) {
  $form_state['payment_method'] = $payment_method;

  $form['controller'] = array(
    '#type' => 'item',
    '#title' => t('Type'),
    '#markup' => check_plain($payment_method->controller->title),
  );
  $form['title_specific'] = array(
    '#type' => 'textfield',
    '#title' => t('Title (specific)'),
    '#description' => t('The specific title is often only displayed to people such as administrators who need to know the exact payment method that is used, for instance <em>Paypal Website Payments Pro</em>.'),
    '#default_value' => $payment_method->title_specific,
    '#maxlength' => 255,
    '#required' => TRUE,
  );
  $form['title_generic'] = array(
    '#type' => 'textfield',
    '#title' => t('Title (generic)'),
    '#description' => t('The generic title is often only displayed to people such as payers who only need to know the generic payment method that is used, for instance <em>Paypal</em>.'),
    '#default_value' => $payment_method->title_generic,
    '#maxlength' => 255,
    '#required' => TRUE,
  );
  $form['actions'] = array(
    '#type' => 'actions',
  );
  $form['actions']['save'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );
  if ($payment_method->pmid) {
    $form['actions']['delete'] = array(
      '#type' => 'link',
      '#title' => t('Delete'),
      '#href' => 'admin/config/services/payment/method/' . $payment_method->pmid . '/delete',
    );
  }

  return $form;
}

/**
 * Implements form submit callback for paymentui_form_payment_method().
 */
function paymentui_form_payment_method_submit(array $form, array &$form_state) {
  $values = $form_state['values'];
  $payment_method = $form_state['payment_method'];
  $payment_method->title_specific = $values['title_specific'];
  $payment_method->title_generic = $values['title_generic'];
  entity_save('payment_method', $payment_method);
  $form_state['redirect'] = 'admin/config/services/payment/method';
  drupal_set_message(t('Payment method %title has been saved.', array(
    '%title' => $payment_method->title_specific,
  )));
}

/**
 * Implements form build callback: payment method deletion form.
 */
function paymentui_form_payment_method_delete(array $form, array &$form_state, PaymentMethod $payment_method) {
  $form_state['payment_method'] = $payment_method;

  return confirm_form($form, t('Do you really want to delete payment method %title?', array(
    '%title' => $form_state['payment_method']->title_specific,
  )), 'admin/config/services/payment/method', t('Existing payments that use this payment method will become unusable. This action cannot be undone.'), t('Delete payment method'));
}

/**
 * Implements form submit callback for paymentui_form_payment_method_delete().
 */
function paymentui_form_payment_method_delete_submit(array $form, array &$form_state) {
  $payment_method = $form_state['payment_method'];
  entity_delete('payment_method', $payment_method->pmid);
  $form_state['redirect'] = 'admin/config/services/payment/method';
  drupal_set_message(t('Payment method %title has been deleted.', array(
    '%title' => $payment_method->title_specific,
  )));
}

/**
 * Implements form build callback: global configuration form.
 */
function paymentui_form_global_configuration(array $form, array &$form_state) {
  $form['payment_display_log_entries'] = array(
    '#type' => 'checkbox',
    '#title' => t('Display <em>Payment</em> log entries on screen'),
    '#default_value' => variable_get('payment_display_log_entries', TRUE),
  );

  return system_settings_form($form);
}