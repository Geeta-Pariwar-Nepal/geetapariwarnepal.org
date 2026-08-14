<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) ) {
	return;
}

function geeta_checkout_depot_field( $fields ) {
	$provinces = geeta_get_provinces();
	$province_options = array( '' => __( '— Select Province —', 'astra-child' ) );
	foreach ( $provinces as $key => $label ) {
		$province_options[ $key ] = $label;
	}

	$fields['order']['pickup_depot_province'] = array(
		'type'     => 'select',
		'label'    => __( 'Select Province', 'astra-child' ),
		'options'  => $province_options,
		'required' => true,
		'class'    => array( 'form-row-wide' ),
		'priority' => 90,
	);

	$fields['order']['pickup_depot_district'] = array(
		'type'     => 'select',
		'label'    => __( 'Select District', 'astra-child' ),
		'options'  => array( '' => __( '— Select Province First —', 'astra-child' ) ),
		'required' => true,
		'class'    => array( 'form-row-wide' ),
		'priority' => 95,
	);

	$fields['order']['pickup_depot_selection'] = array(
		'type'     => 'select',
		'label'    => __( 'Select Your Nearest Depot', 'astra-child' ),
		'options'  => array( '' => __( '— Select District First —', 'astra-child' ) ),
		'required' => true,
		'class'    => array( 'form-row-wide' ),
		'priority' => 100,
	);

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'geeta_checkout_depot_field' );

function geeta_validate_depot_selection() {
	if ( empty( $_POST['pickup_depot_province'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wc_add_notice( __( 'Please select your province.', 'astra-child' ), 'error' );
	}
	if ( empty( $_POST['pickup_depot_district'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wc_add_notice( __( 'Please select your district.', 'astra-child' ), 'error' );
	}
	if ( empty( $_POST['pickup_depot_selection'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wc_add_notice( __( 'Please select your nearest depot.', 'astra-child' ), 'error' );
	}
}
add_action( 'woocommerce_checkout_process', 'geeta_validate_depot_selection' );

function geeta_save_depot_order_meta( $order_id ) {
	if ( empty( $_POST['pickup_depot_selection'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	if ( ! empty( $_POST['pickup_depot_province'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$order->update_meta_data( '_shipping_pickup_province', sanitize_text_field( wp_unslash( $_POST['pickup_depot_province'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	if ( ! empty( $_POST['pickup_depot_district'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$order->update_meta_data( '_shipping_pickup_district', sanitize_text_field( wp_unslash( $_POST['pickup_depot_district'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	$depot_id = sanitize_text_field( wp_unslash( $_POST['pickup_depot_selection'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$order->update_meta_data( '_shipping_pickup_depot_id', $depot_id );
	$order->save();
}
add_action( 'woocommerce_checkout_update_order_meta', 'geeta_save_depot_order_meta' );

function geeta_checkout_depot_script() {
	if ( ! is_checkout() ) {
		return;
	}

	$depots = geeta_get_depots();

	// Hardcoded province+district mapping by depot slug (always correct)
	$slug_map = array(
		'dhulabari-jhapa'          => array( 'p' => 'province-1', 'd' => 'झापा' ),
		'birtamod-jhapa'           => array( 'p' => 'province-1', 'd' => 'झापा' ),
		'biratnagar-morang'        => array( 'p' => 'province-1', 'd' => 'मोरङ' ),
		'urlabari-morang'          => array( 'p' => 'province-1', 'd' => 'मोरङ' ),
		'dharan-sunsari'           => array( 'p' => 'province-1', 'd' => 'सुनसरी' ),
		'itahari-sunsari'          => array( 'p' => 'province-1', 'd' => 'सुनसरी' ),
		'main-depot-kathmandu'     => array( 'p' => 'province-3', 'd' => 'काठमाडौँ' ),
		'gongabu-kathmandu'        => array( 'p' => 'province-3', 'd' => 'काठमाडौँ' ),
		'bhainsepati-lalitpur'     => array( 'p' => 'province-3', 'd' => 'ललितपुर' ),
		'gwarko-lalitpur'          => array( 'p' => 'province-3', 'd' => 'ललितपुर' ),
		'battisputali-kathmandu'   => array( 'p' => 'province-3', 'd' => 'काठमाडौँ' ),
		'kirtipur-kathmandu'       => array( 'p' => 'province-3', 'd' => 'काठमाडौँ' ),
		'bansbari-kathmandu'       => array( 'p' => 'province-3', 'd' => 'काठमाडौँ' ),
		'madhyabaneshwor-kathmandu' => array( 'p' => 'province-3', 'd' => 'काठमाडौँ' ),
		'gaindakot-nawalparasi'    => array( 'p' => 'province-5', 'd' => 'नवलपरासी' ),
		'pokhara-kaski'            => array( 'p' => 'province-4', 'd' => 'कास्की' ),
		'nepalgunj-banke'          => array( 'p' => 'province-5', 'd' => 'बाँके' ),
		'nepalgunj-medical'        => array( 'p' => 'province-5', 'd' => 'बाँके' ),
		'ghorahi-dang'             => array( 'p' => 'province-5', 'd' => 'दाङ' ),
		'butwal-rupandehi'         => array( 'p' => 'province-5', 'd' => 'रुपन्देही' ),
		'birendranagar-surkhet'    => array( 'p' => 'province-6', 'd' => 'सुर्खेत' ),
		'attariya-kailali'         => array( 'p' => 'province-7', 'd' => 'कैलाली' ),
		'siliguri-india'           => array( 'p' => 'india', 'd' => 'सिलिगुडी' ),
	);

	$all_districts = geeta_get_all_districts_by_province();

	$depot_map = array();
	foreach ( $depots as $slug => $depot ) {
		$prov = isset( $slug_map[ $slug ] ) ? $slug_map[ $slug ]['p'] : $depot['province'];
		$dist = isset( $slug_map[ $slug ] ) ? $slug_map[ $slug ]['d'] : $depot['district'];

		if ( empty( $prov ) || empty( $dist ) ) {
			continue;
		}

		if ( ! isset( $depot_map[ $prov ] ) ) {
			$depot_map[ $prov ] = array();
		}
		if ( ! isset( $depot_map[ $prov ][ $dist ] ) ) {
			$depot_map[ $prov ][ $dist ] = array();
		}
		$depot_map[ $prov ][ $dist ][ $slug ] = array(
			'name'           => $depot['name'],
			'address'        => $depot['address'],
			'contact_person' => $depot['contact_person'],
			'phone'          => $depot['phone'],
		);
	}
	?>
	<style>
	.geeta-depot-details {
		background: #f8f8f8;
		border: 1px solid #ddd;
		border-radius: 4px;
		padding: 15px;
		margin-top: 10px;
		display: none;
	}
	.geeta-depot-details h4 { margin-top: 0; margin-bottom: 8px; }
	.geeta-depot-details p { margin: 4px 0; }
	</style>
	<script type="text/javascript">
	jQuery( function( $ ) {
		var depotMap = <?php echo wp_json_encode( $depot_map ); ?>;
		var allDistricts = <?php echo wp_json_encode( $all_districts ); ?>;

		var $p = $( '#pickup_depot_province' );
		var $d = $( '#pickup_depot_district' );
		var $s = $( '#pickup_depot_selection' );

		if ( ! $p.length || ! $d.length || ! $s.length ) return;

		$( '<div class="geeta-depot-details"></div>' ).insertAfter( $s.closest( '.form-row' ) );

		$p.on( 'change', function() {
			var prov = $p.val();
			$d.find( 'option' ).not( ':first' ).remove();
			$s.find( 'option' ).not( ':first' ).remove();
			$( '.geeta-depot-details' ).hide();
			if ( prov && allDistricts[ prov ] ) {
				$.each( allDistricts[ prov ], function( i, v ) {
					$d.append( $( '<option>', { value: v, text: v } ) );
				} );
			}
		} );

		$d.on( 'change', function() {
			var prov = $p.val(), dist = $d.val();
			$s.find( 'option' ).not( ':first' ).remove();
			$( '.geeta-depot-details' ).hide();
			if ( prov && dist && depotMap[ prov ] && depotMap[ prov ][ dist ] ) {
				$.each( depotMap[ prov ][ dist ], function( slug, data ) {
					$s.append( $( '<option>', { value: slug, text: data.name } ) );
				} );
			}
		} );

		$s.on( 'change', function() {
			var slug = $( this ).val();
			var $info = $( '.geeta-depot-details' );
			if ( ! slug ) { $info.hide(); return; }
			var prov = $p.val(), dist = $d.val();
			if ( depotMap[ prov ] && depotMap[ prov ][ dist ] && depotMap[ prov ][ dist ][ slug ] ) {
				var d = depotMap[ prov ][ dist ][ slug ];
				var html = '<h4>' + d.name + '</h4>' +
					'<p><strong><?php echo esc_js( __( 'Address:', 'astra-child' ) ); ?></strong> ' + d.address + '</p>';
				if ( d.contact_person ) html += '<p><strong><?php echo esc_js( __( 'Contact:', 'astra-child' ) ); ?></strong> ' + d.contact_person + '</p>';
				if ( d.phone ) html += '<p><strong><?php echo esc_js( __( 'Phone:', 'astra-child' ) ); ?></strong> ' + d.phone + '</p>';
				$info.html( html ).show();
			}
		} );

		if ( $p.val() ) $p.trigger( 'change' );
	} );
	</script>
	<?php
}
add_action( 'wp_footer', 'geeta_checkout_depot_script' );
