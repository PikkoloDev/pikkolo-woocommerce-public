<?php
/**
 * Helper Functions
 *
 * @package PikkoloWooUtils
 */

/*
**========== Direct access not allowed ===========
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit; }


/**
 * Gets the station ID from order metadata or falls back to cookies.
 *
 * @param WC_Order $order The order object.
 * @return string The station ID.
 */
function pikkolo_get_station_id( $order ) {
	$station_id = $order->get_meta( 'pikkolo_station_id' );
	if ( ! $station_id ) {
		$station_id = isset( $_COOKIE['pikkolo_station_id'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['pikkolo_station_id'] ) ) : '';
	}
	return $station_id;
}

/**
 * Gets the station name from order metadata or falls back to cookies.
 *
 * @param WC_Order $order The order object.
 * @return string The station name.
 */
function pikkolo_get_station_name( $order ) {
	$station_name = $order->get_meta( 'pikkolo_station_name' );
	if ( ! $station_name ) {
		$station_name = isset( $_COOKIE['pikkolo_station_name'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['pikkolo_station_name'] ) ) : '';
	}
	return $station_name;
}

/**
 * Get the station name from the cookies and add it to the shipping method title.
 *
 * @param WC_Order $order The order object.
 * @param array    $cookies The cookies array.
 * @return bool
 */
function pikkolois_add_station_name_to_shipping_method_title( $order, array $cookies ): bool {
	$found_pikkolo = false;
	foreach ( $order->get_shipping_methods() as $shipping_method ) {
		if ( $shipping_method->get_method_id() === 'pikkolois' ) {
			$found_pikkolo = true;
			// Add the station name to the shipping method title.
			$station_name = pikkolo_get_station_name( $order );
			$shipping_method->set_method_title( ( 'Pikkoló - ' . $station_name ) );
		}
	}
	return $found_pikkolo;
}

/**
 * Gets the necessary data from the order's products.
 *
 * @param WC_Order $order The order object.
 * @return array An array of product data.
 */
function pikkolois_get_products_data( $order ) {
	$products = $order->get_items();

	// Initializing variables.
	$refrigerated_count    = 0;
	$frozen_count          = 0;
	$age_restriction_value = 0;

	$item_lines_refrigerated = array();
	$item_lines_frozen       = array();

	$item_name_refrigerated = array();
	$item_name_frozen       = array();

	foreach ( $products as $product ) {
		$product_id      = $product->get_product_id();
		$quantity        = $product->get_quantity();
		$frozen          = get_post_meta( $product_id, 'pikkolo_frozen', true );
		$age_restriction = get_post_meta( $product_id, 'pikkolo_age_restriction', true );

		$wc_product = $product->get_product();

		$weight     = $wc_product ? $wc_product->get_weight() : 0;
		$dimensions = $wc_product ? $wc_product->get_dimensions() : array();

		if ( 'none' !== $age_restriction && $age_restriction > $age_restriction_value ) {
			$age_restriction_value = $age_restriction;
		}

		$line_item = array(
			'name'     => $product->get_name(),
			'sku'      => (string) $product_id,
			'quantity' => $quantity,
			'weight'   => $weight,
		);

		if ( is_array( $dimensions ) ) {
			$line_item['dimensions'] = array(
				$dimensions['length'] ? $dimensions['length'] : 0,
				$dimensions['width'] ? $dimensions['width'] : 0,
				$dimensions['height'] ? $dimensions['height'] : 0,
			);
		}

		if ( 'true' === $frozen ) {
			$frozen_count       += $quantity;
			$item_name_frozen[]  = $line_item['name'];
			$line_item['type']   = 'Frozen';
			$item_lines_frozen[] = $line_item;
		} else {
			$refrigerated_count       += $quantity;
			$item_name_refrigerated[]  = $line_item['name'];
			$line_item['type']         = 'Refrigerated';
			$item_lines_refrigerated[] = $line_item;
		}
	}

	$items = array();
	if ( $refrigerated_count > 0 && array() !== $item_lines_refrigerated ) {
		$items[] = array(
			'description' => substr( join( '; ', $item_name_refrigerated ), 0, 191 ),
			'type'        => 'Refrigerated',
			'lineItems'   => $item_lines_refrigerated,
		);
	}
	if ( $frozen_count > 0 && array() !== $item_lines_frozen ) {
		$items[] = array(
			'description' => substr( join( '; ', $item_name_frozen ), 0, 191 ),
			'type'        => 'Frozen',
			'lineItems'   => $item_lines_frozen,
		);
	}

	return array(
		'items'                 => $items,
		'refrigerated_count'    => $refrigerated_count,
		'frozen_count'          => $frozen_count,
		'age_restriction_value' => $age_restriction_value,
	);
}



/**
 * Prepares the post fields for sending to the Pikkoló API.
 *
 * @param Pikkolo_Shipping_Method $pikkolo The Pikkoló shipping method instance.
 * @param WC_Order                $order The WooCommerce order object.
 * @param array                   $product_data The product data array containing items, refrigerated count, frozen count, and age restriction value.
 * @param WC_Logger               $log The WooCommerce logger instance for debugging.
 */
function pikkolo_prepare_post_fields( $pikkolo, $order, $product_data, $log ) {
	$station_id = pikkolo_get_station_id( $order );

	// Get delivery date from order meta data if it exists.
	$delivery_date_from_meta = pikkolois_get_delivery_date_from_order_meta_data( $order );

	// Get delivery date from checkout page if it exists.
	$delivery_date_from_checkout = $order->get_meta( 'pikkolo_delivery_date_from_checkout' );

	if ( 'yes' === ( $pikkolo->debug ) ) {
		$log->add( 'pikkolois', "Delivery date from meta {$delivery_date_from_meta}" );
		$log->add( 'pikkolois', "Delivery date from checkout $delivery_date_from_checkout" );
	}

	$vendor_order_id = strval( $order->get_id() );
	$customer_phone  = strval( $order->get_billing_phone() );
	$customer_email  = strval( $order->get_billing_email() );
	$customer_name   = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

	$ret = array(
		'vendorOrderId'           => $vendor_order_id,
		'stationId'               => $station_id,
		'customerPhone'           => $customer_phone,
		'customerEmail'           => $customer_email,
		'customerName'            => $customer_name,
		'isSubscription'          => false,
		'pickupAuthenticationAge' => intval( $product_data['age_restriction_value'] ) > 0 ? intval( $product_data['age_restriction_value'] ) : 0,
		'nrOfRefrigeratedItems'   => $product_data['refrigerated_count'],
		'nrOfFreezerItems'        => $product_data['frozen_count'],
		'items'                   => $product_data['items'],
	);
	if ( $delivery_date_from_meta ) {
		$ret['deliveryTimeId'] = $station_id . ':' . $delivery_date_from_meta;
	} elseif ( $delivery_date_from_checkout ) {
		$ret['deliveryTimeId'] = $station_id . ':' . $delivery_date_from_checkout;
	}

	return $ret;
}
