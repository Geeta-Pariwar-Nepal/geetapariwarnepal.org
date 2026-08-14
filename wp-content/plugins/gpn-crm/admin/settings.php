<?php
/**
 * GPN CRM - Settings page.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_current_user = $current_user;
$gpn_settings     = GPN_Settings::instance()->all();
$gpn_token        = GPN_Settings::instance()->sync_token();
$gpn_countries    = gpn_country_codes();
$gpn_cnames       = gpn_country_names();

require GPN_CRM_DIR . 'templates/header.php';
?>
<section class="gpn-panel gpn-single-col-inner">
	<h3 class="gpn-panel-title">CRM Settings</h3>

	<form id="gpnSettingsForm" autocomplete="off">
		<div class="gpn-field">
			<label for="gpnSetAppName">Application Name</label>
			<input type="text" id="gpnSetAppName" value="<?php echo esc_attr( $gpn_settings['app_name'] ); ?>">
		</div>
		<div class="gpn-field">
			<label for="gpnSetCountry">Default Country Code</label>
			<select id="gpnSetCountry">
				<?php foreach ( $gpn_countries as $code ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $gpn_settings['default_country'] ); ?>>
						<?php echo esc_html( $code . ' ' . ( isset( $gpn_cnames[ $code ] ) ? $gpn_cnames[ $code ] : '' ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="gpn-field">
			<label for="gpnSetPerPage">Sadhaks per page</label>
			<input type="number" id="gpnSetPerPage" min="5" max="500" value="<?php echo esc_attr( (int) $gpn_settings['per_page'] ); ?>">
		</div>

		<div class="gpn-switch-field">
			<label class="gpn-switch">
				<input type="checkbox" id="gpnSetPrnRemote" <?php checked( (int) $gpn_settings['prn_remote_search'] ); ?>>
				<span class="gpn-slider"></span>
			</label>
			<div><strong>LearnGeeta remote PRN search</strong><p>Query online.learngeeta.com when no local PRN match is found.</p></div>
		</div>

		<div class="gpn-field">
			<label for="gpnSetPrnTimeout">Remote PRN timeout (seconds)</label>
			<input type="number" id="gpnSetPrnTimeout" min="1" max="20" value="<?php echo esc_attr( (int) $gpn_settings['prn_remote_timeout'] ); ?>">
		</div>

		<div class="gpn-switch-field">
			<label class="gpn-switch">
				<input type="checkbox" id="gpnSetAutoBackup" <?php checked( (int) $gpn_settings['auto_backup'] ); ?>>
				<span class="gpn-slider"></span>
			</label>
			<div><strong>Automatic JSON backup</strong><p>Create a backup after every save/delete (mirrors the desktop's auto backup to Google Drive).</p></div>
		</div>

		<div class="gpn-field">
			<label for="gpnSetKeepBackups">Keep the latest N backups</label>
			<input type="number" id="gpnSetKeepBackups" min="1" max="200" value="<?php echo esc_attr( (int) $gpn_settings['keep_backups'] ); ?>">
		</div>

		<div class="gpn-switch-field">
			<label class="gpn-switch">
				<input type="checkbox" id="gpnSetWhatsapp" <?php checked( (int) $gpn_settings['whatsapp_enabled'] ); ?>>
				<span class="gpn-slider"></span>
			</label>
			<div><strong>Enable WhatsApp button</strong><p>Open https://wa.me/ using the selected record's number.</p></div>
		</div>

		<div class="gpn-field">
			<label for="gpnSetWhatsappPrefix">WhatsApp fallback prefix (used when a number has no country code)</label>
			<input type="text" id="gpnSetWhatsappPrefix" value="<?php echo esc_attr( $gpn_settings['whatsapp_prefix'] ); ?>">
		</div>

		<div class="gpn-switch-field">
			<label class="gpn-switch">
				<input type="checkbox" id="gpnSetSyncEnabled" <?php checked( (int) $gpn_settings['sync_enabled'] ); ?>>
				<span class="gpn-slider"></span>
			</label>
			<div><strong>Enable REST API sync</strong><p>Allow remote sites to pull/push CRM data using the sync token below.</p></div>
		</div>

		<div class="gpn-field">
			<label for="gpnSetSyncToken">Sync Token</label>
			<div class="gpn-input-btn-row">
				<input type="text" id="gpnSetSyncToken" value="<?php echo esc_attr( $gpn_token ); ?>" readonly>
				<button type="button" class="gpn-btn gpn-btn-secondary" id="gpnRegenToken">Regenerate</button>
			</div>
		</div>

		<div class="gpn-btn-row">
			<button type="submit" class="gpn-btn gpn-btn-primary">Save Settings</button>
		</div>
		<div class="gpn-status-text" id="gpnSettingsStatus">Ready</div>
	</form>
</section>
<?php
require GPN_CRM_DIR . 'templates/footer.php';
