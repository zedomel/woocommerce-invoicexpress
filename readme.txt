=== WooCommerce-InvoiceXpress ===
Contributors: nunomorgadinho, aaires, widgilabs
Tags: woocommerce, invoice, invoicing, invoicexpress, ecommerce
Requires at least: 3.0
Tested up to: 4.4
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The InvoiceXpress Extension for WooCommerce allows you to invoice your clients via InvoiceXpress.

== Description ==

The InvoiceXpress Extension for WooCommerce allows you to invoice your clients via InvoiceXpress based on the orders you take on your WooCommerce store. When an order comes in the extension checks if the customer exists in your InvoiceXpress account and creates an invoice that can be e-mailed or archived.

> <strong>Bug Reports</strong><br>
> Bug reports for are [welcomed on GitHub](https://github.com/widgilabs/woocommerce-invoicexpress). Please note GitHub is _not_ a support forum and issues that aren't properly qualified as bugs will be closed.

== Installation ==

1. Place 'woocommerce-invoicexpress' folder in your plugins directory.
1. Activate the plugin.
1. Visit the InvoiceXpress settings under the WooCommerce menu and configure the required settings.

== Screenshots ==

1. WooCommerce-InvoiceXpress Settings.

== Changelog ==

= 0.12 =
* Fix discount calculation
* Fix company name field
* Skip invoice creation if order total is zero

= 0.11 =
* Refactor the plugin

= 0.6 =
* Update the API endpoint to .com
* Include due date on invoice since it now seems to be mandatory
* Remove inicial XML block which isn't needed

= 0.5 =
* Remove class-woothemes-plugin-updater.php from the plugin

= 0.4 =
* Small fixes

= 0.3 =
* Use country name instead of country code

= 0.2 =
* Several fixes and general cleanup

= 0.1 =
* Initial release.
