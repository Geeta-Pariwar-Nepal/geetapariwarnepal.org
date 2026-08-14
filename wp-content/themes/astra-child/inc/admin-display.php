<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) ) {
	return;
}

function geeta_admin_order_depot_display( $order ) {
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
	<div class="geeta-depot-info">
		<h4><?php esc_html_e( 'Pickup Depot', 'astra-child' ); ?></h4>
		<p>
			<strong><?php echo esc_html( $depot['name'] ); ?></strong><br>
			<?php if ( ! empty( $province_label ) ) : ?>
				<?php echo esc_html( $province_label ); ?><br>
			<?php endif; ?>
			<?php if ( ! empty( $district ) ) : ?>
				<?php echo esc_html( $district ); ?><br>
			<?php endif; ?>
			<?php if ( ! empty( $depot['contact_person'] ) ) : ?>
				<?php echo esc_html( $depot['contact_person'] ); ?><br>
			<?php endif; ?>
			<?php echo esc_html( $depot['address'] ); ?><br>
			<?php if ( ! empty( $depot['phone'] ) ) : ?>
				<?php echo esc_html( $depot['phone'] ); ?>
			<?php endif; ?>
		</p>
	</div>
	<?php
}
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'geeta_admin_order_depot_display' );
