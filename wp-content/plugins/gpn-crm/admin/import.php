<?php
/**
 * GPN CRM - Import page (CSV / Excel / SQLite .db).
 * Two-step flow: upload -> preview column mapping -> import -> report.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_current_user = $current_user;
$gpn_import_nonce = wp_create_nonce( 'gpn_crm_import' );

require GPN_CRM_DIR . 'templates/header.php';
?>
<section class="gpn-panel">
	<h3 class="gpn-panel-title">Import Sadhaks</h3>

	<div class="gpn-alert gpn-alert-info">
		<strong>Required:</strong> <code>Name</code> and <code>Phone</code> (or <code>Mobile</code>).
		Optional: <code>Email</code>, <code>PRN</code>, <code>Group</code>, <code>Level</code>,
		<code>Batch</code>, <code>BC</code>, <code>GC</code>, <code>CT</code>, <code>TA</code>, <code>Status</code>.
		Column names are recognised flexibly (e.g. <code>Mobile Number</code>, <code>Full Name</code>, <code>Registration No</code>)
		and can be adjusted in the preview. Rows are matched by phone number: existing numbers are <strong>updated</strong>,
		new numbers are <strong>added</strong> — never duplicated.
	</div>

	<div id="gpnImportStepUpload">
		<form id="gpnImportForm" enctype="multipart/form-data">
			<div class="gpn-field">
				<label for="gpnImportFile">Choose CSV, Excel (.xlsx) or SQLite (.db) file</label>
				<input type="file" id="gpnImportFile" name="file" accept=".csv,.xlsx,.xls,.db,.sqlite,.sqlite3" required>
			</div>
			<div class="gpn-btn-row">
				<button type="submit" class="gpn-btn gpn-btn-primary" id="gpnImportPreviewBtn">
					<span class="dashicons dashicons-visibility"></span> Preview File
				</button>
			</div>
			<p class="gpn-status-text" id="gpnImportStatus">
				<span class="gpn-spinner" id="gpnImportSpinner" style="display:none"></span>
				<span id="gpnImportStatusText">Ready</span>
			</p>
		</form>
	</div>

	<div id="gpnImportStepPreview" class="gpn-import-panel" style="display:none">
		<h4 class="gpn-panel-title">Step 2 — Column Mapping</h4>
		<p class="gpn-note" id="gpnImportPreviewNote"></p>
		<div id="gpnImportMapping"></div>
		<div id="gpnImportSamples" class="gpn-import-samples"></div>
		<div class="gpn-btn-row">
			<button type="button" class="gpn-btn gpn-btn-success" id="gpnImportRunBtn">
				<span class="dashicons dashicons-upload"></span> <span id="gpnImportRunLabel">Import</span>
			</button>
			<button type="button" class="gpn-btn gpn-btn-secondary" id="gpnImportCancelBtn">Cancel</button>
		</div>
	</div>

	<div id="gpnImportStepReport" class="gpn-import-panel" style="display:none">
		<h4 class="gpn-panel-title">Import Report</h4>
		<div id="gpnImportReport"></div>
		<div class="gpn-btn-row">
			<button type="button" class="gpn-btn gpn-btn-primary" id="gpnImportLogBtn">
				<span class="dashicons dashicons-media-text"></span> Download Log (.txt)
			</button>
			<button type="button" class="gpn-btn gpn-btn-secondary" id="gpnImportAgainBtn">Import Another File</button>
		</div>
	</div>
</section>
<?php
require GPN_CRM_DIR . 'templates/footer.php';
