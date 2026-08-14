<?php
/**
 * Activation & deactivation routines.
 *
 * @package Geeta_Pariwar_Parayan_Booking
 */

defined( 'ABSPATH' ) || exit;

class GPPB_Activator {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			deactivate_plugins( GPPB_BASENAME );
			wp_die( esc_html__( 'Geeta Pariwar Parayan Booking Engine requires PHP 8.0 or higher.', 'geeta-parayan-booking' ) );
		}
		GPPB_Database::instance()->install();
		self::setup_capabilities();
		self::flush_permalinks();
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::flush_permalinks();
	}

	/**
	 * Create the shared "Sadhak Manager" role and grant the management
	 * capability to administrators plus the official contact address user.
	 *
	 * @return void
	 */
	private static function setup_capabilities() {
		$cap = GPPB_Helpers::capability();

		/* Dedicated shared-admin role. */
		$role = get_role( 'gppb_sadhak_manager' );
		if ( ! $role ) {
			add_role(
				'gppb_sadhak_manager',
				__( 'Sadhak Manager', 'geeta-parayan-booking' ),
				array(
					$cap          => true,
					'read'        => true,
					'level_0'     => true,
				)
			);
		} else {
			$role->add_cap( $cap );
		}

		/* Administrators can always manage. */
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( $cap );
		}

		/* Grant the capability to the official contact account if it exists. */
		$contact = get_user_by( 'email', (string) GPPB_Helpers::get_setting( 'admin_email', 'contact@geetapariwarnepal.org' ) );
		if ( $contact instanceof WP_User ) {
			$contact->add_cap( $cap );
		}
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @return void
	 */
	private static function flush_permalinks() {
		flush_rewrite_rules();
	}
}
