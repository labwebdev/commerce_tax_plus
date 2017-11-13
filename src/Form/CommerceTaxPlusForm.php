<?php
 /**
 *@file
 *Contains Drupal\commerce_tax_plus\Form\CommerceTaxPlusForm.
 */
  namespace Drupal\commerce_tax_plus\Form;

  use Drupal\Core\Form\FormBase;
  use Drupal\Core\Form\FormStateInterface;
  use Drupal\SmartyStreetsAPI\Controller\SmartyStreetsAPIService;
  use Symfony\Component\DependencyInjection\ContainerInterface;


  /**
  *Class smartstreetsSettings
  *
  *@package Drupal\ValidateAddress\Form
  */
  class CommerceTaxPlusForm extends FormBase {

    /**
   * @var \Drupal\SmartyStreetsAPI\Controller\SmartyStreetsAPIService
   */
    protected $APIService;

    /**
     * {@inheritdoc}
     */
    public function __construct(SmartyStreetsAPIService $APIService) {
      $this->APIService = $APIService;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
      return new static(
        $container->get('smartystreetsapi.service')
      );
    }

    /**
    * {@inheritdoc}
    */
    public function getFormId() {
      return 'commerce_tax_plus_form';
    }
    /**
    * {@inheritdoc}
    */
    public function buildForm(array $form, FormStateInterface $form_state) {
      /** @var \Drupal\commerce_store\Resolver\StoreResolverInterface $resolver */
      $resolver = \Drupal::service('commerce_store.default_store_resolver');
      $store_street_address = $resolver->resolve()->getAddress()->getAddressLine1();
      $store_city = $resolver->resolve()->getAddress()->getLocality();
      $store_state = $resolver->resolve()->getAddress()->getAdministrativeArea();
      $store_county = $this->LookupValidAddress($store_street_address,$store_city,$store_state);
      $form['CommerceTaxPlusForm_street_address'] = array(
          '#type' => 'textfield',
          '#title' => t('Street Addres'),
          '#default_value' => t($store_street_address),
      );
      $form['CommerceTaxPlusForm_city'] = array(
          '#type' => 'textfield',
          '#title' => t('City'),
          '#default_value' => t($store_city),
      );
      $form['CommerceTaxPlusForm_state'] = array(
          '#type' => 'textfield',
          '#title' => t('State'),
          '#default_value' => t($store_state),
      );
      $form['CommerceTaxPlusForm_county'] = array(
          '#type' => 'textfield',
          '#title' => t('County'),
          '#default_value' => t($store_county),
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
    $_SESSION['street_address'] = $form_state->getValue('CommerceTaxPlusForm_street_address');
    $_SESSION['city'] = $form_state->getValue('CommerceTaxPlusForm_city');
    $_SESSION['state'] = $form_state->getValue('CommerceTaxPlusForm_state');
    $form_state->setRedirect('ValidateAddress.lookup');
    return;
  }

  public function LookupValidAddress($street_address,$city,$state) {

      $arrLookup = $this->APIService->LookupAddress($street_address,$city,$state);
      if ($arrLookup['valid'] == 1) {
        return $arrLookup['county'];
     }
     else {
       return 'error';
     }

   }
}
