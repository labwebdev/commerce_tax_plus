<?php

namespace Drupal\commerce_tax_plus\Plugin\Commerce\TaxType;


use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_tax\Resolver\ChainTaxRateResolverInterface;
use Drupal\commerce_tax\TaxZone;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

//mod
use Drupal\commerce_tax\Plugin\Commerce\TaxType\LocalTaxTypeBase;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\SmartyStreetsAPI\Controller\SmartyStreetsAPIService;
use CommerceGuys\Addressing\AddressInterface;
use Drupal\commerce\Response\NeedsRedirectException;


/**
 * Provides the County tax type.
 *
 * @CommerceTaxType(
 *   id = "custom_plus",
 *   label = "Custom Plus",
 * )
 */
class CustomPlus extends LocalTaxTypeBase {
    //mod
    /**
     * @var \Drupal\SmartyStreetsAPI\Controller\SmartyStreetsAPIService
     */
    protected $APIService;
     
   /**
   * The UUID generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  //mod
  /**
   * Constructs a new County object.
   * {@inheritdoc}
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The rounder.
   * @param \Drupal\commerce_tax\Resolver\ChainTaxRateResolverInterface $chain_rate_resolver
   *   The chain tax rate resolver.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_generator
   *   The UUID generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, RounderInterface $rounder, ChainTaxRateResolverInterface $chain_rate_resolver, UuidInterface $uuid_generator, SmartyStreetsAPIService $APIService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $event_dispatcher, $rounder, $chain_rate_resolver);

    $this->uuidGenerator = $uuid_generator;
    //mod
    $this->APIService = $APIService;
    
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('commerce_price.rounder'),
      $container->get('commerce_tax.chain_tax_rate_resolver'),
      $container->get('uuid'),
      //mod
      $container->get('smartystreetsapi.service')
      
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'display_label' => 'tax',
      'round' => TRUE,
      'rates' => [],
      'territories' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);

    foreach ($this->configuration['rates'] as &$rate) {
      if (isset($rate['amount'])) {
        // The 'amount' key was renamed to 'percentage' in 2.0-rc2.
        $rate['percentage'] = $rate['amount'];
        unset($rate['amount']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['display_label'] = [
      '#type' => 'select',
      '#title' => t('Display label'),
      '#description' => t('Used to identify the applied tax in order summaries.'),
      '#options' => $this->getDisplayLabels(),
      '#default_value' => $this->configuration['display_label'],
    ];
    $form['round'] = [
      '#type' => 'checkbox',
      '#title' => t('Round tax at the order item level'),
      '#description' => t('Sales taxes are not rounded at the order item level, while VAT-style taxes are rounded.'),
      '#default_value' => $this->configuration['round'],
    ];

    $wrapper_id = Html::getUniqueId('tax-type-ajax-wrapper');
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';
    // Ajax callbacks need rates and territories to be in form state.
    if (!$form_state->get('tax_form_initialized')) {
      $rates = $this->configuration['rates'];
      $territories = $this->configuration['territories'];
      // Initialize empty rows in case there's no data yet.
      $rates = $rates ?: [NULL];
      $territories = $territories ?: [NULL];

      $form_state->set('rates', $rates);
      $form_state->set('territories', $territories);
      $form_state->set('tax_form_initialized', TRUE);
    }

    $form['rates'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Tax rate'),
        $this->t('Percentage'),
        $this->t('Operations'),
      ],
      '#input' => FALSE,
    ];
    foreach ($form_state->get('rates') as $index => $rate) {
      $rate_form = &$form['rates'][$index];
      $rate_form['rate']['id'] = [
        '#type' => 'value',
        '#value' => $rate ? $rate['id'] : $this->uuidGenerator->generate(),
      ];
      $rate_form['rate']['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $rate ? $rate['label'] : '',
        '#maxlength' => 255,
        '#required' => TRUE,
      ];
      $rate_form['percentage'] = [
        '#type' => 'commerce_number',
        '#title' => $this->t('Percentage'),
        '#default_value' => $rate ? $rate['percentage'] * 100 : 0,
        '#field_suffix' => $this->t('%'),
        '#min' => 0,
        '#max' => 100,
      ];
      $rate_form['remove'] = [
        '#type' => 'submit',
        '#name' => 'remove_rate' . $index,
        '#value' => $this->t('Remove'),
        '#limit_validation_errors' => [],
        '#submit' => [[get_class($this), 'removeRateSubmit']],
        '#rate_index' => $index,
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }
    $form['rates'][] = [
      'add_rate' => [
        '#type' => 'submit',
        '#value' => $this->t('Add rate'),
        '#submit' => [[get_class($this), 'addRateSubmit']],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
      ],
    ];

    $form['territories'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Territory'),
        $this->t('Operations'),
      ],
      '#input' => FALSE,
      '#prefix' => '<p>' . $this->t('The tax type will be used if both the customer and the store belong to one of the territories.') . '</p>',
    ];
    foreach ($form_state->get('territories') as $index => $territory) {
      $territory_form = &$form['territories'][$index];
      $territory_form['territory'] = [
        '#type' => 'address_zone_territory',
        '#default_value' => $territory,
        '#required' => TRUE,
      ];
      if (array_key_exists('plus_county_limits', $this->configuration)) {
          $plus_county_limit = $this->configuration['plus_county_limits'][$index];
      }
      else {
          $plus_county_limit = 0;
      }
      $territory_form['limit_county'] = [
          '#type' => 'checkbox',
          '#title' => t('Limit by county (only apply if customer and store are in the same county)'),
          '#prefix' => '<tr><td>',
          '#suffix' => '</td></tr>',
          '#default_value' => $plus_county_limit,
      ];
      if (array_key_exists('plus_city_limits', $this->configuration)) {
          $plus_city_limit = $this->configuration['plus_city_limits'][$index];
      }
      else {
          $plus_city_limit = 0;
      }
      $territory_form['limit_city'] = [
          '#type' => 'checkbox',
          '#title' => t('Limit by city (only apply if customer and store are in the same city)'),
          '#prefix' => '<tr><td>',
          '#suffix' => '</td></tr>',
          '#default_value' => $plus_city_limit,
      ];
      $territory_form['remove'] = [
        '#type' => 'submit',
        '#name' => 'remove_territory' . $index,
        '#value' => $this->t('Remove'),
        '#limit_validation_errors' => [],
        '#submit' => [[get_class($this), 'removeTerritorySubmit']],
        '#territory_index' => $index,
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }
    $form['territories'][] = [
      'add_territory' => [
        '#type' => 'submit',
        '#value' => $this->t('Add territory'),
        '#submit' => [[get_class($this), 'addTerritorySubmit']],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
      ],
    ];

    return $form;
  }

  /**
   * Ajax callback for tax rate and zone territory operations.
   */
  public static function ajaxCallback(array $form, FormStateInterface $form_state) {
    return $form['configuration'];
  }

  /**
   * Submit callback for adding a new rate.
   */
  public static function addRateSubmit(array $form, FormStateInterface $form_state) {
    $rates = $form_state->get('rates');
    $rates[] = [];
    $form_state->set('rates', $rates);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for removing a rate.
   */
  public static function removeRateSubmit(array $form, FormStateInterface $form_state) {
    $rates = $form_state->get('rates');
    $index = $form_state->getTriggeringElement()['#rate_index'];
    unset($rates[$index]);
    $form_state->set('rates', $rates);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for adding a new territory.
   */
  public static function addTerritorySubmit(array $form, FormStateInterface $form_state) {
    $territories = $form_state->get('territories');
    $territories[] = [];
    $form_state->set('territories', $territories);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for removing a territory.
   */
  public static function removeTerritorySubmit(array $form, FormStateInterface $form_state) {
    $territories = $form_state->get('territories');
    $index = $form_state->getTriggeringElement()['#territory_index'];
    unset($territories[$index]);
    $form_state->set('territories', $territories);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    // Filter out the button rows.
    $values['rates'] = array_filter($values['rates'], function ($rate) {
      return !empty($rate) && !isset($rate['add_rate']);
    });
    $values['territories'] = array_filter($values['territories'], function ($territory) {
      return !empty($territory) && !isset($territory['add_territory']);
    });
    $form_state->setValue($form['#parents'], $values);

    if (empty($values['rates'])) {
      $form_state->setError($form['rates'], $this->t('Please add at least one rate.'));
    }
    if (empty($values['territories'])) {
      $form_state->setError($form['territories'], $this->t('Please add at least one territory.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['display_label'] = $values['display_label'];
      $this->configuration['round'] = $values['round'];
      $this->configuration['rates'] = [];
      foreach (array_filter($values['rates']) as $rate) {
          $ratestr = $rate['percentage'];
          $ratestr=$ratestr/100;
          settype($ratestr,"string");
        $this->configuration['rates'][] = [
          'id' => $rate['rate']['id'],
          'label' => $rate['rate']['label'],
            'percentage' => $ratestr,
        ];
      }
      $this->configuration['territories'] = [];
      $this->configuration['plus_county_limits'] = [];
      $this->configuration['plus_city_limits'] = [];
      foreach (array_filter($values['territories']) as $territory) {
        $this->configuration['territories'][] = $territory['territory'];
        $this->configuration['plus_county_limits'][] = $territory['limit_county'];
        $this->configuration['plus_city_limits'][] = $territory['limit_city'];
      }
    }
  }

  /**
   * Gets the available display labels.
   *
   * @return array
   *   The display labels, keyed by machine name.
   */
  protected function getDisplayLabels() {
    return [
      'tax' => $this->t('Tax'),
      'vat' => $this->t('VAT'),
      // Australia, New Zealand, Singapore, Hong Kong, India, Malaysia.
      'gst' => $this->t('GST'),
      // Japan.
      'consumption_tax' => $this->t('Consumption tax'),
    ];
  }

  /**
   * Gets the configured display label.
   *
   * @return string
   *   The configured display label.
   */
  protected function getDisplayLabel() {
    $display_labels = $this->getDisplayLabels();
    $display_label_id = $this->configuration['display_label'];
    if (isset($display_labels[$display_label_id])) {
      $display_label = $display_labels[$display_label_id];
    }
    else {
      $display_label = reset($display_labels);
    }
    return $display_label;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldRound() {
    return $this->configuration['round'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildZones() {
    $rates = $this->configuration['rates'];
    // The plugin doesn't support defining multiple percentages with own
    // start/end dates for UX reasons, so a start date is invented here.
    foreach ($rates as &$rate) {
      $rate['percentages'][] = [
        'number' => $rate['percentage'],
        'start_date' => '2000-01-01',
      ];
      unset($rate['percentage']);
    }
    // The first defined rate is assumed to be the default.
    $rates[0]['default'] = TRUE;

    $zones = [];
    $zones['default'] = new TaxZone([
      'id' => 'default',
      'label' => 'Default',
      'display_label' => $this->getDisplayLabel(),
      'territories' => $this->configuration['territories'],
      'rates' => $rates,
    ]);

    return $zones;
  }

  /**
   * Resolves the tax zones for the given order item and customer profile.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param \Drupal\profile\Entity\ProfileInterface $customer_profile
   *   The customer profile. Contains the address and tax number.
   *
   * @return \Drupal\commerce_tax\TaxZone[]
   *   The tax zones.
   */
  protected function resolveZones(OrderItemInterface $order_item, ProfileInterface $customer_profile) {
      $order = $order_item->getOrder()->id();
      $customer_address = $customer_profile->get('address')->first();
      $resolved_zones = [];
      foreach ($this->getZones() as $zone) {
          if ($zone->match($customer_address)) {
              $MatchResult = $this->MatchCounty($customer_address, $order);
              if($MatchResult == "yes")
              {
                  $resolved_zones[] = $zone;
              }
          }
      }
      if($MatchResult == "store_error")
      {
          drupal_set_message(t('Store address error'));
          $orderobj = $order_item->getOrder();
          if(!$orderobj->getData('address_error')) {
            $orderobj->setData('address_error', 'Store address is invalid.  Please alert the web site owner.');
            $orderobj->save();
          }
          throw new NeedsRedirectException(Url::fromRoute('commerce_checkout.form', [
              'commerce_order' => $order,
              'step' => 'order_information',
          ])->toString());
      }
      elseif($MatchResult == "cust_error")
      {
          drupal_set_message(t('Customer address error'));
          $orderobj = $order_item->getOrder();
          if(!$orderobj->getData('address_error')) {
            $orderobj->setData('address_error', 'Customer address is invalid.  Please enter proper address.');
            $orderobj->save();
          }
          throw new NeedsRedirectException(Url::fromRoute('commerce_checkout.form', [
              'commerce_order' => $order,
              'step' => 'order_information',
          ])->toString());        
      }
      else
      {

          return $resolved_zones;
      }
  }
  
  public function LookupValidAddress($street_address,$city,$state, $order) {
      $arrLookup = $this->APIService->LookupAddress($street_address,$city,$state);
      if ($arrLookup['valid'] == 1) {
          return $arrLookup['county'];
      }
      else {
          return 'error';
      }
  }
  
  public function MatchCounty(AddressInterface $customer_address, $order){
      /** @var \Drupal\commerce_store\Resolver\StoreResolverInterface $resolver */
      $resolver = \Drupal::service('commerce_store.default_store_resolver');
      $store_street_address = $resolver->resolve()->getAddress()->getAddressLine1();
      $store_city = $resolver->resolve()->getAddress()->getLocality();
      $store_state = $resolver->resolve()->getAddress()->getAdministrativeArea();
      $store_county = $this->LookupValidAddress($store_street_address,$store_city,$store_state, $order);
      $cust_street_address = $customer_address->getAddressLine1();
      $cust_state = $customer_address->getAdministrativeArea();
      $cust_city = $customer_address->getLocality();
      $cust_county = $this->LookupValidAddress($cust_street_address, $cust_city, $cust_state, $order);
      if($store_county == 'error')
      {
          return "store_error";
      }
      elseif($cust_county == 'error')
      {
          return "cust_error";
      }
      elseif($store_county == $cust_county) {
          return "yes";
      }
      else {
          return "no";
      }
  }
  
}
