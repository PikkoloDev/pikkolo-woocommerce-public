<?php
/*
Plugin Name: Pikkoló  
Plugin URI: https://pikkolo.is/
Description: Shipping method
Version: 1.0.0
Author: Pikkoló ehf.
Text Domain: pikkolois
Domain Path: /languages
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Check if WooCommerce is active
 */
if (
    in_array(
        'woocommerce/woocommerce.php',
        apply_filters(
            'active_plugins',
            get_option('active_plugins')
        )
    )
) {
    function pikkolo_shipping_method_init()
    {
        if (!class_exists('Pikkolo_Shipping_Method')) {
            class Pikkolo_Shipping_Method extends WC_Shipping_Method
            {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct()
                {
                    $this->id                 = 'pikkolois'; // Id for your shipping method. Should be unique.

                    // TODO: Find out why these strings are not added to .pot file
                    $this->title              = __('Pikkoló - Self service station'); // This can be added as an setting but for this example its forced.
                    $this->method_title       = __('Pikkoló');  // Title shown in admin
                    $this->method_description = __('Pikkoló provides self-service stations for groceries in your neighbourhood'); // Description shown in admin

                    $this->enabled            = $this->get_option('enabled');
                    $this->cost               = $this->get_option('cost');
                    $this->free_shipping_minimum = $this->get_option('free_shipping_minimum');

                    // Debugging
                    $this->debug              = $this->get_option('debug');
                    $this->test_mode          = $this->get_option('test');

                    // Pikkoló
                    $this->api_key              = $this->get_option('apikey');
                    $this->api_key_test          = $this->get_option('apikey_test');
                    $this->vendor_id          = $this->get_option('vendorid');
                    $this->vendor_id_test      = $this->get_option('vendorid_test');
                    $this->api_url            = null;

                    $this->init();
                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init()
                {
                    // Load the settings API
                    $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
                    $this->init_settings(); // This is part of the settings API. Loads settings you previously init.
                    $this->init_api_url(); // Create API url from given settings
                    $this->pikkolo_add_metadata_to_all_products(); // Add custom metadata to all products

                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                }

                /**
                 * Initialise Gateway Settings Form Fields
                 */
                function init_form_fields()
                {

                    $this->form_fields = array(
                        'connect_settings' => array(
                            'title'       => __('Connect to Pikkoló', 'pikkolois'),
                            'type'        => 'title',
                            'description' => __('Enter your API key and vendor ID to connect to Pikkoló', 'pikkolois'),
                        ),
                        'enabled' => array(
                            'title' => __(
                                'Enable/Disable',
                                'pikkolois'
                            ),
                            'type' => 'checkbox',
                            'label' => __(
                                'Enable',
                                'pikkolois'
                            ),
                            'default' => 'no'
                        ),
                        'apikey' => array(
                            'title' => __(
                                'API Key',
                                'pikkolois'
                            ),
                            'type' => 'text',
                            'description' => __(
                                'The API key from api.pikkolo.is',
                                'pikkolois'
                            ),
                            'default' => __(
                                '',
                                'pikkolois'
                            ),
                            'desc_tip' => true,
                        ),
                        'apikey_test' => array(
                            'title' => __(
                                'API Key (test)',
                                'pikkolois'
                            ),
                            'type' => 'text',
                            'description' => __(
                                'The API key from api-staging.pikkolo.is',
                                'pikkolois'
                            ),
                            'default' => __(
                                '',
                                'pikkolois'
                            ),
                            'desc_tip' => true,
                        ),
                        'vendorid' => array(
                            'title' => __(
                                'Vendor ID',
                                'pikkolois'
                            ),
                            'type' => 'text',
                            'description' => __(
                                'The vendor ID from api.pikkolo.is',
                                'pikkolois'
                            ),
                            'default' => __(
                                '',
                                'pikkolois'
                            ),
                            'desc_tip' => true,
                        ),
                        'vendorid_test' => array(
                            'title' => __(
                                'Vendor ID (test)',
                                'pikkolois'
                            ),
                            'type' => 'text',
                            'description' => __(
                                'The vendor ID from api-staging.pikkolo.is',
                                'pikkolois'
                            ),
                            'default' => __(
                                '',
                                'pikkolois'
                            ),
                            'desc_tip' => true,
                        ),
                        'delivery_settings' => array(
                            'title'       => __('Delivery rate', 'pikkolois'),
                            'type'        => 'title',
                            'description' => __('Enter the delivery rate for Pikkoló', 'pikkolois'),
                        ),
                        'cost' => array(
                            'title' => __(
                                'Base rate (ISK)',
                                'pikkolois'
                            ),
                            'type' => 'text',
                            'description' => __(
                                'The base rate displayed to the customer',
                                'pikkolois'
                            ),
                            'default' => __(
                                '',
                                'pikkolois'
                            ),
                            'desc_tip' => true
                        ),
                        'free_shipping_minimum' => array(
                            'title' => __(
                                'Minimum for free delivery (ISK)',
                                'pikkolois'
                            ),
                            'type' => 'text',
                            'description' => __(
                                'The minimum order amount for free delivery. Leave blank for no minimum',
                                'pikkolois'
                            ),
                            'default' => __(
                                '',
                                'pikkolois'
                            ),
                            'desc_tip' => true
                        ),
                        'debug_settings' => array(
                            'title'       => __('Debugging & Testing', 'pikkolois'),
                            'type'        => 'title',
                            'description' => __('Options related to debugging and testing the Pikkoló gateway.', 'pikkolois'),
                        ),
                        'debug' => array(
                            'title'       => __(
                                'Debug mode',
                                'pikkolois'
                            ),
                            'type'        => 'checkbox',
                            'label'       => __(
                                'Enable debugging',
                                'pikkolois'
                            ),
                            'default'     => 'yes',
                            'description' => sprintf(
                                __(
                                    'See debugging logs %s.',
                                    'pikkolois'
                                ),
                                '<a href="' . admin_url('admin.php?page=wc-status&tab=logs') . '">' . __('here', 'pikkolois') . '</a>'
                            )
                        ),
                        'test' => array(
                            'title' => __(
                                'Test mode (Staging)',
                                'pikkolois'
                            ),
                            'type' => 'checkbox',
                            'label' => __(
                                'Enable test mode',
                                'pikkolois'
                            ),
                            'description' => __(
                                'Do requests against test environment',
                                'pikkolois'
                            ),
                            'default' => 'yes',
                            'desc_tip' => true
                        ),
                    );
                }
                /**
                 * Set API url
                 * 
                 * @return void
                 */
                function init_api_url()
                {
                    if ($this->test_mode == 'no') {
                        $this->api_url = 'https://api.pikkolo.is';
                    } else {
                        $this->api_url = 'https://api-staging.pikkolo.is';
                    }
                }

                /**
                 * Add Pikkoló metadata to all products at initalization
                 * 
                 * @return void
                 */
                function pikkolo_add_metadata_to_all_products()
                {
                    $args = array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,  // Fetch all products,
                        'post_status'    => 'any' // Get products of all statuses
                    );

                    // $log = new WC_Logger();
                    // $log->add('pikkolois', 'Adding meta data to all products');

                    $all_products = get_posts($args);

                    // $log->add('pikkolois', 'Showing ' . count($all_products) . ' products');

                    foreach ($all_products as $product) {
                        $product_id = $product->ID;

                        // $log->add('pikkolois', 'Checking product ' . $product_id);
                        // $log->add('pikkolois', wc_get_product($product->ID));

                        // if meta data is not set
                        if (!get_post_meta($product_id, 'pikkolo_frozen', true)) {
                            // $log->add('pikkolois', 'Adding pikkolo_frozen meta data to product ' . $product_id);
                            $meta_key_1 = 'pikkolo_frozen';
                            $meta_value_1 = 'false';
                            update_post_meta($product_id, $meta_key_1, $meta_value_1);
                        }
                        if (!get_post_meta($product_id, 'pikkolo_age_restriction', true)) {
                            // $log->add('pikkolois', 'Adding pikkolo_age_restriction meta data to product ' . $product_id);
                            $meta_key_2 = 'pikkolo_age_restriction';
                            $meta_value_2 = 'none';
                            update_post_meta($product_id, $meta_key_2, $meta_value_2);
                        }
                    }
                }

                /**
                 * Get Pikkoló environment
                 * 
                 * @access public
                 * @return string @env
                 */
                public function get_env()
                {
                    if ($this->test_mode == 'no') {
                        return 'production';
                    } else {
                        return 'staging';
                    }
                }
                /**
                 * calculate_shipping function.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping($package = array())
                {
                    $cart_subtotal = WC()->cart->subtotal;

                    // Check if the cart's subtotal exceeds the free shipping minimum
                    if (!empty($this->free_shipping_minimum) && $cart_subtotal >= $this->free_shipping_minimum) {
                        $shipping_cost = 0;
                    } else {
                        $shipping_cost = $this->cost;
                    }

                    $rate = array(
                        'label' => $this->title,
                        'cost' =>  $shipping_cost,
                        'taxes' => 'false',
                        'calc_tax' => 'per_order',
                    );

                    // Register the rate
                    $this->add_rate($rate);
                }
            }
        }
    }
    add_action('woocommerce_shipping_init', 'pikkolo_shipping_method_init');

    /**
     * Load plugin textdomain.
     * 
     * @return void 
     */
    function pikkolo_load_textdomain()
    {
        load_plugin_textdomain('pikkolois', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    add_action('init', 'pikkolo_load_textdomain');

    /**
     * Allow admin to edit Pikkoló custom meta data on product page
     * 
     * @return void
     */
    function pikkolo_add_vendor_custom_field()
    {
        global $post;

        $product = wc_get_product($post->ID);

        $frozen_meta_value = $product->get_meta('pikkolo_frozen');
        $age_restriction_meta_value = $product->get_meta('pikkolo_age_restriction');

        echo '<p style="font-weight: bold;">' . esc_html__('Pikkoló Custom Fields', 'woocommerce') . '</p>';

        woocommerce_wp_select(
            array(
                'id'          => '_pikkolo_frozen',
                'label'       => __('Is frozen', 'woocommerce'),
                'options'     => array(
                    'true' => __('Yes', 'woocommerce'),
                    'false'  => __('No', 'woocommerce')
                ),
                'value'       => $frozen_meta_value ? $frozen_meta_value : 'false'
            )
        );

        woocommerce_wp_select(
            array(
                'id'          => '_pikkolo_age_restriction',
                'label'       => __('Age restriction', 'woocommerce'),
                'options'     => array(
                    'none' => __('None', 'woocommerce'),
                    '18' => __('18', 'woocommerce'),
                    '20'  => __('20', 'woocommerce')
                ),
                'value'       => $age_restriction_meta_value ? $age_restriction_meta_value : 'none'
            )
        );
    }
    add_action('woocommerce_product_options_general_product_data', 'pikkolo_add_vendor_custom_field');

    /**
     * Save Pikkoló custom meta data
     * 
     * @param int $post_id
     * @return void
     */
    function pikkolo_save_vendor_custom_field($post_id)
    {
        // Check nonce for security
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data')) {
            return;
        }

        // Check if the current user has permission to edit the product
        if (!current_user_can('edit_product', $post_id)) {
            return;
        }

        // Save custom fields
        $frozen_value = isset($_POST['_pikkolo_frozen']) ? sanitize_text_field($_POST['_pikkolo_frozen']) : 'false';
        update_post_meta($post_id, 'pikkolo_frozen', $frozen_value);

        $age_restriction_value = isset($_POST['_pikkolo_age_restriction']) ? sanitize_text_field($_POST['_pikkolo_age_restriction']) : 'none';
        update_post_meta($post_id, 'pikkolo_age_restriction', $age_restriction_value);
    }
    add_action('woocommerce_process_product_meta', 'pikkolo_save_vendor_custom_field');

    /**
     * Add Pikkoló custom meta data to product bulk edit
     * 
     * @return void
     */
    function pikkolo_custom_bulk_edit_fields()
    {
?>
        <label>
            <span class="title"><?php esc_html_e('Pikkoló: Is frozen', 'woocommerce'); ?></span>
            <select name="_pikkolo_frozen_bulk">
                <option value="no-change"><?php esc_html_e('— No change —', 'woocommerce'); ?></option>
                <option value="true"><?php esc_html_e('True', 'woocommerce'); ?></option>
                <option value="false"><?php esc_html_e('False', 'woocommerce'); ?></option>
            </select>
        </label>

        <label>
            <span class="title"><?php esc_html_e('Pikkoló: Age restriction', 'woocommerce'); ?></span>
            <select name="_pikkolo_age_restriction_bulk">
                <option value="no-change"><?php esc_html_e('— No change —', 'woocommerce'); ?></option>
                <option value="none"><?php esc_html_e('None', 'woocommerce'); ?></option>
                <option value="18"><?php esc_html_e('18', 'woocommerce'); ?></option>
                <option value="20"><?php esc_html_e('20', 'woocommerce'); ?></option>
            </select>
        </label>
<?php
    }
    add_action('woocommerce_product_bulk_edit_start', 'pikkolo_custom_bulk_edit_fields');

    /**
     * Save Pikkoló custom meta data from product bulk edit
     * 
     * @param int $product
     * @return void
     */
    function pikkolo_save_bulk_edit_fields($product)
    {
        $product_id = method_exists($product, 'get_id') ? $product->get_id() : $product->id;

        if (isset($_REQUEST['_pikkolo_frozen_bulk']) && $_REQUEST['_pikkolo_frozen_bulk'] !== 'no-change') {
            update_post_meta($product_id, 'pikkolo_frozen', wc_clean($_REQUEST['_pikkolo_frozen_bulk']));
        }

        if (isset($_REQUEST['_pikkolo_age_restriction_bulk']) && $_REQUEST['_pikkolo_age_restriction_bulk'] !== 'no-change') {
            update_post_meta($product_id, 'pikkolo_age_restriction', wc_clean($_REQUEST['_pikkolo_age_restriction_bulk']));
        }
    }
    add_action('woocommerce_product_bulk_edit_save', 'pikkolo_save_bulk_edit_fields');

    /**
     * Adds Pikkoló as a shipping method.
     * 
     * @param array $methods The array of shipping methods.
     * @return array Updated array of shipping methods.
     */
    function pikkolo_add_shipping_method($methods)
    {
        if (class_exists('Pikkolo_Shipping_Method')) {
            $methods['pikkolo_shipping_method'] = 'Pikkolo_Shipping_Method';
        }

        return $methods;
    }
    add_filter('woocommerce_shipping_methods', 'pikkolo_add_shipping_method');

    /**
     * Limits Pikkoló shipping method to Iceland.
     * 
     * @param array $rates The array of shipping rates.
     * @param array $package The package details.
     * @return array Updated array of shipping rates.
     */
    function pikkolo_hide_when_not_iceland($rates, $package)
    {
        $shipping_country = WC()->customer->get_shipping_country();

        if (isset($rates['pikkolois']) && $shipping_country !== 'IS') {
            unset($rates['pikkolois']);
        }
        return $rates;
    }
    add_filter('woocommerce_package_rates', 'pikkolo_hide_when_not_iceland', 10, 2);

    /**
     * Adds additional information about the Pikkoló shipping method.
     * 
     * @param string $label The shipping method label.
     * @param object $method The shipping method object.
     * @return string Updated shipping method label.
     */
    function pikkolo_more_info($label, $method)
    {
        if ($method->id == 'pikkolois') {
            $label = __('<span style="width:100%">', 'pikkolois') . $label;
            $label .= sprintf(__(
                '<span data-tooltip="bla" data-flow="top" style="float:right" >
                    <img src="%s" style="max-height:20px;max-width:20px;">
                </span>',
                'pikkolois'
            ), plugins_url('pikkolo/assets/img/info.png', __DIR__));
            $label .= '</span>';
        }
        return $label;
    }
    add_filter('woocommerce_cart_shipping_method_full_label', 'pikkolo_more_info', 10, 2);

    /**
     * Adds a modal button for choosing a location.
     * 
     * @param WC_Shipping_Rate $shipping_rate The shipping rate object.
     * @param int $index The index of the shipping rate.
     * @return void
     */
    function pikkolo_choose_location(WC_Shipping_Rate $shipping_rate, $index)
    {
        if (function_exists('is_checkout') && is_checkout() && function_exists('WC')) {
            if (
                ($shipping_rate->get_method_id() !== "pikkolois") ||
                (!in_array($shipping_rate->get_id(), WC()->session->get('chosen_shipping_methods')))
            ) {
                return;
            }

            // Add modal button
            printf(
                '<div class="PikkoloDeliveryMethodAction">
					<span id="pikkolo_chosen_station"></span><br>
					<button id="pikkolo_choose_another" class="button" aria-label="%s">%s</button>
				</div>',
                esc_attr__('Choose a Pikkolo station for delivery', 'pikkolois'),
                esc_html__('Choose a station', 'pikkolois')
            );
        }
    }
    add_action('woocommerce_after_shipping_rate', 'pikkolo_choose_location', 10, 2);

    /**
     * Adds necessary scripts and styles for Pikkoló functionality.
     * 
     * @return void
     */
    function pikkolo_add_scripts()
    {
        if (function_exists('is_checkout') && is_checkout() && function_exists('WC')) {

            $pikkolo = new Pikkolo_Shipping_Method();
            $environment = $pikkolo->get_env();
            $vendor_id = $environment == 'production' ? $pikkolo->vendor_id : $pikkolo->vendor_id_test;

            $sdk_url = $pikkolo->api_url . '/sdk/pikkolo-sdk.min.js?environment=' . $environment . '&vendorId=' . $vendor_id;

            wp_enqueue_script(
                'pikkolo-sdk',
                $sdk_url,
                array('jquery')
            );
            wp_enqueue_script(
                'pikkolo-js',
                esc_url(plugins_url('pikkolo/assets/js/pikkolo.js', __DIR__)),
                array('jquery')
            );

            $customer = WC()->cart->get_customer();

            wp_localize_script(
                'pikkolo-js',
                '_pikkolo',
                [
                    'country' => $customer->get_shipping_country(),
                    'city' => $customer->get_shipping_city() ? $customer->get_shipping_city() : 'Reykjavík',
                    'postcode' => $customer->get_shipping_postcode() ? $customer->get_shipping_postcode() : '101',
                    'address' => $customer->get_shipping_address(),
                    'email' => $customer->get_billing_email(),
                    'phone' => $customer->get_shipping_phone(),
                    'i18n' => [
                        'noChosenStation' => __('No station chosen', 'pikkolois'),
                        'loadingStations' => __('Loading stations', 'pikkolois'),
                        'chooseStation' => __('Choose a station', 'pikkolois'),
                        'noStationAvailable' => __('No station available', 'pikkolois'),
                        'seeOnMap' => __('See on map', 'pikkolois'),
                    ]
                ]
            );
        }
        wp_enqueue_style(
            'pikkolo-css',
            esc_url(plugins_url('pikkolo/assets/css/tooltip.css', __DIR__))
        );
    }
    add_action('wp_enqueue_scripts', 'pikkolo_add_scripts', 10, 1);

    /**
     * Validates the selected Pikkoló station location during checkout.
     * 
     * @param array $data An array of checkout data.
     * @param WP_Error $errors The error object to add validation errors.
     * @return void
     */
    function pikkolo_validate_location(array $data, WP_Error $errors)
    {
        // Check if a shipping method is chosen
        $shipping_methods = $data['shipping_method'];

        // If Pikkoló is not the chosen shipping method, return early
        if (!$shipping_methods || !in_array('pikkolois', $shipping_methods)) {
            return;
        }

        // Check if necessary cookies are set (indicating a Pikkoló location selection)
        if (!isset($_COOKIE['pikkolo_station_id'], $_COOKIE['pikkolo_delivery_time_id'])) {
            // Add an error indicating the need to select a Pikkoló location
            $errors->add(
                'shipping',
                __('Please select a location for Pikkoló', 'pikkolois')
            );
        }
    }
    add_action('woocommerce_after_checkout_validation', 'pikkolo_validate_location', 10, 2);

    /**
     * Books a Pikkólo slot when a order is processed.
     * 
     * @param int $order_id The ID of the processed order.
     * @return void
     */
    function pikkolo_process_order($order_id)
    {
        $pikkolo = new Pikkolo_Shipping_Method();
        $log = new WC_Logger();

        $order = wc_get_order($order_id);
        if ($order->get_shipping_method() !== $pikkolo->title) {
            // Pikkoló is not the chosen shipping method
            return;
        }

        $products_data = pikkolo_get_products_data($order);
        $post_fields = pikkolo_prepare_post_fields($order, $products_data);

        $result = pikkolo_send_order_to_api($pikkolo, $post_fields);

        if (!$result || $result['httpcode'] >= 300) {
            pikkolo_handle_api_error($result, $order, $pikkolo, $log);
        } else {
            pikkolo_handle_api_success($result, $order, $pikkolo, $log);
        }

        $order->save();
    }
    add_action('woocommerce_checkout_order_processed', 'pikkolo_process_order', 10, 1);

    /**
     * Gets the necessary data from the order's products.
     * 
     * @param WC_Order $order The order object.
     * @return array An array of product data.
     */
    function pikkolo_get_products_data($order)
    {
        $products = $order->get_items();

        // Initializing variables
        $refrigerated_count = 0;
        $frozen_count = 0;
        $age_restriction_value = 0;
        $itemLinesRefrigerated = [];
        $itemLinesFrozen = [];

        foreach ($products as $product) {
            $product_id = $product->get_product_id();
            $quantity = $product->get_quantity();
            $frozen = get_post_meta($product_id, 'pikkolo_frozen', true);
            $age_restriction = get_post_meta($product_id, 'pikkolo_age_restriction', true);

            $wc_product = $product->get_product();
            $weight = $wc_product ? $wc_product->get_weight() : 0;
            $dimensions = $wc_product ? $wc_product->get_dimensions() : array();

            if ($age_restriction !== 'none' && $age_restriction > $age_restriction_value) {
                $age_restriction_value = $age_restriction;
            }

            for ($i = 0; $i < $quantity; $i++) {
                if ($frozen == 'true') {
                    $frozen_count++;
                    $itemLinesFrozen[] = [
                        "type" => "Frozen",
                        "description" => $product->get_name(),
                        'quantity' => $quantity,
                        'weight' => $weight,
                        'dimensions' => $dimensions,
                    ];
                } else {
                    $refrigerated_count++;
                    $itemLinesRefrigerated[] = [
                        "type" => "Refrigerated",
                        "description" => $product->get_name(),
                        'quantity' => $quantity,
                        'weight' => $weight,
                        'dimensions' => $dimensions,
                    ];
                }
            }
        }

        $items = [];
        if ($refrigerated_count > 0 && $itemLinesRefrigerated !== []) {
            $items[] = [
                "description" => "WooCommerce Refrigerated items",
                "type" => "Refrigerated",
                "itemLines" => $itemLinesRefrigerated
            ];
        }
        if ($frozen_count > 0 && $itemLinesFrozen !== []) {
            $items[] = [
                "description" => "WooCommerce Frozen items",
                "type" => "Frozen",
                "itemLines" => $itemLinesFrozen
            ];
        }

        return [
            'items' => $items,
            'refrigerated_count' => $refrigerated_count,
            'frozen_count' => $frozen_count,
            'age_restriction_value' => $age_restriction_value
        ];
    }

    function pikkolo_prepare_post_fields($order, $product_data)
    {
        // Get cookies set in pikkolo.js
        $station_id = sanitize_text_field($_COOKIE['pikkolo_station_id']);
        // $delivery_time_id = sanitize_text_field($_COOKIE['pikkolo_delivery_time_id']);

        $vendor_order_id = strval($order->get_id());
        $customer_phone = strval($order->get_billing_phone());
        $customer_email = strval($order->get_billing_email());
        $customer_name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();

        return [
            'vendorOrderId' => $vendor_order_id,
            'stationId' => $station_id,
            //'deliveryTimeId' => $delivery_time_id,
            'customerPhone' => $customer_phone,
            'customerEmail' => $customer_email,
            'customerName' => $customer_name,
            'isSubscription' => false,
            'needsPickupAuthentication' => $product_data['age_restriction_value'] > 0 ? $product_data['age_restriction_value'] : false,
            'nrOfRefrigeratedItems' => $product_data['refrigerated_count'],
            'nrOfFreezerItems' => $product_data['frozen_count'],
            'items' => $product_data['items'],
        ];
    }

    function pikkolo_send_order_to_api($pikkolo, $post_fields)
    {
        $process_url = $pikkolo->api_url . '/api/public/v1/orders';

        $response = wp_remote_post($process_url, array(
            'method' => 'POST',
            'headers' => array(
                'X-Api-Key' => ($pikkolo->get_env() == 'production' ? $pikkolo->api_key : $pikkolo->api_key_test),
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($post_fields)
        ));

        if (is_wp_error($response)) {
            return [
                'json' => wp_json_decode("{\"error\":\"" . $response->get_error_message() . "\"}"),
                'httpcode' => 500 // Generic error code, adjust as necessary
            ];
        }

        $httpcode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = wp_json_decode($body);

        return ['json' => $json, 'httpcode' => $httpcode];
    }

    function pikkolo_handle_api_error($result, $order, $pikkolo, $log)
    {
        $error =
            "Error when attempting to send order to Pikkoló API. Please contact Pikkoló technical support if in need of assistance.\n
			Status code: " . $result['httpcode'] . "\n
			Error message: " . (empty($result['json']) ? "No error message" : $result['json']->error);

        $order->update_meta_data('pikkolo_process_error', $error); // To display on admin page

        if ($pikkolo->debug == 'yes') {
            $log->add('pikkolois', $error);
        }

        pikkolo_send_error_to_logtail($error, $pikkolo, $log);
    }

    function pikkolo_handle_api_success($result, $order, $pikkolo, $log)
    {
        $log->add('pikkolois', 'Order ID: ' . $result['json']->data->id);
        $log->add('pikkolois', 'Environment: ' . $pikkolo->get_env());

        $order->update_meta_data('pikkolo_order_id', $result['json']->data->id); // To be able to cancel
        $order->update_meta_data('pikkolo_environment', $pikkolo->get_env()); // To prevent cancellation errors if test mode settings are changed after

        if ($pikkolo->debug == 'yes') {
            $log->add('pikkolois', 'Order ' . $result['json']->data->vendorOrderId . ' successfully sent to Pikkoló API with ID ' . $result['json']->data->id);
        }
    }

    function pikkolo_send_error_to_logtail($error, $pikkolo, $log)
    {
        $url = $pikkolo->api_url . '/api/public/v1/log';

        $response = wp_remote_post($url, array(
            'method' => 'POST',
            'headers' => array(
                'X-Api-Key' => ($pikkolo->get_env() == 'production' ? $pikkolo->api_key : $pikkolo->api_key_test),
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode(array(
                'level' => 'debug',
                'message' => $error
            ))
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $log->add('pikkolois', "Something went wrong when sending information to Pikkoló: " . $error_message);
        } else {
            $httpcode = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response); // Convert to JSON to get the error message
            $json = wp_json_decode($body);

            if ($httpcode >= 300) {
                $log->add('pikkolois', "Something went wrong when sending information to Pikkoló: " . $json->error);
            } else {
                $log->add('pikkolois', "Error information successfully sent to Pikkoló");
            }
        }
    }


    /**
     * Adds an success or error message to the admin page depending on if the order was processed succesfully or not in Pikkoló.
     * 
     * @param int $item_id The item ID.
     * @param WC_Order_Item_Product $item The order item object.
     * @param WC_Product $product The product object.
     * @return void
     */
    function pikkolo_admin_notifications($order)
    {
        $pikkolo = new Pikkolo_Shipping_Method();
        if (
            is_admin() &&
            $order &&
            ($order->get_shipping_method() == $pikkolo->title)
        ) {
            if ($order->get_meta('pikkolo_process_error')) {
                add_action(
                    'admin_notices',
                    printf(
                        '<div class="%1$s"><p>%2$s</p></div>',
                        esc_attr('notice notice-error'),
                        esc_html(
                            $order->get_meta('pikkolo_process_error')
                        )
                    )
                );
            } else {
                if ($order->get_meta('pikkolo_environment') == 'production') {
                    $api_url = 'https://api.pikkolo.is';
                } else {
                    $api_url = 'https://api-staging.pikkolo.is';
                }

                if ($order->get_status() == 'cancelled') {
                    add_action(
                        'admin_notices',
                        printf(
                            '<div class="%1$s"><p>Remember to cancel order %2$s from Pikkoló dashboard.<br>Log into <a href="%3$s" target="_blank">%3$s</a> to view the order details.</p></div>',
                            esc_attr('notice notice-success'),
                            esc_html(get_post_meta($order->get_id(), 'pikkolo_order_id', true)),
                            esc_url($api_url)
                        )
                    );
                } else {
                    add_action(
                        'admin_notices',
                        printf(
                            '<div class="%1$s"><p>Order successfully sent to Pikkoló API with ID %2$s.<br>Log into <a href="%3$s" target="_blank">%3$s</a> to view the order details.</p></div>',
                            esc_attr('notice notice-success'),
                            esc_html(get_post_meta($order->get_id(), 'pikkolo_order_id', true)),
                            esc_url($api_url)
                        )
                    );
                }
            }
        }
    }
    add_action('woocommerce_admin_order_data_after_order_details', 'pikkolo_admin_notifications');

    // /**
    //  * Cancels a Pikkólo slot when an order is set to cancelled.
    //  * 
    //  * @param int $order_id The ID of the order to be cancelled.
    //  * @return void
    //  */
    // function pikkolo_cancel_order($order_id) {
    // 	$pikkolo = new Pikkolo_Shipping_Method();
    // 	$log = new WC_Logger();

    // 	$order = wc_get_order($order_id);

    // 	if (!$order || $order->get_shipping_method() !== $pikkolo->title) {
    // 		return;
    // 	}

    // 	$environment = $order->get_meta('pikkolo_environment');
    // 	$api_url = pikkolo_get_cancel_api_url($environment);
    // 	$cancel_url = $api_url . '/api/public/v1/orders/' . $order->get_meta('pikkolo_order_id') . '/cancel';

    // 	$result = pikkolo_send_cancel_request($cancel_url, ($environment == 'production' ? $pikkolo->api_key : $pikkolo->api_key_test));

    // 	if (!$result || $result['httpcode'] >= 300) {
    // 		pikkolo_handle_cancel_error($result, $order, $pikkolo, $log);
    // 	} else {
    // 		pikkolo_handle_cancel_success($result, $order, $pikkolo, $log);
    // 	}

    // 	$order->save();
    // }
    // add_action('woocommerce_order_status_cancelled', 'pikkolo_cancel_order');

    // function pikkolo_get_cancel_api_url($environment) {
    // 	if ($environment == 'production') {
    // 		return 'https://api.pikkolo.is';
    // 	} else {
    // 		return 'https://api-staging.pikkolo.is';
    // 	}
    // }

    // function pikkolo_send_cancel_request($url, $api_key) {	
    // 	$response = wp_remote_post($url, array(
    // 		'method' => 'POST',
    // 		'headers' => array(
    // 			'X-Api-Key' => $api_key,
    // 			'Content-Type' => 'application/json'
    // 		)
    // 	));

    // 	if (is_wp_error($response)) {
    // 		return [
    // 			'json' => json_decode("{\"error\":\"" . $response->get_error_message() . "\"}"),
    // 			'httpcode' => 500 // Generic error code, adjust as necessary
    // 		];
    // 	}

    // 	$httpcode = wp_remote_retrieve_response_code($response);
    // 	$body = wp_remote_retrieve_body($response);
    // 	$json = json_decode($body);

    // 	$log = new WC_Logger();
    // 	$log->add('pikkolois', "Response from Pikkoló API: " . $body);

    // 	return ['json' => $json, 'httpcode' => $httpcode];
    // }

    // function pikkolo_handle_cancel_error($result, $order, $pikkolo, $log) {
    // 	$error = 
    // 		"Error when attempting to cancel order with Pikkoló API. Please contact Pikkoló technical support if in need of assistance.\n
    // 		Status code: " . $result['httpcode'] . "\n
    // 		Error message: " . (empty($result['json']) ? "No error message" : $result['json']->error);

    // 	$order->update_meta_data('pikkolo_process_error', $error); // To display on admin page

    // 	if ($pikkolo->debug == 'yes') {
    // 		$log->add('pikkolois', $error);
    // 	}

    // 	pikkolo_send_error_to_logtail($error, $pikkolo, $log);
    // }

    // function pikkolo_handle_cancel_success($result, $order, $pikkolo, $log) {			
    // 	if ($pikkolo->debug == 'yes') {
    // 		$log->add('pikkolois', 'Order ' . $result['json']->data->vendorOrderId . ' successfully cancelled with Pikkoló API with ID ' . $result['json']->data->id);
    // 	}
    // }
}
