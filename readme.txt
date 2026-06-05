=== Pikkoló for Woo ===
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: Shipping, WooCommerce, Iceland
Requires at least: 5.2
Tested up to: 6.7.0
Requires PHP: 7.3.5
WC requires at least: 3.8.1
WC tested up to: 9.4.1
Stable tag: 1.0.11

== Installation ==

1. Download the plugin [here](/archive/pikkolo-woocommerce-1.0.0.zip)
2. Unzip the archive into your `wp-content/plugins` directory.
3. Rename the plugin directory from **pikkolo-woocommerce-1.x** to **pikkolo-woocommerce**
4. Activate the plugin under WP-Admin → Plugins

OR

1. Go to WP-Admin → Plugins → Add New
2. Search for **Pikkoló**
3. Click the **Install Now** button
4. Activate the plugin under WP-Admin → Plugins

== Configuration ==

### Connect to the Pikkoló API

1. Navigate to  WooCommerce → Settings → Shipping → Pikkoló
2. Fill in your API key and Vendor ID
3. Click the **Save changes** button

== Changelog ==

= 1.0.11 =
* Check the initialization of the Pikkolo shipping method in the admin interface

= 1.0.10 =
* Save Pikkoló meta data at checkout

= 1.0.9 =
* The customer has to select a station in a explicit manner

= 1.0.8 =
* Robust processing of order on payment hook

= 1.0.7 =
* Fix for incomplete order processing and initialization of station selector 

= 1.0.6 =
* Enable detection of the delivery date from the order meta data 

= 1.0.5 =
* Documentation updates 

= 1.0.4 =
* More robust handling of the delivery date

= 1.0.3 =
* Added station name to the shipping method title

= 1.0.2 =
* Added logic to extract a delivery date from the checkout form. 

= 1.0.1 =
* Changes in response to the WP team review 

= 1.0.0 =
* First version

