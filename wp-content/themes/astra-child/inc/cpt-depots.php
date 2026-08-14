<?php

defined( 'ABSPATH' ) || exit;

function geeta_register_depot_cpt() {
	$labels = array(
		'name'                  => _x( 'Depots', 'Post Type General Name', 'astra-child' ),
		'singular_name'         => _x( 'Depot', 'Post Type Singular Name', 'astra-child' ),
		'menu_name'             => __( 'Depots', 'astra-child' ),
		'name_admin_bar'        => __( 'Depot', 'astra-child' ),
		'add_new'               => __( 'Add New Depot', 'astra-child' ),
		'add_new_item'          => __( 'Add New Depot', 'astra-child' ),
		'new_item'              => __( 'New Depot', 'astra-child' ),
		'edit_item'             => __( 'Edit Depot', 'astra-child' ),
		'view_item'             => __( 'View Depot', 'astra-child' ),
		'all_items'             => __( 'All Depots', 'astra-child' ),
		'search_items'          => __( 'Search Depots', 'astra-child' ),
		'not_found'             => __( 'No depots found.', 'astra-child' ),
		'not_found_in_trash'    => __( 'No depots found in Trash.', 'astra-child' ),
	);

	$args = array(
		'label'               => __( 'Depot', 'astra-child' ),
		'labels'              => $labels,
		'description'         => __( 'Pickup depot locations', 'astra-child' ),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => 30,
		'menu_icon'           => 'dashicons-store',
		'supports'            => array( 'title' ),
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'can_export'          => true,
		'delete_with_user'    => false,
		'show_in_rest'        => true,
	);

	register_post_type( 'geeta_depot', $args );
}
add_action( 'init', 'geeta_register_depot_cpt' );

function geeta_register_depot_meta() {
	$fields = array(
		'_depot_address'        => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		'_depot_contact_person' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		'_depot_phone'          => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		'_depot_email'          => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ),
		'_depot_is_main'        => array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean' ),
		'_depot_province'       => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		'_depot_district'       => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
	);

	foreach ( $fields as $key => $args ) {
		register_post_meta(
			'geeta_depot',
			$key,
			array(
				'type'              => $args['type'],
				'description'       => '',
				'single'            => true,
				'sanitize_callback' => $args['sanitize_callback'],
				'auth_callback'     => null,
				'show_in_rest'      => true,
			)
		);
	}
}
add_action( 'init', 'geeta_register_depot_meta' );

function geeta_depot_add_meta_box() {
	add_meta_box(
		'geeta_depot_details',
		__( 'Depot Details', 'astra-child' ),
		'geeta_depot_meta_box_html',
		'geeta_depot',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'geeta_depot_add_meta_box' );

function geeta_depot_meta_box_html( $post ) {
	wp_nonce_field( 'geeta_depot_save_meta', 'geeta_depot_meta_nonce' );

	$address        = get_post_meta( $post->ID, '_depot_address', true );
	$contact_person = get_post_meta( $post->ID, '_depot_contact_person', true );
	$phone          = get_post_meta( $post->ID, '_depot_phone', true );
	$email          = get_post_meta( $post->ID, '_depot_email', true );
	$is_main        = (bool) get_post_meta( $post->ID, '_depot_is_main', true );
	$province       = get_post_meta( $post->ID, '_depot_province', true );
	$district       = get_post_meta( $post->ID, '_depot_district', true );

	$all_districts = function_exists( 'geeta_get_all_districts_by_province' ) ? geeta_get_all_districts_by_province() : array();
	?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="geeta_depot_address"><?php esc_html_e( 'Address / Location', 'astra-child' ); ?></label></th>
				<td><input type="text" id="geeta_depot_address" name="geeta_depot_address" value="<?php echo esc_attr( $address ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="geeta_depot_contact"><?php esc_html_e( 'Contact Person', 'astra-child' ); ?></label></th>
				<td><input type="text" id="geeta_depot_contact" name="geeta_depot_contact" value="<?php echo esc_attr( $contact_person ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="geeta_depot_phone"><?php esc_html_e( 'Phone Number', 'astra-child' ); ?></label></th>
				<td><input type="text" id="geeta_depot_phone" name="geeta_depot_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="geeta_depot_email"><?php esc_html_e( 'Notification Email', 'astra-child' ); ?></label></th>
				<td>
					<input type="email" id="geeta_depot_email" name="geeta_depot_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'This email receives New Order notifications when customers select this depot.', 'astra-child' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="geeta_depot_province"><?php esc_html_e( 'Province', 'astra-child' ); ?></label></th>
				<td>
					<select id="geeta_depot_province" name="geeta_depot_province">
						<option value=""><?php esc_html_e( '— Select Province —', 'astra-child' ); ?></option>
						<option value="province-1" <?php selected( $province, 'province-1' ); ?>><?php echo esc_html( 'प्रदेश नं. १ (कोशी प्रदेश)' ); ?></option>
						<option value="province-2" <?php selected( $province, 'province-2' ); ?>><?php echo esc_html( 'प्रदेश नं. २ (मधेश प्रदेश)' ); ?></option>
						<option value="province-3" <?php selected( $province, 'province-3' ); ?>><?php echo esc_html( 'प्रदेश नं. ३ (बागमती प्रदेश)' ); ?></option>
						<option value="province-4" <?php selected( $province, 'province-4' ); ?>><?php echo esc_html( 'प्रदेश नं. ४ (गण्डकी प्रदेश)' ); ?></option>
						<option value="province-5" <?php selected( $province, 'province-5' ); ?>><?php echo esc_html( 'प्रदेश नं. ५ (लुम्बिनी प्रदेश)' ); ?></option>
						<option value="province-6" <?php selected( $province, 'province-6' ); ?>><?php echo esc_html( 'प्रदेश नं. ६ (कर्णाली प्रदेश)' ); ?></option>
						<option value="province-7" <?php selected( $province, 'province-7' ); ?>><?php echo esc_html( 'प्रदेश नं. ७ (सुदूरपश्चिम प्रदेश)' ); ?></option>
						<option value="india" <?php selected( $province, 'india' ); ?>><?php echo esc_html( 'भारत' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="geeta_depot_district"><?php esc_html_e( 'District', 'astra-child' ); ?></label></th>
				<td>
					<select id="geeta_depot_district" name="geeta_depot_district">
						<option value=""><?php esc_html_e( '— Select District —', 'astra-child' ); ?></option>
						<?php foreach ( $all_districts as $pkey => $districts ) : ?>
							<?php foreach ( $districts as $d ) : ?>
								<option value="<?php echo esc_attr( $d ); ?>" class="geeta-dist-opt geeta-dist-<?php echo esc_attr( $pkey ); ?>" <?php selected( $district, $d ); ?>><?php echo esc_html( $d ); ?></option>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Select province first, then district.', 'astra-child' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Depot Type', 'astra-child' ); ?></th>
				<td>
					<label>
						<input type="checkbox" id="geeta_depot_is_main" name="geeta_depot_is_main" value="1" <?php checked( $is_main ); ?> />
						<?php esc_html_e( 'This is the Main Depot', 'astra-child' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'The Main Depot receives emails via the WooCommerce admin email setting. Sub-depots use their own notification email.', 'astra-child' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
	<script type="text/javascript">
	jQuery( function( $ ) {
		var $prov = $( '#geeta_depot_province' );
		var $dist = $( '#geeta_depot_district' );
		if ( ! $prov.length || ! $dist.length ) return;

		function geetaFilterDistricts() {
			var val = $prov.val();
			$dist.find( 'option' ).show();
			if ( val ) {
				$dist.find( '.geeta-dist-opt' ).not( '.geeta-dist-' + val ).hide();
			}
			if ( $dist.val() && $dist.find( 'option:selected' ).is( ':hidden' ) ) {
				$dist.val( '' );
			}
		}

		$prov.on( 'change', geetaFilterDistricts );
		geetaFilterDistricts();
	} );
	</script>
	<?php
}

function geeta_depot_save_meta( $post_id ) {
	if ( ! isset( $_POST['geeta_depot_meta_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( $_POST['geeta_depot_meta_nonce'] ), 'geeta_depot_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['geeta_depot_address'] ) ) {
		update_post_meta( $post_id, '_depot_address', sanitize_text_field( wp_unslash( $_POST['geeta_depot_address'] ) ) );
	}
	if ( isset( $_POST['geeta_depot_contact'] ) ) {
		update_post_meta( $post_id, '_depot_contact_person', sanitize_text_field( wp_unslash( $_POST['geeta_depot_contact'] ) ) );
	}
	if ( isset( $_POST['geeta_depot_phone'] ) ) {
		update_post_meta( $post_id, '_depot_phone', sanitize_text_field( wp_unslash( $_POST['geeta_depot_phone'] ) ) );
	}
	if ( isset( $_POST['geeta_depot_email'] ) ) {
		update_post_meta( $post_id, '_depot_email', sanitize_email( wp_unslash( $_POST['geeta_depot_email'] ) ) );
	}

	$is_main = isset( $_POST['geeta_depot_is_main'] ) ? 1 : 0;
	update_post_meta( $post_id, '_depot_is_main', $is_main );

	if ( isset( $_POST['geeta_depot_province'] ) ) {
		update_post_meta( $post_id, '_depot_province', sanitize_text_field( wp_unslash( $_POST['geeta_depot_province'] ) ) );
	}
	if ( isset( $_POST['geeta_depot_district'] ) ) {
		update_post_meta( $post_id, '_depot_district', sanitize_text_field( wp_unslash( $_POST['geeta_depot_district'] ) ) );
	}
}
add_action( 'save_post', 'geeta_depot_save_meta' );

function geeta_depot_admin_columns( $columns ) {
	$new_columns = array();
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new_columns[ $key ] = $label;
			$new_columns['depot_province'] = __( 'Province', 'astra-child' );
			$new_columns['depot_district'] = __( 'District', 'astra-child' );
			$new_columns['depot_type'] = __( 'Type', 'astra-child' );
			$new_columns['depot_contact'] = __( 'Contact Person', 'astra-child' );
			$new_columns['depot_phone']   = __( 'Phone', 'astra-child' );
			$new_columns['depot_email']   = __( 'Notification Email', 'astra-child' );
		} elseif ( 'date' === $key ) {
			continue;
		} else {
			$new_columns[ $key ] = $label;
		}
	}
	return $new_columns;
}
add_filter( 'manage_geeta_depot_posts_columns', 'geeta_depot_admin_columns' );

function geeta_depot_admin_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'depot_province':
			$province = get_post_meta( $post_id, '_depot_province', true );
			$provinces = function_exists( 'geeta_get_provinces' ) ? geeta_get_provinces() : array();
			echo isset( $provinces[ $province ] ) ? esc_html( $provinces[ $province ] ) : esc_html( $province );
			break;
		case 'depot_district':
			echo esc_html( get_post_meta( $post_id, '_depot_district', true ) );
			break;
		case 'depot_type':
			$is_main = (bool) get_post_meta( $post_id, '_depot_is_main', true );
			echo $is_main ? '<strong>' . esc_html__( 'Main Depot', 'astra-child' ) . '</strong>' : esc_html__( 'Sub-Depot', 'astra-child' );
			break;
		case 'depot_contact':
			echo esc_html( get_post_meta( $post_id, '_depot_contact_person', true ) );
			break;
		case 'depot_phone':
			echo esc_html( get_post_meta( $post_id, '_depot_phone', true ) );
			break;
		case 'depot_email':
			echo esc_html( get_post_meta( $post_id, '_depot_email', true ) );
			break;
	}
}
add_action( 'manage_geeta_depot_posts_custom_column', 'geeta_depot_admin_column_content', 10, 2 );

function geeta_seed_depots() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$existing = get_posts(
		array(
			'post_type'      => 'geeta_depot',
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		)
	);

	if ( ! empty( $existing ) ) {
		return;
	}

	$seed_data = array(
		'dhulabari-jhapa'          => array(
			'name'           => 'धुलाबारी, झापा',
			'address'        => 'सिटी प्लाजा, धुलाबारी',
			'contact_person' => 'श्रीमती तुलसा घिमिरे',
			'phone'          => '9804972156',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-1',
			'district'       => 'झापा',
		),
		'birtamod-jhapa'           => array(
			'name'           => 'बिर्तामोड, झापा',
			'address'        => 'जगदम्बा साडी सेन्टर, मुक्तिचोक, QFX सिनेमा/होटल Kohinoor सँगै',
			'contact_person' => 'श्रीमती राखी बिहानी',
			'phone'          => '9806058977',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-1',
			'district'       => 'झापा',
		),
		'biratnagar-morang'        => array(
			'name'           => 'विराटनगर, मोरङ',
			'address'        => 'अलंकार घडी पसल, बाटारोड शनिमन्दिर पूर्व',
			'contact_person' => 'श्रीमती बबिता लखोटिया',
			'phone'          => '9811057227',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-1',
			'district'       => 'मोरङ',
		),
		'urlabari-morang'          => array(
			'name'           => 'उर्लाबारी, मोरङ',
			'address'        => 'उर्लाबारी चोक, डिजिटल कलरल्याबसँगै',
			'contact_person' => 'श्रीमती जमुना रिजाल',
			'phone'          => '9841255290',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-1',
			'district'       => 'मोरङ',
		),
		'dharan-sunsari'           => array(
			'name'           => 'धरान, सुनसरी',
			'address'        => 'धरान-सुनसरी',
			'contact_person' => 'श्रीमती सारिका अग्रवाल',
			'phone'          => '9802722443',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-1',
			'district'       => 'सुनसरी',
		),
		'itahari-sunsari'          => array(
			'name'           => 'इटहरी, सुनसरी',
			'address'        => 'पीसजोन स्कूलसँगै, आइतबार पश्चिमलाइन',
			'contact_person' => 'श्रीमती शारदा न्यौपाने',
			'phone'          => '9841271009',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-1',
			'district'       => 'सुनसरी',
		),
		'main-depot-kathmandu'     => array(
			'name'           => 'मुख्य डिपो (काठमाडौँ)',
			'address'        => 'पशुपति बनकाली (पशुपतिनाथ मन्दिर दक्षिण) [फुटकर नपाइने]',
			'contact_person' => 'जयकिशन सारडा',
			'phone'          => '9841056107',
			'email'          => '',
			'is_main'        => true,
			'province'       => 'province-3',
			'district'       => 'काठमाडौँ',
		),
		'gongabu-kathmandu'        => array(
			'name'           => 'गोंगबु, काठमाडौँ',
			'address'        => 'गोंगबु गणेशस्थान नजिक',
			'contact_person' => 'श्री हरेराम श्रेष्ठ',
			'phone'          => '9851047386',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-3',
			'district'       => 'काठमाडौँ',
		),
		'bhainsepati-lalitpur'     => array(
			'name'           => 'भैंसेपाटी, ललितपुर',
			'address'        => 'तालिमकेन्द्र नजिकै (कोशेली कर्नर)',
			'contact_person' => 'श्री राधाविनोद शर्मा अधिकारी',
			'phone'          => '9840060008',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-3',
			'district'       => 'ललितपुर',
		),
		'gwarko-lalitpur'          => array(
			'name'           => 'ग्वार्को, ललितपुर',
			'address'        => 'Research Nepal, (NMB Bank नजिकै) [12-3PM]',
			'contact_person' => 'डा. भरतप्रसाद बडाल',
			'phone'          => '9841783418',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-3',
			'district'       => 'ललितपुर',
		),
		'battisputali-kathmandu'   => array(
			'name'           => 'बत्तिसपुतली, काठमाडौँ',
			'address'        => 'नेशनल रोटर पम्प, सेतोपुल उकालो',
			'contact_person' => 'श्री सञ्जय अटल',
			'phone'          => '9841206828 / 9851206828',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-3',
			'district'       => 'काठमाडौँ',
		),
		'kirtipur-kathmandu'       => array(
			'name'           => 'कीर्तिपुर, काठमाडौँ',
			'address'        => 'मैत्रीनगर GREEN TARA ग्रोसर्री नजिक',
			'contact_person' => 'श्री उद्धवप्राद गौतम',
			'phone'          => '9851228899',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-3',
			'district'       => 'काठमाडौँ',
		),
		'bansbari-kathmandu'       => array(
			'name'           => 'बाँसबारी, काठमाडौँ',
			'address'        => 'गंगालाल हस्पिटल नजिकै',
			'contact_person' => 'श्री अशोककुमार पौडेल',
			'phone'          => '9851118650',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-3',
			'district'       => 'काठमाडौँ',
		),
		'madhyabaneshwor-kathmandu' => array(
			'name'           => 'मध्यबानेश्वर, काठमाडौँ',
			'address'        => 'कात्यायिनी चोक नजिकै',
			'contact_person' => 'श्रीमती चन्द्रा शर्मा',
			'phone'          => '9848195910',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-3',
			'district'       => 'काठमाडौँ',
		),
		'gaindakot-nawalparasi'    => array(
			'name'           => 'गैंडाकोट, नवलपरासी (ब.सु.पू.)',
			'address'        => 'विकासचोक, (नारायणगढ पुल पारीपट्टि)',
			'contact_person' => 'श्री गुणराज पोखरेल',
			'phone'          => '9845033028',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-5',
			'district'       => 'नवलपरासी',
		),
		'pokhara-kaski'            => array(
			'name'           => 'पोखरा, कास्की',
			'address'        => 'सनातन पूजा सामाग्री, साइँग्लाचोक पोखरा-१७',
			'contact_person' => 'श्री कमलप्रसाद शर्मा',
			'phone'          => '9846082830',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-4',
			'district'       => 'कास्की',
		),
		'nepalgunj-banke'          => array(
			'name'           => 'बाँके, नेपालगञ्ज',
			'address'        => 'मुख्य डिपो',
			'contact_person' => 'श्री केदार अग्रवाल',
			'phone'          => '9848060107',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-5',
			'district'       => 'बाँके',
		),
		'nepalgunj-medical'        => array(
			'name'           => 'बाँके, नेपालगञ्ज (मेडिकल कलेज)',
			'address'        => 'नेपालगञ्ज मेडिकल कलेज (चिसापानी, कोहलपुर, बासगढी, बर्दियाका लागि)',
			'contact_person' => 'श्री सुदेश ज्ञवाली',
			'phone'          => '9856044051',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-5',
			'district'       => 'बाँके',
		),
		'ghorahi-dang'             => array(
			'name'           => 'घोराही, दाङ',
			'address'        => 'घोराही-९ तेघरा दाङ',
			'contact_person' => 'श्री झसेन्द्र आचार्य',
			'phone'          => '9847959473',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-5',
			'district'       => 'दाङ',
		),
		'butwal-rupandehi'         => array(
			'name'           => 'बुटवल, रुपन्देही',
			'address'        => 'बुटवल-११ देवीनगर',
			'contact_person' => 'श्रीमती किरण काफ्ले',
			'phone'          => '9847022678',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-5',
			'district'       => 'रुपन्देही',
		),
		'birendranagar-surkhet'    => array(
			'name'           => 'बिरेन्द्रनगर, सुर्खेत',
			'address'        => 'बिरेन्द्रनगर-१२ (नेवारे स्कूलनजिकै)',
			'contact_person' => 'श्रीमती चन्द्रा लामिछाने',
			'phone'          => '9848286465',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-6',
			'district'       => 'सुर्खेत',
		),
		'attariya-kailali'         => array(
			'name'           => 'अत्तरिया, कैलाली',
			'address'        => 'अत्तरिया, कैलाली',
			'contact_person' => 'श्री नविनप्रसाद भट्ट',
			'phone'          => '9851136357',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'province-7',
			'district'       => 'कैलाली',
		),
		'siliguri-india'           => array(
			'name'           => 'सिलिगुडी, भारत (विदेश शाखा)',
			'address'        => 'Lane-09, नर्मदा बगान, इक्रा इन्टिच्युटनजिके, चम्पासरी',
			'contact_person' => 'श्री परशुराम उपाध्याय',
			'phone'          => '+919547823939 / +919933779395',
			'email'          => '',
			'is_main'        => false,
			'province'       => 'india',
			'district'       => 'सिलिगुडी',
		),
	);

	foreach ( $seed_data as $slug => $data ) {
		$post_id = wp_insert_post(
			array(
				'post_title'  => $data['name'],
				'post_name'   => $slug,
				'post_type'   => 'geeta_depot',
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			continue;
		}

		update_post_meta( $post_id, '_depot_address', $data['address'] );
		update_post_meta( $post_id, '_depot_contact_person', $data['contact_person'] );
		update_post_meta( $post_id, '_depot_phone', $data['phone'] );
		update_post_meta( $post_id, '_depot_email', $data['email'] );
		update_post_meta( $post_id, '_depot_is_main', $data['is_main'] ? 1 : 0 );
		update_post_meta( $post_id, '_depot_province', $data['province'] );
		update_post_meta( $post_id, '_depot_district', $data['district'] );
	}
}

function geeta_activate_child_theme() {
	geeta_seed_depots();
}
add_action( 'after_switch_theme', 'geeta_activate_child_theme' );
