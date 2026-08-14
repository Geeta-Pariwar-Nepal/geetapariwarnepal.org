<?php
/**
 * Plugin Name:       Geeta Pariwar Parayan Booking Engine
 * Plugin URI:        https://geetapariwarnepal.org
 * Description:       श्रीमद्भगवद्गीता पारायण बुकिङ इन्जिन — teacher-approval gateway, 18 Adhyaya daily/weekly slots, 2-person waitlist, late-cancellation policy, session links and a public dashboard.
 * Version:           2.7.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Geeta Pariwar Nepal
 * Author URI:        https://geetapariwarnepal.org
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       geeta-parayan-booking
 * Domain Path:       /languages
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */
define( 'GPPB_VERSION', '2.8.2' );
define( 'GPPB_FILE', __FILE__ );
define( 'GPPB_PATH', plugin_dir_path( __FILE__ ) );
define( 'GPPB_URL', plugin_dir_url( __FILE__ ) );
define( 'GPPB_BASENAME', plugin_basename( __FILE__ ) );
define( 'GPPB_DB_VERSION', '2.4.0' );
define( 'GPPB_ADHYAYAS_TOTAL', 18 );

/* -------------------------------------------------------------------------
 * Autoloader
 * Map GPPB_ClassName -> includes/class-class-name.php
 * ---------------------------------------------------------------------- */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'GPPB_';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', substr( $class, strlen( $prefix ) ) ) );
		$file = GPPB_PATH . 'includes/class-' . $slug . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

require_once GPPB_PATH . 'includes/helpers.php';

/* -------------------------------------------------------------------------
 * Activation / Deactivation hooks
 * ---------------------------------------------------------------------- */
register_activation_hook( __FILE__, array( 'GPPB_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GPPB_Activator', 'deactivate' ) );

/* -------------------------------------------------------------------------
 * Boot on plugins_loaded
 * ---------------------------------------------------------------------- */
add_action(
	'plugins_loaded',
	function () {
		GPPB_Database::instance();
		GPPB_Booking_Engine::instance();
		GPPB_Frontend_UI::instance();
		GPPB_Admin_Controller::instance();
		GPPB_Audit_Log::instance();
		GPPB_Mailer::register();
		load_plugin_textdomain( 'geeta-parayan-booking', false, dirname( GPPB_BASENAME ) . '/languages' );
	}
);

/* -------------------------------------------------------------------------
 * Upgrade check + session auto-completion
 * ---------------------------------------------------------------------- */
add_action(
	'init',
	function () {
		GPPB_Database::instance()->maybe_upgrade();
		GPPB_Booking_Engine::instance()->maybe_auto_complete();
	}
);
