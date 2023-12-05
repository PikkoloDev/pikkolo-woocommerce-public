<?php
/*
** Helper Functions
*/


/*
**========== Direct access not allowed ===========
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Try to get the delivery date from the billing fields.
 *
 * @param array $billing_fields The billing fields array.
 * @return string
 */
function pikkolois_get_delivery_date( array $billing_fields ): string {
	$delivery_date = $billing_fields['billing_delivery_date'];
	$d             = DateTime::createFromFormat( 'Y-m-d', $delivery_date );
	if ( $d && $d->format( 'Y-m-d' ) === $delivery_date ) {
		return $delivery_date;
	}
	return '';
}

/**
 * Get the station name from the cookies and add it to the shipping method title.
 *
 * @param WC_Order $order The order object.
 * @param array    $cookies The cookies array.
 * @return bool
 */
function pikkolois_add_station_name_to_shipping_method_title( $order, array $cookies ): string {
	$found_pikkolo = false;
	foreach ( $order->get_shipping_methods() as $shipping_method ) {
		if ( $shipping_method->get_method_id() === 'pikkolois' ) {
			$found_pikkolo = true;
			// Add the station name to the shipping method title.
			$station_name = '';
			if ( isset( $_COOKIE['pikkolo_station_name'] ) ) {
				$station_name = sanitize_text_field( wp_unslash( $cookies['pikkolo_station_name'] ) );
			}
			$shipping_method->set_method_title( ( 'Pikkoló - ' . $station_name ) );
		}
	}
	return $found_pikkolo;
}
