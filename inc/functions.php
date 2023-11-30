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
	$d = DateTime::createFromFormat( 'Y-m-d', $delivery_date );
	if ( $d && $d->format( 'Y-m-d' ) === $delivery_date ) {
		return $delivery_date;
	}
	return '';
}
