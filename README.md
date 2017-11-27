# commerce_tax_plus
Drupal module that extends functionality of commerce 2.0 tax types.

Adds a new tax type plugin called "Custom Plus" as well as a new checkout pane plugin called "Payment Info Plus".

The Custom Plus tax type plugin allows for added options to limit by city and by county in this new tax type.

The Payment Info Plus checkout pane plugin

This module depends on the SmartyStreetsAPI module (https://www.drupal.org/project/smartystreetsapi) as well as Commerce 2.0 and its
Tax module (commerce_tax) along with its Checkout module (commerce_checkout) as well as their dependencies.  Here is a complete list:
SmartyStreetsAPI, Commerce, Address, Field, Entity, System, Datetime, Inline Entity Form, Views, Filter, User, Commerce Checkout, Commerce Order,
Commerce Price,Commerce Store,OptionsText, Entity Reference Revisions,Profile, State Machine, Commerce Cart, Commerce Product, Path, Commerce Tax

After installing Commerce Tax Plus Module, disable the default "Payment Information" checkout pane or there will be 2 prompts for billing Info
which will cause issues.
