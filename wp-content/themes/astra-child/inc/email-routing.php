<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) ) {
	return;
}

function geeta_email_recipient_new_order( $recipient, $order ) {
	if ( ! $order ) {
		return $recipient;
	}

	$depot_id = $order->get_meta( '_shipping_pickup_depot_id' );

	if ( empty( $depot_id ) ) {
		return $recipient;
	}

	$depot_email = geeta_get_depot_email( $depot_id );

	if ( empty( $depot_email ) ) {
		return $recipient;
	}

	$recipient .= ',' . sanitize_email( $depot_email );

	return $recipient;
}
add_filter( 'woocommerce_email_recipient_new_order', 'geeta_email_recipient_new_order', 10, 2 );

function geeta_email_depot_info( $order, $sent_to_admin, $plain_text, $email ) {
	$depot_id = $order->get_meta( '_shipping_pickup_depot_id' );

	if ( empty( $depot_id ) ) {
		return;
	}

	$depot = geeta_get_depot( $depot_id );

	if ( ! $depot ) {
		return;
	}

	if ( $plain_text ) {
		geeta_email_depot_info_plain( $depot );
	} else {
		geeta_email_depot_info_html( $depot );
	}
}
add_action( 'woocommerce_email_order_meta', 'geeta_email_depot_info', 10, 4 );

function geeta_email_depot_info_html( $depot ) {
	?>
	<table class="td font-family" cellspacing="0" cellpadding="6" style="width: 100%; margin-top: 20px;" border="1">
		<tbody>
			<tr>
				<th class="td text-align-left" scope="row" colspan="2" style="background-color: #f8f8f8;">
					<?php esc_html_e( 'Pickup Depot Information', 'astra-child' ); ?>
				</th>
			</tr>
			<tr>
				<th class="td text-align-left" scope="row" style="width: 120px;">
					<?php esc_html_e( 'Depot:', 'astra-child' ); ?>
				</th>
				<td class="td text-align-left">
					<strong><?php echo esc_html( $depot['name'] ); ?></strong>
				</td>
			</tr>
			<?php if ( ! empty( $depot['contact_person'] ) ) : ?>
			<tr>
				<th class="td text-align-left" scope="row">
					<?php esc_html_e( 'Contact:', 'astra-child' ); ?>
				</th>
				<td class="td text-align-left">
					<?php echo esc_html( $depot['contact_person'] ); ?>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th class="td text-align-left" scope="row">
					<?php esc_html_e( 'Address:', 'astra-child' ); ?>
				</th>
				<td class="td text-align-left">
					<?php echo esc_html( $depot['address'] ); ?>
				</td>
			</tr>
			<?php if ( ! empty( $depot['phone'] ) ) : ?>
			<tr>
				<th class="td text-align-left" scope="row">
					<?php esc_html_e( 'Phone:', 'astra-child' ); ?>
				</th>
				<td class="td text-align-left">
					<?php echo esc_html( $depot['phone'] ); ?>
				</td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>
	<?php
}

function geeta_email_depot_info_plain( $depot ) {
	echo "\n\n----------------------------------------\n";
	echo esc_html__( 'Pickup Depot Information', 'astra-child' ) . "\n";
	echo esc_html__( 'Depot:', 'astra-child' ) . ' ' . esc_html( $depot['name'] ) . "\n";
	if ( ! empty( $depot['contact_person'] ) ) {
		echo esc_html__( 'Contact:', 'astra-child' ) . ' ' . esc_html( $depot['contact_person'] ) . "\n";
	}
	echo esc_html__( 'Address:', 'astra-child' ) . ' ' . esc_html( $depot['address'] ) . "\n";
	if ( ! empty( $depot['phone'] ) ) {
		echo esc_html__( 'Phone:', 'astra-child' ) . ' ' . esc_html( $depot['phone'] ) . "\n";
	}
	echo "----------------------------------------\n\n";
}
