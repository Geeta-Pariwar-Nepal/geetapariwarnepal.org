<?php
/**
 * WhatsApp Order Notification System for Geeta Pariwar Nepal
 *
 * Two delivery modes:
 *   1. Automatic — sends order details directly to your WhatsApp number
 *      via Meta's free WhatsApp Cloud API (no subscription, 1,000 free
 *      conversations/month).
 *   2. Click-to-chat — shows a button on the thank-you page so the
 *      customer can forward the order details to you.
 *
 * @package Astra_Child
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * ─── DEFAULT CONFIGURATION ─────────────────────────────
 *
 * Change your WhatsApp number here OR via the admin settings
 * (WooCommerce → WhatsApp Notifications).
 */
define( 'GP_WHATSAPP_DEFAULT_NUMBER', '9779800000000' );
define( 'GP_WHATSAPP_API_VERSION',    'v22.0' );

/**
 * ─── ADMIN SETTINGS ────────────────────────────────────
 */
class GP_WhatsApp_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_gp_whatsapp_test', array( __CLASS__, 'handle_test' ) );
	}

	public static function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'WhatsApp Notifications', 'astra-child' ),
			__( 'WhatsApp', 'astra-child' ),
			'manage_woocommerce',
			'gp-whatsapp',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting( 'gp_whatsapp_group', 'gp_whatsapp_number', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_number' ),
			'default'           => GP_WHATSAPP_DEFAULT_NUMBER,
		) );

		register_setting( 'gp_whatsapp_group', 'gp_whatsapp_enabled', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'yes',
		) );

		register_setting( 'gp_whatsapp_group', 'gp_whatsapp_trigger_status', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'any',
		) );

		register_setting( 'gp_whatsapp_group', 'gp_whatsapp_template', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => self::default_template(),
		) );

		register_setting( 'gp_whatsapp_group', 'gp_whatsapp_delivery_method', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'click_to_chat',
		) );

		register_setting( 'gp_whatsapp_group', 'gp_whatsapp_cloud_phone_number_id', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		register_setting( 'gp_whatsapp_group', 'gp_whatsapp_cloud_access_token', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		add_settings_section(
			'gp_whatsapp_main',
			__( 'WhatsApp Notification Settings', 'astra-child' ),
			array( __CLASS__, 'section_callback' ),
			'gp-whatsapp'
		);

		add_settings_field(
			'gp_whatsapp_number',
			__( 'Your WhatsApp Number', 'astra-child' ),
			array( __CLASS__, 'field_number' ),
			'gp-whatsapp',
			'gp_whatsapp_main'
		);

		add_settings_field(
			'gp_whatsapp_enabled',
			__( 'Enable Notifications', 'astra-child' ),
			array( __CLASS__, 'field_enabled' ),
			'gp-whatsapp',
			'gp_whatsapp_main'
		);

		add_settings_field(
			'gp_whatsapp_delivery_method',
			__( 'Delivery Method', 'astra-child' ),
			array( __CLASS__, 'field_delivery_method' ),
			'gp-whatsapp',
			'gp_whatsapp_main'
		);

		add_settings_field(
			'gp_whatsapp_trigger_status',
			__( 'Send When Order Status Is', 'astra-child' ),
			array( __CLASS__, 'field_trigger_status' ),
			'gp-whatsapp',
			'gp_whatsapp_main'
		);

		add_settings_field(
			'gp_whatsapp_template',
			__( 'Message Template', 'astra-child' ),
			array( __CLASS__, 'field_template' ),
			'gp-whatsapp',
			'gp_whatsapp_main'
		);

		add_settings_section(
			'gp_whatsapp_cloud',
			__( 'WhatsApp Cloud API (for Automatic Delivery)', 'astra-child' ),
			array( __CLASS__, 'cloud_section_callback' ),
			'gp-whatsapp'
		);

		add_settings_field(
			'gp_whatsapp_cloud_phone_number_id',
			__( 'Phone Number ID', 'astra-child' ),
			array( __CLASS__, 'field_phone_number_id' ),
			'gp-whatsapp',
			'gp_whatsapp_cloud'
		);

		add_settings_field(
			'gp_whatsapp_cloud_access_token',
			__( 'Access Token', 'astra-child' ),
			array( __CLASS__, 'field_access_token' ),
			'gp-whatsapp',
			'gp_whatsapp_cloud'
		);
	}

	public static function sanitize_number( $value ) {
		$clean = preg_replace( '/[^0-9]/', '', $value );
		if ( empty( $clean ) ) {
			add_settings_error( 'gp_whatsapp_number', 'invalid', __( 'Please enter a valid WhatsApp number.', 'astra-child' ) );
			return get_option( 'gp_whatsapp_number', GP_WHATSAPP_DEFAULT_NUMBER );
		}
		return $clean;
	}

	public static function default_template() {
		return "━━━━━━━━━━━━━━━\n📚 New Book Order\n━━━━━━━━━━━━━━━\n\nOrder #{order_number}\n\nCustomer\n{customer_name}\n\nPhone\n{mobile_number}\n\nEmail\n{email}\n\nAddress\n{billing_address}\n{district}, {province}\n{country}\n\nProducts\n{products}\n\nSubtotal\n{subtotal}\n\nShipping\n{shipping}\n\nDiscount\n{discount}\n\nTotal\n{total}\n\nPayment\n{payment_method}\n\nNotes\n{notes}\n\n━━━━━━━━━━━━━━━";
	}

	public static function section_callback() {
		echo '<p>' . esc_html__( 'Get instant order notifications on your WhatsApp. Choose between automatic delivery (Cloud API) or click-to-chat.', 'astra-child' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Available placeholders:', 'astra-child' ) . '</strong> {order_number}, {order_date}, {customer_name}, {mobile_number}, {email}, {billing_address}, {district}, {province}, {country}, {products}, {subtotal}, {shipping}, {discount}, {total}, {payment_method}, {notes}</p>';
	}

	public static function cloud_section_callback() {
		$method = get_option( 'gp_whatsapp_delivery_method', 'click_to_chat' );
		if ( 'click_to_chat' === $method ) {
			echo '<p style="color:#856404;background:#fff3cd;padding:10px 14px;border-radius:4px;">' . esc_html__( 'You selected "Manual (Click to Chat)" above. These Cloud API fields are only needed for automatic delivery.', 'astra-child' ) . '</p>';
			return;
		}
		echo '<p>' . esc_html__( 'Enter your WhatsApp Cloud API credentials from the Meta Developer dashboard. These are only needed for automatic delivery.', 'astra-child' ) . '</p>';
		echo '<ol style="margin-top:6px;">';
		echo '<li>' . esc_html__( 'Go to developers.facebook.com → My Apps → your WhatsApp app.', 'astra-child' ) . '</li>';
		echo '<li>' . esc_html__( 'Under "WhatsApp → API Setup", copy the "Phone Number ID".', 'astra-child' ) . '</li>';
		echo '<li>' . esc_html__( 'Generate a permanent token: Business Settings → System Users → Add an admin user with "whatsapp_business_messaging" permission → Generate Token.', 'astra-child' ) . '</li>';
		echo '<li>' . esc_html__( 'Paste the token and Phone Number ID below, then save.', 'astra-child' ) . '</li>';
		echo '<li>' . esc_html__( 'Important: Message your WhatsApp Business number ONCE from your personal number. After that, order notifications will be sent automatically within 24 hours.', 'astra-child' ) . '</li>';
		echo '</ol>';
	}

	public static function field_number() {
		$value = get_option( 'gp_whatsapp_number', GP_WHATSAPP_DEFAULT_NUMBER );
		echo '<input type="text" name="gp_whatsapp_number" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="97798XXXXXXXX">';
		echo '<p class="description">' . esc_html__( 'Include country code without + sign (e.g. 97798XXXXXXXX). This number will receive notifications.', 'astra-child' ) . '</p>';
	}

	public static function field_enabled() {
		$value = get_option( 'gp_whatsapp_enabled', 'yes' );
		echo '<label><input type="checkbox" name="gp_whatsapp_enabled" value="yes" ' . checked( 'yes', $value, false ) . '> ' . esc_html__( 'Enable WhatsApp order notifications', 'astra-child' ) . '</label>';
	}

	public static function field_delivery_method() {
		$value = get_option( 'gp_whatsapp_delivery_method', 'click_to_chat' );
		?>
		<select name="gp_whatsapp_delivery_method" id="gp-whatsapp-delivery-method">
			<option value="click_to_chat" <?php selected( $value, 'click_to_chat' ); ?>>
				<?php esc_html_e( 'Manual (Click to Chat) — customer taps a button', 'astra-child' ); ?>
			</option>
			<option value="auto" <?php selected( $value, 'auto' ); ?>>
				<?php esc_html_e( 'Automatic (Cloud API) — sent directly to your phone', 'astra-child' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( '"Automatic" requires Cloud API credentials below (free from Meta). "Manual" shows a WhatsApp button on the thank-you page.', 'astra-child' ); ?>
		</p>
		<script>
		(function() {
			var sel = document.getElementById('gp-whatsapp-delivery-method');
			var row = sel.closest('table') || document.querySelector('.form-table');
			var ids = ['gp_whatsapp_cloud_phone_number_id', 'gp_whatsapp_cloud_access_token'];
			function toggle() {
				var show = sel.value === 'auto';
				// Toggle the parent tr for each relevant field (walk up to tr)
				document.querySelectorAll('[name="gp_whatsapp_cloud_phone_number_id"], [name="gp_whatsapp_cloud_access_token"]').forEach(function(el) {
					var tr = el.closest('tr');
					if (tr) tr.style.display = show ? '' : 'none';
				});
				// Also toggle the cloud section description row
				var sectionRows = document.querySelectorAll('[name="gp_whatsapp_cloud_phone_number_id"]').forEach(function(el) {
					var tr = el.closest('tr');
					if (tr) {
						var prev = tr.previousElementSibling;
						if (prev && prev.querySelector('p')) prev.style.display = show ? '' : 'none';
					}
				});
			}
			if (sel) { sel.addEventListener('change', toggle); toggle(); }
		})();
		</script>
		<?php
	}

	public static function field_trigger_status() {
		$value  = get_option( 'gp_whatsapp_trigger_status', 'any' );
		$status = array(
			'any'        => __( 'Any status (recommended)', 'astra-child' ),
			'pending'    => __( 'Pending', 'astra-child' ),
			'processing' => __( 'Processing', 'astra-child' ),
			'on-hold'    => __( 'On Hold', 'astra-child' ),
			'completed'  => __( 'Completed', 'astra-child' ),
		);
		echo '<select name="gp_whatsapp_trigger_status">';
		foreach ( $status as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Send the notification when the order reaches this status. "Any status" sends it immediately on the thank-you page.', 'astra-child' ) . '</p>';
	}

	public static function field_template() {
		$value = get_option( 'gp_whatsapp_template', self::default_template() );
		echo '<textarea name="gp_whatsapp_template" rows="12" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Customize the message. Use the placeholders listed above.', 'astra-child' ) . '</p>';
	}

	public static function field_phone_number_id() {
		$value = get_option( 'gp_whatsapp_cloud_phone_number_id', '' );
		echo '<input type="text" name="gp_whatsapp_cloud_phone_number_id" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="123456789012345">';
		echo '<p class="description">' . esc_html__( 'Found in Meta Developer Dashboard → WhatsApp → API Setup.', 'astra-child' ) . '</p>';
	}

	public static function field_access_token() {
		$value = get_option( 'gp_whatsapp_cloud_access_token', '' );
		$masked = $value ? substr( $value, 0, 6 ) . '••••••••' . substr( $value, -4 ) : '';
		echo '<input type="password" name="gp_whatsapp_cloud_access_token" value="' . esc_attr( $value ) . '" class="regular-text" style="width:400px;" autocomplete="off">';
		if ( $masked ) {
			echo '<p class="description">' . esc_html( sprintf( __( 'Current token: %s (saved)', 'astra-child' ), $masked ) ) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'Permanent token from Meta Business Settings → System Users. Needs "whatsapp_business_messaging" permission.', 'astra-child' ) . '</p>';

		if ( $value && get_option( 'gp_whatsapp_cloud_phone_number_id', '' ) ) {
			$test_url = admin_url( 'admin-post.php?action=gp_whatsapp_test&_wpnonce=' . wp_create_nonce( 'gp_whatsapp_test' ) );
			echo '<p><a href="' . esc_url( $test_url ) . '" class="button button-secondary">' . esc_html__( 'Test Connection', 'astra-child' ) . '</a></p>';
		}
	}

	public static function handle_test() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'gp_whatsapp_test' ) ) {
			wp_die( 'Security check failed.' );
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized.' );
		}

		$token  = get_option( 'gp_whatsapp_cloud_access_token', '' );
		$pid    = get_option( 'gp_whatsapp_cloud_phone_number_id', '' );
		$number = get_option( 'gp_whatsapp_number', GP_WHATSAPP_DEFAULT_NUMBER );

		$result = GP_WhatsApp_Cloud_API::send( $number, '✅ WhatsApp Cloud API test message from Geeta Pariwar Nepal. Connection is working!', $pid, $token );
		$status = $result['success'] ? '1' : '0';

		wp_safe_redirect( add_query_arg( array(
			'page'    => 'gp-whatsapp',
			'gp_test' => $status,
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$test_result = isset( $_GET['gp_test'] ) ? (int) $_GET['gp_test'] : null;
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( 1 === $test_result ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '✅ Test message sent successfully! Check your WhatsApp.', 'astra-child' ); ?></p></div>
			<?php elseif ( 0 === $test_result ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( '❌ Test failed. Check your Phone Number ID and Access Token, then try again.', 'astra-child' ); ?></p></div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'gp_whatsapp_group' );
				do_settings_sections( 'gp-whatsapp' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
GP_WhatsApp_Settings::init();

/**
 * ─── MESSAGE BUILDER ───────────────────────────────────
 */
class GP_WhatsApp_Message {

	public static function build( $order ) {
		$template = get_option( 'gp_whatsapp_template', GP_WhatsApp_Settings::default_template() );
		$data     = self::get_template_data( $order );
		return str_replace( array_keys( $data ), array_values( $data ), $template );
	}

	public static function plain_summary( $order ) {
		$items  = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = '• ' . $item->get_name() . ' ×' . $item->get_quantity() . ' — ' . wp_strip_all_tags( wc_price( $item->get_total() ) );
		}

		return sprintf(
			"━━━━━━━━━━━━━━━\n📚 New Book Order\n━━━━━━━━━━━━━━━\n\nOrder #%s\n\nCustomer\n%s\n\nPhone\n%s\n\nEmail\n%s\n\nAddress\n%s\n%s, %s\n%s\n\nProducts\n%s\n\nSubtotal\n%s\n\nShipping\n%s\n\nDiscount\n%s\n\nTotal\n%s\n\nPayment\n%s\n\nNotes\n%s\n\n━━━━━━━━━━━━━━━",
			$order->get_order_number(),
			$order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			$order->get_billing_phone(),
			$order->get_billing_email(),
			str_replace( "\n", ', ', $order->get_billing_address_1() . ( $order->get_billing_address_2() ? ', ' . $order->get_billing_address_2() : '' ) ),
			$order->get_billing_city(),
			$order->get_billing_state(),
			$order->get_billing_country(),
			implode( "\n", $items ),
			wp_strip_all_tags( wc_price( $order->get_subtotal() ) ),
			wp_strip_all_tags( wc_price( $order->get_shipping_total() ) ),
			wp_strip_all_tags( wc_price( $order->get_discount_total() ) ),
			wp_strip_all_tags( wc_price( $order->get_total() ) ),
			$order->get_payment_method_title(),
			$order->get_customer_note() ?: '—'
		);
	}

	public static function get_template_data( $order ) {
		$items       = array();
		$item_prices = array();
		$total_qty   = 0;

		foreach ( $order->get_items() as $item ) {
			$items[]       = '• ' . $item->get_name() . ' ×' . $item->get_quantity() . ' — ' . wp_strip_all_tags( wc_price( $item->get_total() ) );
			$item_prices[] = '• ' . $item->get_name() . ' — ' . wp_strip_all_tags( wc_price( $item->get_subtotal() / max( 1, $item->get_quantity() ) ) ) . ' each';
			$total_qty    += $item->get_quantity();
		}

		$order_date = $order->get_date_created();
		return array(
			'{order_number}'    => $order->get_order_number(),
			'{order_date}'      => $order_date ? $order_date->date_i18n( 'M j, Y' ) : gmdate( 'M j, Y' ),
			'{customer_name}'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'{mobile_number}'   => $order->get_billing_phone(),
			'{email}'           => $order->get_billing_email(),
			'{billing_address}' => str_replace( "\n", ', ', $order->get_billing_address_1() . ( $order->get_billing_address_2() ? ', ' . $order->get_billing_address_2() : '' ) ),
			'{district}'        => $order->get_billing_city(),
			'{province}'        => $order->get_billing_state(),
			'{country}'         => $order->get_billing_country(),
			'{products}'        => implode( "\n", $items ),
			'{item_price}'      => implode( "\n", $item_prices ),
			'{quantity}'        => $total_qty,
			'{subtotal}'        => wp_strip_all_tags( wc_price( $order->get_subtotal() ) ),
			'{shipping}'        => wp_strip_all_tags( wc_price( $order->get_shipping_total() ) ),
			'{discount}'        => wp_strip_all_tags( wc_price( $order->get_discount_total() ) ),
			'{total}'           => wp_strip_all_tags( wc_price( $order->get_total() ) ),
			'{payment_method}'  => $order->get_payment_method_title(),
			'{notes}'           => $order->get_customer_note() ?: '—',
		);
	}
}

/**
 * ─── WHATSAPP CLOUD API ────────────────────────────────
 *
 * Sends messages via Meta's free WhatsApp Cloud API.
 * Requires a phone number ID and permanent access token from the
 * Meta Developer Dashboard.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class GP_WhatsApp_Cloud_API {

	/**
	 * Send a text message via the WhatsApp Cloud API.
	 *
	 * @param  string $to      Recipient WhatsApp number (no + sign).
	 * @param  string $message Message text.
	 * @param  string $phone_number_id  Optional override (defaults to option).
	 * @param  string $access_token     Optional override (defaults to option).
	 * @return array{success:bool,error?:string}
	 */
	public static function send( $to, $message, $phone_number_id = '', $access_token = '' ) {
		if ( ! $phone_number_id ) {
			$phone_number_id = get_option( 'gp_whatsapp_cloud_phone_number_id', '' );
		}
		if ( ! $access_token ) {
			$access_token = get_option( 'gp_whatsapp_cloud_access_token', '' );
		}

		if ( ! $phone_number_id || ! $access_token ) {
			return array(
				'success' => false,
				'error'   => 'Cloud API not configured — missing phone number ID or access token.',
			);
		}

		$to = preg_replace( '/[^0-9]/', '', $to );
		if ( ! $to ) {
			return array( 'success' => false, 'error' => 'Invalid recipient number.' );
		}

		$url = 'https://graph.facebook.com/' . GP_WHATSAPP_API_VERSION . '/' . $phone_number_id . '/messages';

		$body = wp_json_encode( array(
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $to,
			'type'              => 'text',
			'text'              => array(
				'preview_url' => false,
				'body'        => $message,
			),
		) );

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => $body,
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$resp_body   = wp_remote_retrieve_body( $response );
		$data        = json_decode( $resp_body, true );

		if ( $status_code >= 200 && $status_code < 300 && isset( $data['messages'][0]['id'] ) ) {
			return array( 'success' => true, 'message_id' => $data['messages'][0]['id'] );
		}

		$error = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP {$status_code}";
		return array( 'success' => false, 'error' => $error );
	}
}

/**
 * ─── AUTO SENDER ───────────────────────────────────────
 *
 * Automatically sends the order notification via WhatsApp Cloud API
 * when an order is placed (on the thank-you page).
 */
class GP_WhatsApp_AutoSender {

	public static function init() {
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'send_auto_notification' ), 10 );
	}

	public static function send_auto_notification( $order_id ) {
		if ( 'yes' !== get_option( 'gp_whatsapp_enabled', 'yes' ) ) {
			return;
		}
		if ( 'auto' !== get_option( 'gp_whatsapp_delivery_method', 'click_to_chat' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( ! self::should_send( $order ) ) {
			return;
		}

		if ( $order->get_meta( '_gp_whatsapp_sent' ) ) {
			return;
		}

		$number  = get_option( 'gp_whatsapp_number', GP_WHATSAPP_DEFAULT_NUMBER );
		$message = GP_WhatsApp_Message::build( $order );
		$result  = GP_WhatsApp_Cloud_API::send( $number, $message );

		$order->update_meta_data( '_gp_whatsapp_sent', $result['success'] ? '1' : '0' );
		if ( ! $result['success'] && isset( $result['error'] ) ) {
			$order->update_meta_data( '_gp_whatsapp_error', $result['error'] );
		}
		$order->save();
	}

	private static function should_send( $order ) {
		$trigger = get_option( 'gp_whatsapp_trigger_status', 'any' );
		if ( 'any' === $trigger ) {
			return true;
		}
		return $order->has_status( $trigger );
	}
}
GP_WhatsApp_AutoSender::init();

/**
 * ─── THANK-YOU PAGE ───────────────────────────────────
 *
 * Shows a click-to-chat button when:
 *   - Delivery method is "Manual (Click to Chat)", OR
 *   - Delivery method is "Automatic" but the Cloud API send failed.
 */
class GP_WhatsApp_ThankYou {

	public static function init() {
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'render' ), 20 );
	}

	public static function render( $order_id ) {
		if ( 'yes' !== get_option( 'gp_whatsapp_enabled', 'yes' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$method = get_option( 'gp_whatsapp_delivery_method', 'click_to_chat' );
		$send_succeeded = '1' === $order->get_meta( '_gp_whatsapp_sent' );

		if ( 'auto' === $method && $send_succeeded ) {
			self::render_success( $order );
			return;
		}

		if ( ! self::should_show( $order ) ) {
			return;
		}

		$auto_failed = 'auto' === $method && '0' === $order->get_meta( '_gp_whatsapp_sent' );
		self::render_button( $order, $auto_failed );
	}

	public static function render_success( $order ) {
		?>
		<div class="gp-whatsapp-thankyou gp-whatsapp-auto-sent">
			<h3><?php esc_html_e( '📲 Notification Sent!', 'astra-child' ); ?></h3>
			<p><?php esc_html_e( 'Your order details have been sent to our team via WhatsApp. We will contact you shortly.', 'astra-child' ); ?></p>
		</div>
		<?php
	}

	public static function render_button( $order, $auto_failed = false ) {
		$number  = get_option( 'gp_whatsapp_number', GP_WHATSAPP_DEFAULT_NUMBER );
		$message = GP_WhatsApp_Message::build( $order );
		$url     = self::wa_url( $number, $message );
		?>
		<div class="gp-whatsapp-thankyou">
			<?php if ( $auto_failed ) : ?>
				<p class="gp-whatsapp-error-note"><?php esc_html_e( '⚠️ Automatic notification failed. Please tap the button below to notify us.', 'astra-child' ); ?></p>
			<?php endif; ?>
			<h3><?php esc_html_e( '✅ Thank You!', 'astra-child' ); ?></h3>
			<p><?php esc_html_e( 'Your order has been received. Our team will contact you shortly.', 'astra-child' ); ?></p>
			<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="gp-whatsapp-btn">
				📲 <?php esc_html_e( 'Contact Us on WhatsApp', 'astra-child' ); ?>
			</a>
			<p class="gp-whatsapp-help"><?php esc_html_e( 'Tap the button to send order details to our team via WhatsApp.', 'astra-child' ); ?></p>
		</div>
		<?php
	}

	private static function should_show( $order ) {
		$trigger = get_option( 'gp_whatsapp_trigger_status', 'any' );
		if ( 'any' === $trigger ) {
			return true;
		}
		return $order->has_status( $trigger );
	}

	private static function wa_url( $number, $text ) {
		return 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $number ) . '?text=' . rawurlencode( $text );
	}
}
GP_WhatsApp_ThankYou::init();

/**
 * ─── ADMIN ORDER PAGE ─────────────────────────────────
 */
class GP_WhatsApp_AdminOrder {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
	}

	public static function add_meta_box() {
		add_meta_box(
			'gp_whatsapp_order',
			__( 'WhatsApp Notification', 'astra-child' ),
			array( __CLASS__, 'render_meta_box' ),
			'shop_order',
			'side',
			'high'
		);
	}

	public static function render_meta_box( $post ) {
		$order = wc_get_order( $post->ID );
		if ( ! $order ) {
			return;
		}

		$method = get_option( 'gp_whatsapp_delivery_method', 'click_to_chat' );
		?>

		<p><strong><?php esc_html_e( 'Delivery Method:', 'astra-child' ); ?></strong>
			<?php echo 'auto' === $method ? esc_html__( 'Automatic (Cloud API)', 'astra-child' ) : esc_html__( 'Manual (Click to Chat)', 'astra-child' ); ?>
		</p>

		<?php if ( 'auto' === $method ) : ?>
			<?php
			$sent     = $order->get_meta( '_gp_whatsapp_sent' );
			$error    = $order->get_meta( '_gp_whatsapp_error' );
			?>
			<p><strong><?php esc_html_e( 'Auto-Notify Status:', 'astra-child' ); ?></strong>
				<?php if ( '1' === $sent ) : ?>
					<span style="color:#0B5D3B;"><?php esc_html_e( '✅ Sent', 'astra-child' ); ?></span>
				<?php elseif ( '0' === $sent ) : ?>
					<span style="color:#dc3232;"><?php esc_html_e( '❌ Failed', 'astra-child' ); ?></span>
					<?php if ( $error ) : ?>
						<pre style="font-size:11px;white-space:pre-wrap;background:#f0f0f1;padding:8px;border-radius:3px;margin:4px 0;"><?php echo esc_html( $error ); ?></pre>
					<?php endif; ?>
				<?php else : ?>
					<span style="color:#999;"><?php esc_html_e( 'Not sent', 'astra-child' ); ?></span>
				<?php endif; ?>
			</p>

			<?php if ( '1' !== $sent ) : ?>
				<p><a href="<?php echo esc_url( self::resend_url( $order ) ); ?>" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Resend WhatsApp notification?', 'astra-child' ); ?>');">📲 <?php esc_html_e( 'Resend Now', 'astra-child' ); ?></a></p>
			<?php endif; ?>
		<?php endif; ?>

		<p><?php esc_html_e( 'Send this order to your WhatsApp:', 'astra-child' ); ?></p>
		<p>
			<a href="<?php echo esc_url( self::click_to_chat_url( $order ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
				📲 <?php esc_html_e( 'Send to WhatsApp', 'astra-child' ); ?>
			</a>
		</p>
		<details>
			<summary><?php esc_html_e( 'Preview message', 'astra-child' ); ?></summary>
			<pre style="font-size:12px;white-space:pre-wrap;margin-top:8px;background:#f0f0f1;padding:12px;border-radius:4px;"><?php echo esc_html( GP_WhatsApp_Message::build( $order ) ); ?></pre>
		</details>
		<?php
	}

	private static function click_to_chat_url( $order ) {
		$number  = get_option( 'gp_whatsapp_number', GP_WHATSAPP_DEFAULT_NUMBER );
		$message = GP_WhatsApp_Message::build( $order );
		return 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $number ) . '?text=' . rawurlencode( $message );
	}

	private static function resend_url( $order ) {
		return wp_nonce_url(
			add_query_arg( array(
				'gp_resend' => $order->get_id(),
			), admin_url( 'admin-post.php?action=gp_whatsapp_resend' ) ),
			'gp_whatsapp_resend_' . $order->get_id()
		);
	}
}
GP_WhatsApp_AdminOrder::init();

/**
 * ─── ADMIN RESEND HANDLER ─────────────────────────────
 */
class GP_WhatsApp_Resend {

	public static function init() {
		add_action( 'admin_post_gp_whatsapp_resend', array( __CLASS__, 'handle' ) );
	}

	public static function handle() {
		$order_id = absint( $_GET['gp_resend'] ?? 0 );
		if ( ! $order_id ) {
			wp_die( 'Invalid order.' );
		}

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'gp_whatsapp_resend_' . $order_id ) ) {
			wp_die( 'Security check failed.' );
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized.' );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( 'Order not found.' );
		}

		$number  = get_option( 'gp_whatsapp_number', GP_WHATSAPP_DEFAULT_NUMBER );
		$message = GP_WhatsApp_Message::build( $order );
		$result  = GP_WhatsApp_Cloud_API::send( $number, $message );

		$order->update_meta_data( '_gp_whatsapp_sent', $result['success'] ? '1' : '0' );
		if ( ! $result['success'] && isset( $result['error'] ) ) {
			$order->update_meta_data( '_gp_whatsapp_error', $result['error'] );
		} else {
			$order->delete_meta_data( '_gp_whatsapp_error' );
		}
		$order->save();

		$redirect = add_query_arg( array(
			'post'   => $order_id,
			'action' => 'edit',
		), admin_url( 'post.php' ) );

		wp_safe_redirect( $redirect );
		exit;
	}
}
GP_WhatsApp_Resend::init();

/**
 * ─── FRONT-END STYLES ─────────────────────────────────
 */
class GP_WhatsApp_Styles {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'inline_styles' ) );
	}

	public static function inline_styles() {
		if ( ! is_checkout() && ! is_order_received_page() ) {
			return;
		}
		?>
		<style>
		.gp-whatsapp-thankyou {
			max-width: 560px;
			margin: 32px auto 0;
			padding: 36px 28px;
			background: #F8F9FA;
			border: 1px solid #E5E7EB;
			border-radius: 10px;
			text-align: center;
		}
		.gp-whatsapp-thankyou h3 {
			font-size: 24px;
			font-weight: 700;
			color: #0B5D3B;
			margin: 0 0 8px;
		}
		.gp-whatsapp-thankyou p {
			font-size: 15px;
			color: #666;
			margin: 0 0 24px;
			line-height: 1.6;
		}
		.gp-whatsapp-btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 14px 32px;
			background: #25D366;
			color: #fff !important;
			font-size: 16px;
			font-weight: 600;
			font-family: inherit;
			border-radius: 10px;
			text-decoration: none;
			transition: all 0.3s ease;
			border: 0;
			cursor: pointer;
		}
		.gp-whatsapp-btn:hover {
			background: #1DA851;
			color: #fff !important;
			transform: translateY(-2px);
			box-shadow: 0 4px 16px rgba(37,211,102,0.30);
			text-decoration: none;
		}
		.gp-whatsapp-help {
			font-size: 13px !important;
			color: #999 !important;
			margin-top: 16px !important;
			margin-bottom: 0 !important;
		}
		.gp-whatsapp-error-note {
			font-size: 13px !important;
			color: #856404 !important;
			background: #fff3cd;
			padding: 8px 12px;
			border-radius: 4px;
			margin-bottom: 16px !important;
		}
		.gp-whatsapp-auto-sent h3 {
			color: #0B5D3B;
		}
		.gp-whatsapp-auto-sent p {
			margin-bottom: 0;
		}
		</style>
		<?php
	}
}
GP_WhatsApp_Styles::init();

/**
 * ─── HELPER — public URL builder ──────────────────────
 *
 * Used by functions.php (product page WhatsApp button) and elsewhere.
 * Reads from the same shared option so changing the number in
 * WooCommerce → WhatsApp updates it everywhere.
 */
if ( ! function_exists( 'gp_whatsapp_url' ) ) {
	function gp_whatsapp_url( $text = '' ) {
		$number = get_option( 'gp_whatsapp_number', GP_WHATSAPP_DEFAULT_NUMBER );
		$number = apply_filters( 'gp_whatsapp_number', $number );
		$number = preg_replace( '/[^0-9]/', '', $number );
		if ( $text ) {
			$text = '?text=' . rawurlencode( $text );
		}
		return 'https://wa.me/' . $number . $text;
	}
}
