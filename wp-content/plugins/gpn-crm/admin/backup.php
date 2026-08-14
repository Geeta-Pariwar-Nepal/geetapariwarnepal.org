<?php
/**
 * GPN CRM - Backup page (create, list, download, restore).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_current_user = $current_user;
$gpn_backups      = GPN_Backup::instance()->list_backups();
$gpn_restore_nonce = wp_create_nonce( 'gpn_crm_backup_restore' );

require GPN_CRM_DIR . 'templates/header.php';
?>
<section class="gpn-panel">
	<h3 class="gpn-panel-title">Database Backup (JSON)</h3>
	<p class="gpn-note">
		Creates a full JSON snapshot of users, groups, sadhaks and history.
		Backups are stored under <code>wp-content/uploads/gpn-crm/backups</code>.
		Restoring replaces the current data after making a safety backup of it.
	</p>

	<div class="gpn-btn-row">
		<button type="button" class="gpn-btn gpn-btn-primary" id="gpnCreateBackupBtn">
			<span class="dashicons dashicons-backup"></span> Create Backup
		</button>
	</div>
	<div class="gpn-status-text" id="gpnBackupStatus">Ready</div>
</section>

<section class="gpn-panel">
	<h3 class="gpn-panel-title">Existing Backups</h3>
	<div class="gpn-table-wrapper">
		<table class="gpn-table" id="gpnBackupTable">
			<thead>
				<tr><th>File</th><th>Size</th><th>Created</th><th>Actions</th></tr>
			</thead>
			<tbody id="gpnBackupBody">
				<?php if ( empty( $gpn_backups ) ) : ?>
					<tr><td colspan="4" class="gpn-empty">No backups yet.</td></tr>
				<?php else : ?>
					<?php foreach ( $gpn_backups as $b ) : ?>
						<tr>
							<td><?php echo esc_html( $b['name'] ); ?></td>
							<td><?php echo esc_html( size_format( (int) $b['size'] ) ); ?></td>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $b['modified'] ) ); ?></td>
							<td class="gpn-row-actions">
								<a class="gpn-btn gpn-btn-info gpn-btn-sm" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gpn-crm-backup&download=' . rawurlencode( $b['name'] ) ), 'gpn_crm_backup_download' ) ); ?>">Download</a>
								<button type="button" class="gpn-btn gpn-btn-warning gpn-btn-sm gpn-restore-backup" data-name="<?php echo esc_attr( $b['name'] ); ?>">Restore</button>
								<button type="button" class="gpn-btn gpn-btn-danger gpn-btn-sm gpn-delete-backup" data-name="<?php echo esc_attr( $b['name'] ); ?>">Delete</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>

<section class="gpn-panel">
	<h3 class="gpn-panel-title">Restore from Uploaded File</h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="gpn_crm_backup_restore">
		<?php wp_nonce_field( 'gpn_crm_backup_restore' ); ?>
		<div class="gpn-field">
			<label for="gpnBackupFile">Choose a .json backup file</label>
			<input type="file" id="gpnBackupFile" name="gpn_backup_file" accept=".json" required>
		</div>
		<div class="gpn-btn-row">
			<button type="submit" class="gpn-btn gpn-btn-warning">
				<span class="dashicons dashicons-restore"></span> Upload &amp; Restore
			</button>
		</div>
	</form>
</section>
<?php
require GPN_CRM_DIR . 'templates/footer.php';
