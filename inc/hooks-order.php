<?php
/**
 * Hook logic for Pikkolo integration
 *
 * @package Pikkolois
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Books a Pikkoló slot when a order is processed.
 *
 * @param  int $order_id The ID of the processed order.
 * @return void
 */
function pikkolo_process_order( $order_id ) {
	$pikkolo = new Pikkolo_Shipping_Method();
	$log     = new WC_Logger();

	$order = wc_get_order( $order_id );

	// Prevent duplicate Pikkoló orders.
	if ( $order->get_meta( 'pikkolo_order_id' ) ) {
		return;
	}

	$found_pikkolo = pikkolois_add_station_name_to_shipping_method_title( $order, $_COOKIE );
	if ( ! $found_pikkolo ) {
		// Pikkoló is not the chosen shipping method.
		return;
	}

	$products_data = pikkolois_get_products_data( $order );

	$post_fields = pikkolo_prepare_post_fields( $pikkolo, $order, $products_data, $log );

	$result = pikkolo_send_order_to_api( $pikkolo, $post_fields, $log );

	if ( ! $result || $result['httpcode'] >= 300 ) {
		pikkolo_handle_api_error( $result, $order, $pikkolo, $log );
	} else {
		pikkolo_handle_api_success( $result, $order, $pikkolo, $log );
	}

	$order->save();
}

/**
 * Sends an order to the Pikkoló API.
 *
 * @param  Pikkolo_Shipping_Method $pikkolo     The Pikkoló shipping method
 *                                              instance.
 * @param  array                   $post_fields The data to send to the API.
 * @param  WC_Logger               $log         The logger instance.
 * @return array An array containing the API response.
 */
function pikkolo_send_order_to_api( $pikkolo, $post_fields, $log ) {
	$process_url = $pikkolo->api_url . '/api/public/v1/orders';
	if ( 'yes' === ( $pikkolo->debug ) ) {
		$log->add( 'pikkolois', wp_json_encode( $post_fields ) );
	}

	$response = wp_remote_post(
		$process_url,
		array(
			'method'  => 'POST',
			'headers' => array(
				'X-Api-Key'    => ( $pikkolo->get_env() === 'production' ? $pikkolo->api_key : $pikkolo->api_key_test ),
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $post_fields ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array(
			'json'     => json_decode( '{"error":"' . $response->get_error_message() . '"}' ),
			'httpcode' => 500, // Generic error code, adjust as necessary.
		);
	}

	$httpcode = wp_remote_retrieve_response_code( $response );
	$body     = wp_remote_retrieve_body( $response );
	if ( 'yes' === ( $pikkolo->debug ) ) {
		$log->add( 'pikkolois', $body );
	}
	$json = json_decode( $body );

	return array(
		'json'     => $json,
		'httpcode' => $httpcode,
	);
}

/**
 * Handles API errors and display the error in the admin interface for the processed order.
 *
 * @param  array                   $result  The API response.
 * @param  WC_Order                $order   The order.
 * @param  Pikkolo_Shipping_Method $pikkolo The Pikkoló shipping method instance.
 * @param  WC_Logger               $log     The logger instance.
 * @return void
 */
function pikkolo_handle_api_error( $result, $order, $pikkolo, $log ) {
	$error =
	"Error when attempting to send order to Pikkoló API. Please contact Pikkoló technical support if in need of assistance.\n
			Status code: " . $result['httpcode'] . "\n
			Error message: " . ( empty( $result['json'] ) ? 'No error message' : $result['json']->error );

	$order->update_meta_data( 'pikkolo_process_error', $error ); // To display on admin page.

	if ( 'yes' === ( $pikkolo->debug ) ) {
		$log->add( 'pikkolois', $error );
	}

	pikkolo_report_error( $error, $pikkolo, $log );
}

/**
 * Handles API success and updates the order with the Pikkoló order ID and environment.
 *
 * @param  array                   $result  The API response.
 * @param  WC_Order                $order   The order.
 * @param  Pikkolo_Shipping_Method $pikkolo The Pikkoló shipping method instance.
 * @param  WC_Logger               $log     The logger instance.
 * @return void
 */
function pikkolo_handle_api_success( $result, $order, $pikkolo, $log ) {
	$log->add( 'pikkolois', 'Order ID: ' . $result['json']->data->id );
	$log->add( 'pikkolois', 'Environment: ' . $pikkolo->get_env() );

	$order->update_meta_data( 'pikkolo_order_id', $result['json']->data->id ); // To be able to cancel.
	$order->update_meta_data( 'pikkolo_environment', $pikkolo->get_env() ); // To prevent cancellation errors if test mode settings are changed after.

	if ( 'yes' === ( $pikkolo->debug ) ) {
		$log->add( 'pikkolois', 'Order ' . $result['json']->data->vendorOrderId . ' successfully sent to Pikkoló API with ID ' . $result['json']->data->id );
	}
}

/**
 * Report the error to the Pikkoló API for logging.
 *
 * @param  string                  $error   The error message.
 * @param  Pikkolo_Shipping_Method $pikkolo The Pikkoló shipping method instance.
 * @param  WC_Logger               $log     The logger instance.
 * @return void
 */
function pikkolo_report_error( $error, $pikkolo, $log ) {
	$url = $pikkolo->api_url . '/api/public/v1/log';

	$response = wp_remote_post(
		$url,
		array(
			'method'  => 'POST',
			'headers' => array(
				'X-Api-Key'    => ( $pikkolo->get_env() === 'production' ? $pikkolo->api_key : $pikkolo->api_key_test ),
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'level'   => 'debug',
					'message' => $error,
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		$log->add( 'pikkolois', 'Something went wrong when sending information to Pikkoló: ' . $error_message );
	} else {
		$httpcode = wp_remote_retrieve_response_code( $response );
		$body     = wp_remote_retrieve_body( $response ); // Convert to JSON to get the error message.
		$json     = json_decode( $body );

		if ( $httpcode >= 300 ) {
			$log->add( 'pikkolois', 'Something went wrong when sending information to Pikkoló: ' . $json->error );
		} else {
			$log->add( 'pikkolois', 'Error information successfully sent to Pikkoló' );
		}
	}
}
