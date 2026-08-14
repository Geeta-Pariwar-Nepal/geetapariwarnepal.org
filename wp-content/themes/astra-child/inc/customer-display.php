<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) ) {
	return;
}

function geeta_customer_order_depot_display( $order ) {
	$depot_id = $order->get_meta( '_shipping_pickup_depot_id' );

	if ( empty( $depot_id ) ) {
		return;
	}

	$depot = geeta_get_depot( $depot_id );

	if ( ! $depot ) {
		return;
	}

	$province = $order->get_meta( '_shipping_pickup_province' );
	$district = $order->get_meta( '_shipping_pickup_district' );
	$provinces = geeta_get_provinces();
	$province_label = isset( $provinces[ $province ] ) ? $provinces[ $province ] : $province;
	?>
	<section class="woocommerce-order-depot-info">
		<h2 class="woocommerce-order-depot-info__title"><?php esc_html_e( 'Pickup Depot', 'astra-child' ); ?></h2>
		<p class="woocommerce-order-depot-info__name"><strong><?php echo esc_html( $depot['name'] ); ?></strong></p>
		<?php if ( ! empty( $province_label ) ) : ?>
			<p class="woocommerce-order-depot-info__province"><?php echo esc_html( $province_label ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $district ) ) : ?>
			<p class="woocommerce-order-depot-info__district"><?php echo esc_html( $district ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $depot['contact_person'] ) ) : ?>
			<p class="woocommerce-order-depot-info__contact"><?php echo esc_html( $depot['contact_person'] ); ?></p>
		<?php endif; ?>
		<p class="woocommerce-order-depot-info__address"><?php echo esc_html( $depot['address'] ); ?></p>
		<?php if ( ! empty( $depot['phone'] ) ) : ?>
			<p class="woocommerce-order-depot-info__phone"><?php echo esc_html( $depot['phone'] ); ?></p>
		<?php endif; ?>
	</section>
	<?php
}
add_action( 'woocommerce_order_details_after_order_table', 'geeta_customer_order_depot_display' );
