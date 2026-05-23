<?php
/**
 * Date utilities for Pikkoló shipping method.
 *
 * @package PikkoloWooDates
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
	if ( empty( $billing_fields['billing_delivery_date'] ) ) {
		return '';
	}
	$delivery_date = $billing_fields['billing_delivery_date'];
	$d_m_y         = DateTime::createFromFormat( 'd#m#Y', $delivery_date );
	$y_m_d         = DateTime::createFromFormat( 'Y#m#d', $delivery_date );
	if ( $d_m_y ) {
		return $d_m_y->format( 'Y-m-d' );
	}
	if ( $y_m_d ) {
		return $y_m_d->format( 'Y-m-d' );
	}
	return '';
}

/**
 * Fetches the delivery date from the order's metadata for the CODEROCKZ plugin.
 * See documentation for the delivery date metadata:
 * https://coderockz.com/documentations/get-the-delivery-information/
 *
 * @param WC_Order $order The order object.
 * @return string The delivery date in 'Y-m-d' format or an empty string if not set.
 */
function pikkolois_get_delivery_date_from_order_meta_data_coderockz( $order ): string {
	if ( metadata_exists( 'post', $order->get_id(), 'delivery_date' ) && get_post_meta( $order->get_id(), 'delivery_date', true ) !== '' ) {
		$delivery_date_from_meta = get_post_meta( $order->get_id(), 'delivery_date', true );
	}

	if ( $order->meta_exists( 'delivery_date' ) && $order->get_meta( 'delivery_date', true ) !== '' ) {
		$delivery_date_from_meta = $order->get_meta( 'delivery_date', true );
	}
	return $delivery_date_from_meta ?? '';
}

/**
 * Fetches the delivery date from the order's metadata.
 *
 * @param WC_Order $order The order object.
 * @return string The delivery date in 'Y-m-d' format or an empty string if not set.
 */
function pikkolois_get_delivery_date_from_order_meta_data( $order ): string {
	$delivery_date_from_meta = pikkolois_get_delivery_date_from_order_meta_data_coderockz( $order );
	return $delivery_date_from_meta ?? '';
}
