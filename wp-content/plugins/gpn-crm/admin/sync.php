<?php
/**
 * GPN CRM - Sync page (WordPress REST API sync: pull / push).
 *
 * Converts the desktop "Sync from Web" feature into REST-based sync between
 * two WordPress sites running this plugin.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_current_user = $current_user;
$gpn_settings     = GPN_Settings::instance();
$gpn_token        = $gpn_settings->sync_token();
$gpn_endpoint     = rest_url( GPN_Sync::REST_NAMESPACE . '/sync' );

require GPN_CRM_DIR . 'templates/header.php';
?>
<section class="gpn-panel">
	<h3 class="gpn-panel-title">Sync from Web (WordPress REST API)</h3>
	<p class="gpn-note">
		Pull or push the full CRM database between two WordPress sites running this plugin.
		On the remote site enable sync in <strong>Settings &rarr; Sync</strong> and use its
		sync token here. Pulling replaces this site's data (a safety backup is created first).
	</p>

	<div class="gpn-sync-form">
		<div class="gpn-field">
			<label for="gpnSyncUrl">Remote WordPress URL</label>
			<input type="url" id="gpnSyncUrl" placeholder="https://geetapariwarnepal.org">
		</div>
		<div class="gpn-field">
			<label for="gpnSyncUsername">Remote CRM Username (Admin)</label>
			<input type="text" id="gpnSyncUsername" autocomplete="off">
		</div>
		<div class="gpn-field">
			<label for="gpnSyncPassword">Remote CRM Password</label>
			<input type="password" id="gpnSyncPassword" autocomplete="off">
		</div>
		<div class="gpn-field">
			<label for="gpnSyncToken">Remote Sync Token</label>
			<input type="text" id="gpnSyncToken">
		</div>

		<div class="gpn-btn-row">
			<button type="button" class="gpn-btn gpn-btn-info" id="gpnSyncPullBtn">
				<span class="dashicons dashicons-download"></span> Pull from Web
			</button>
			<button type="button" class="gpn-btn gpn-btn-primary" id="gpnSyncPushBtn">
				<span class="dashicons dashicons-upload"></span> Push to Web
			</button>
		</div>
		<div class="gpn-status-text" id="gpnSyncStatus">Ready</div>
	</div>
</section>

<section class="gpn-panel">
	<h3 class="gpn-panel-title">This Site's REST Endpoint</h3>
	<p class="gpn-note">Other sites running this plugin can sync to your site using:</p>
	<div class="gpn-code-box">
		<code id="gpnSyncEndpoint"><?php echo esc_html( $gpn_endpoint ); ?></code>
	</div>
	<p class="gpn-note">Sync token for this site (used by the remote side when pulling from you):</p>
	<div class="gpn-code-box">
		<code id="gpnSyncTokenValue"><?php echo esc_html( $gpn_token ); ?></code>
	</div>
</section>
<?php
require GPN_CRM_DIR . 'templates/footer.php';
