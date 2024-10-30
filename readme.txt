=== Huxloe Shipping ===
Contributors: huxloe
Donate link: https://wordpressfoundation.org/donate/
Tags: WooCommerce, shipping, generate label, consignment number, huxloe
Requires at least: 5.0
Tested up to: 6.5.2
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Generate labels on the Huxloe 360 Shipping platform.

== Description ==

Huxloe Shipping for Woocommerce is a plugin that integrates with the Huxloe 360 Shipping platform to generate shipping labels.

= Features =
* Generate Label: Create shipping labels for orders.
* Generate Consignment Number: Automatically generate a Consignment Number for each order.

Important: This plugin sends order data to an external service for label generation. By using this plugin, you agree to the terms and privacy policies of the external service.

= External API Endpoints =
This plugin interacts with the following external domains:
* `https://label.svc.huxloe360.com` - Used for generating shipping labels and consignment numbers.

Documentation for External Service
Service Link: <a rel="noreferrer" target="_new" href="https://huxloe.com/">Huxloe 360 Shipping Platform</a>
Terms of Use: <a rel="noreferrer" target="_new" href="https://huxloe.com/cookie-policy/">Huxloe 360 Cookie Policy</a>
Privacy Policy: <a rel="noreferrer" target="_new" href="https://huxloe.com/privacy/">Huxloe 360 Privacy Policy</a>

== Installation ==

1. Upload the `huxloe-shipping` folder to the `/wp-content/plugins/` directory or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the Huxloe Shipping page in your WordPress admin panel or you can click on settings link of Huxloe Shipping settings in wordpres plugin list.
4. Go to the WooCommerce Shipping tab and configure the Huxloe Shipping integration.
5. In the Huxloe Shipping Integration tab, enter the API Key, Tenant ID, and User ID in the input fields, which are mandatory to connect with the Huxloe Shipping API.
6. For exporting orders, you will need to assign additional product information such as HS Code, SKU, Weight, Dimensions, and Country of Manufacturer inside the individual product data on the product admin detail page.

== Frequently Asked Questions ==

1. What does this plugin do?
Answer: This plugin generates labels on the Huxloe 360 Shipping platform.

2. Do I need an account with Huxloe to use this plugin?
Answer: Yes, you will require an account with Huxloe. Please contact your account manager for more information.

3. Will this plugin generate PDF labels?
Answer: No, you will need to log in to your Huxloe 360 platform to download your labels.

4. Which shipping carriers are supported?
Answer: We support the same shipping carriers as per those that are active on your account.

== Screenshots ==

1. This is how the Huxloe Shipping settings page looks.
   ![Huxloe Shipping Settings](screenshot-1.png)

== Upgrade Notice ==

= 1.0.0 =
* Initial release

== Changelog ==

= 1.0.0 =
* Initial release
