<?php

namespace Drupal\commerce_tax_plus\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @CommerceCheckoutPane(
 *  id = "custom_order_info_message",
 *  label = @Translation("Custom order info message"),
 *  admin_label = @Translation("Custom order info message"),
 *  default_step = "order_information",
 * )
 */
class OrderInformationMessage extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
      if($this->order->getData('address_error'))  {
        $message = $this->order->getData('address_error');
      }
      else {
          $message ="";
      }
      $pane_form['message'] = [
      '#markup' => $this->t($message),
    ];
    return $pane_form;
  }

}