<?php
 /**
 *@file
 *Contains Drupal\commerce_tax_plus\Form\CommerceTaxPlusForm.
 */
  namespace Drupal\commerce_tax_plus\Form;
  use Drupal\Core\Form\FormBase;
  use Drupal\Core\Form\FormStateInterface;
  /**
  *Class smartstreetsSettings
  *
  *@package Drupal\ValidateAddress\Form
  */
  class CommerceTaxPlusForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['CommerceTaxPlusForm_street_address'] = array(
        '#type' => 'textfield',
        '#title' => t('Street Addres'),
        '#default_value' => t(''),
    );
    $form['CommerceTaxPlusForm_city'] = array(
        '#type' => 'textfield',
        '#title' => t('City'),
        '#default_value' => t(''),
    );
    $form['CommerceTaxPlusForm_state'] = array(
        '#type' => 'textfield',
        '#title' => t('State'),
        '#default_value' => t(''),
    );
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //drupal_set_message($this->t('Your street address is @street', array('@street' => $form_state->getValue('ValidateAddress_street_address'))));
    $_SESSION['street_address'] = $form_state->getValue('CommerceTaxPlusForm_street_address');
    $_SESSION['city'] = $form_state->getValue('CommerceTaxPlusForm_city');
    $_SESSION['state'] = $form_state->getValue('CommerceTaxPlusForm_state');
    $form_state->setRedirect('ValidateAddress.lookup');
    return;
  }

}
