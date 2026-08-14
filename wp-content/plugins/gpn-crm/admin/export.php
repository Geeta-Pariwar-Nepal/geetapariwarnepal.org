<?php
/**
 * GPN CRM - Export page (CSV / Excel).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_current_user = $current_user;
$gpn_export_nonce = wp_create_nonce( 'gpn_crm_export' );
$gpn_csv_url      = admin_url( 'admin.php?page=gpn-crm-export&download=1&_wpnonce=' . $gpn_export_nonce );
$gpn_xlsx_url     = admin_url( 'admin.php?page=gpn-crm-export&xlsx=1&_wpnonce=' . $gpn_export_nonce );

require GPN_CRM_DIR . 'templates/header.php';
?>
<section class="gpn-panel">
	<h3 class="gpn-panel-title">Export Sadhaks (CSV / Excel)</h3>
	<p class="gpn-note">
		Exports the complete sadhak list with all grid columns:
		Name, Mobile, Email, PRN, Group, Level, Batch, BC, GC, CT, TA, Status,
		Created At, Updated At, Created By, Updated By.
	</p>
	<div class="gpn-btn-row">
		<a class="gpn-btn gpn-btn-success" href="<?php echo esc_url( $gpn_csv_url ); ?>">
			<span class="dashicons dashicons-media-spreadsheet"></span> Download CSV
		</a>
		<a class="gpn-btn gpn-btn-primary" href="<?php echo esc_url( $gpn_xlsx_url ); ?>">
			<span class="dashicons dashicons-media-spreadsheet"></span> Download Excel (.xlsx)
		</a>
	</div>
</section>
<?php
require GPN_CRM_DIR . 'templates/footer.php';
