<?php

namespace Drupal\commerce_tax_plus\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;

//This is the use statement for the PaymentInformation BASE plugin aliased as BasePaymentInformation:
use Drupal\commerce_payment\Plugin\Commerce\CheckoutPane\PaymentInformation as BasePaymentInformation;

//This is the use statement for the SmartyStreetsAPI service module:
use Drupal\SmartyStreetsAPI\Controller\SmartyStreetsAPIService;


/**
 * Provides the payment information plus pane.
 *
 * @CommerceCheckoutPane(
 *   id = "payment_info_plus",
 *   label = @Translation("Payment Information"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */

//PayerAddressValidation class extending the functionality in the PaymentInformation 
//(aka BasePaymentInformation - see use statement above)
class PayerAddressValidation extends BasePaymentInformation {
    //Declaration of the $APIService variable to be used to connect to the SmartyStreetsAPI service module:
    /**
     * @var \Drupal\SmartyStreetsAPI\Controller\SmartyStreetsAPIService
     */
    protected $APIService;
    
    //Constructor function to setup the APIService and call the parent constructure function from the
    //CheckOutPaneBase plugin.
    /**
     * Constructs a new CheckoutPaneBase object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
     *   The parent checkout flow.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, SmartyStreetsAPIService $APIService) {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);
        $this->APIService = $APIService;
    }
    
    //create function that overrides the CheckOutBasePane plugin create function.
    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $checkout_flow,
            $container->get('entity_type.manager'),
            $container->get('smartystreetsapi.service')       
            );
    }
    
    //This is the validatePaneForm function that overrides the one in the PaymentInformation plugin (aka BasePaymentInformation)
    //In this function is where the address that the customer entered is validated by calling the LookupValidAddress function
    //to check it via the SmartyStreetsAPI service plugin.  It will set an error state and send the user back to the
    //billing information screen to prompt the user to re-enter the customers address.
    /**
     * {@inheritdoc}
     */
    public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
        parent::validatePaneForm($pane_form, $form_state, $complete_form);
        $values = $form_state->getValue($pane_form['#parents']);
        $cust_address = $values['add_payment_method']['billing_information']['address'][0]['address']['address_line1'];
        $cust_city = $values['add_payment_method']['billing_information']['address'][0]['address']['locality'];
        $cust_state = $values['add_payment_method']['billing_information']['address'][0]['address']['administrative_area'];
        $cust_zip = $values['add_payment_method']['billing_information']['address'][0]['address']['postal_code'];
        $valid_address = $this->LookupValidAddress($cust_address, $cust_city, $cust_state);
        if($valid_address==0){
            $form_state->setErrorByName('billing_information', t('<strong><font color="red">Error: The address entered is not valid. Please input a valid address.</font></strong>'));
        }
    }

    //This is the LookupValidAddress function.  This function uses the SmartyStreetsAPI service to
    //validate the address entered by calling the services at smartystreets.com.
    //todo: add zip code into lookup (also requires smartystreetsAPI module update too)
    public function LookupValidAddress($street_address,$city,$state) {
        $arrLookup = $this->APIService->LookupAddress($street_address,$city,$state);
        if ($arrLookup['valid'] == 1) {
            return 1;
        }
        else {
            return 0;
        }
    }
    
}
