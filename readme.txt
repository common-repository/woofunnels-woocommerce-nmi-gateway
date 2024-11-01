=== XL NMI Gateway for WooCommerce ===
Contributors: xlplugins, amans2k
Tags: WooCommerce, NMI, Payment Gateway, NMI Payment, NMI Gateway, Woocommerce Payment Gateway, XL plugins, PCI compliance
Requires at least: 5.0
Tested up to: 6.3.1
Stable tag: 2.3.1
Requires PHP: 7.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Receive credit card payments using NMI (Network Merchants) Gateway with subscription support. Built with love by XLPlugins.

== Description ==

XL NMI Gateway for WooCommerce is a free plugin by [XLPlugins](https://xlplugins.com/?utm_source=woocommerce-nmi-gateway&utm_campaign=wp-repo&utm_medium=readme&utm_term=XLPlugins).

It allows you to accept Visa, MasterCard, American Express, Discover, JCB, and Diners Club credit card directly on your store.

== XL NMI Gateway for WooCommerce Features ==

1. **Secure Credit Card Processing**
 Use Collect.js to process  payment information from your customers using a PCI compliant process.

2. **Tokenization or Customer Vaulting**
The plugin allows you to tokenize user cards which can be used for future processing. The only requirement is that your merchant account must have Customer Vaulting feature turned ON.

3. **Compatible with [UpStroke WooCommerce One Click Upsells](https://buildwoofunnels.com/woocommerce-one-click-upsells-upstroke/?utm_source=woocommerce-nmi-gateway&utm_campaign=wp-repo&utm_medium=readme&utm_term=UpStroke)**
The plugin is compatible with UpStroke and also powers up One Click Upsells.

4. **Compatible with the official WooCommerce Subscriptions plugin**
The gateway has built-in compatibility for WooCommerce Subscriptions.

5. **Pay via Saved Cards**
The returning users can pay via Saved Cards. It also has a unique feature to enable CVV/CSC verification for returning users. This feature allows merchants to use saved cards when their merchant account requires CVV for purchase.

6. **Add/ Remove Saved Cards**
Allow buyers to manage their cards from My Account > Payment Methods

7. **Refunds from WooCommerce Single Order Screen**
No need to login to your merchant account to process the refunds. Save time and process it, right from the WooCommerce Order screen. You can process full or partial refunds.

8. **Intuitive Settings**
An easy to use UI means that you can set it up and get it running in less than two minutes.


== Installation ==
1. Install "XL NMI Gateway for WooCommerce" Plugin.
2. Activate the Plugin.
3. Go to Woocommerce > Settings > Payments
4. Enable the Gateway and manage the credentials

== REQUIREMENTS ==

1. Valid SSL
2. Customer Vaulting (for using saved cards)

This project is supported and maintained by XLPlugins.

== SUPPORT ==

Feel free to create a [Support Ticket](https://wordpress.org/support/plugin/woofunnels-woocommerce-nmi-gateway) if you have any questions, suggestions or feedback. We are listening :)


== Screenshots ==

1. Manage Gateway Settings
2. Credit Card fields on Checkout page
3. Add Payment Methods from My-Account page
4. Payment Methods List on My Account page
5. Manage Customer Cards from Admin User Edit page
6. Change Payment Methods for Subscriptions


== Change log ==

= 2.3.0  (25-09-2023) =
* Fixed: Missing Javascript file from core module.

= 2.3.0  (22-09-2023) =
* Fixed: Card element getting destorted in case of multistep checkout form with FunnelKit checkout.
* Fixed: PHP error about AssertionError resolved.

= 2.2.0  (07-02-2023) =
* Added: Compatibility with WooCommerce v7.3.0
* Added: Compatibility with Wordpress v6.1.0
* Added: Compatibility with PHP v8.0.
* Fixed: Issue with payments from the saved card tokens in some cases.

= 2.1.0  (04-08-2021) =
* Added: Support for WP 5.8 and WooCommerce 5.6
* Fixed: Subscriptions created using woofunnels upsells were not getting charged correctly.
* Fixed: Issue with change payment method for subscriptions using my-account page.

= 2.0.2 (15-10-2020) =
* Added: Notice to show alert message regarding invalid tokenization key.
* Fixed: Issue with credit card fields not loading when a single gateway present
* Fixed: An issue when Collect.js is unable to load on checkout page.

= 2.0.1 (07-10-2020) =
* Fixed: Issue with charging mechanism when tokenization is enabled.
* Updated: Woofunnels core.
* Improved: Logging for debugging.

= 2.0.0 (01-10-2020) =
* Added: Collect JS for card tokenization to make the gateway PCI compliance.
* Added: Option to make transactions using Private API key along with legacy method (username and password)
* Added: AVS result from NMI response to order note.
* Added: Setting for sending gateway receipt.
* Fixed: Issue with renewals order when gateway is disabled.
* Fixed: Issue with subscription renewals when free trial subscriptions purchased in upsell offer in guest order.

= 1.8.8 (22-07-2020) =
* Fixed: Showing notice about undefined 'woofunnels' in submenu when no other woofunnels plugin installed.
* Fixed: updated woofunnels core.

= 1.8.7 (14-07-2020) =
* Fixed: Resolved conflicts with 'Cost of Goods' plugin due to mismatch version of the Skyverge libraries.
* Fixed: Avoid loading of .map js file showing console error in devtools on every page of the site.

= 1.8.6 (12-06-2020) =
* Fixed: Removed parameters 'state, city, zipcode' etc. Now it only needs minimum parameter for adding a new payment method.
* Fixed: Logging time set as UTC
* Fixed: Issue with overriding 'Place Order' button text.
* Added: New apply_filters for sending customer_receipt parameter as true by default.
* Fixed: Issue with renewals and duplicate transaction after a successful transaction.


= 1.8.5 (06-03-2020) =
* Fixed: Gateway description not showing below the gateway radio on front-end checkout page.
* Fixed: Missing detailed declined message on front end for end customer instead of generic error message when detailed declined message enabled from settings.
* Improved: Logging and error handling, PHPCS fixes.
* Tweak - Added filter to allow modification in final request data sent to NMI.
* New - Added action to add custom fields (like descriptor) on checkout page.

= 1.8.4 (13-12-2019) =
* Added: Subscriptions upsell support using UpStroke WooCommerce One Click Upsells plugin
* Fixed: Updated woofunnels core
* Fixed: PHP notice for regex compilation error
* Updated: Compatible with WordPress 5.3
* Updated: Compatible with WooCommerce 3.8

= 1.8.3 (29-05-2019) =
* Fixed: Updated woofunnels core

= 1.8.2 (05-03-2019) =
* Fixed: Notice for undefined 'exp_year' when adding payment method

= 1.8.1 (13-02-2019) =
* Fixed: 'Invalid username' issue when there are special characters in address fields

= 1.8 (07-02-2019) =
* Added: Notice when woocommerce subscriptions plugin is active and tokenization is not enabled.
* Fixed: Issue with subscriptions payments

= 1.7 (24-01-2019) =
* Improved: Improved and added some more logs
* Fixed: Removed the token edit options from user edit page by admin.

= 1.6 (11-12-2018) =
* Fixed: CVV/CSC handling in case of token payment

= 1.5 (06-12-2018) =
* Fixed: Issue with force tokenization

= 1.4 (04-12-2018) =
* Added: New filter hook to allow force tokenization

= 1.3 (27-11-2018) =
* Fix: Tokenization issue

= 1.2 (23.11.2018) =
* Fix: Issue with validate payment processor
* Fix: Authorization Failed issue

= 1.1 (2018-10-12) =
* Fix: Issue with card vaulting
* Fix: Authorizing the card with order total.

= 1.0.0 (2018-10-11) =
* Public Release
