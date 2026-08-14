<?php

namespace Vivechan;

defined('ABSPATH') || exit;

/**
 * Bootstraps all plugin subsystems.
 */
final class Plugin {

	const PROVISIONED = 'vivechan_provisioned_version';

	public static function init() {
		add_action( 'init', array( Services\Publication::class, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'maybe_provision' ), 20 );
		add_filter( 'template_include', array( Services\Publication::class, 'single_template' ) );

		Cron\Cron::register();
		Rest\RestApi::register();
		Shortcode\Shortcode::register();
		Shortcode\IndexShortcode::register();

		// Owns the whole wp-admin menu.
		Admin\AdminApp::register();
	}

	/**
	 * Re-run role/capability/page provisioning after an upgrade.
	 *
	 * Upgrading through "Replace current with uploaded" never fires the
	 * activation hook, so a release that adds a capability would otherwise
	 * never reach existing installs. Keying the marker on the version means
	 * each release provisions exactly once.
	 *
	 * Runs on `init` at priority 20, not on `plugins_loaded`: core post types
	 * are not registered before `init`, so creating the index page there
	 * silently failed — and the previous flag was written whether or not it
	 * succeeded, so it never retried.
	 */
	public static function maybe_provision() {
		if ( get_option( self::PROVISIONED ) === VIVECHAN_VERSION ) {
			return;
		}

		// dbDelta is idempotent, so this also creates tables added by a release
		// on installs upgraded through "Replace current with uploaded".
		Activator::create_tables();
		Activator::migrate();
		Activator::add_roles_and_caps();
		Services\Publication::add_caps();
		Services\Publication::ensure_index_page();

		// Earlier releases published into a custom post type; convert those to
		// ordinary posts and file them under their chapter's category.
		Services\Publication::migrate_legacy_posts();

		// And re-parent chapter categories created before levels existed.
		Services\Publication::ensure_chapter_taxonomy();

		update_option( self::PROVISIONED, VIVECHAN_VERSION );
	}
}
