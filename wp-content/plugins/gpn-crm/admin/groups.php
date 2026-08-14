<?php
/**
 * GPN CRM - Groups management page.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_current_user = $current_user;
$gpn_is_admin     = ( 'Admin' === $gpn_current_user['role'] );

require GPN_CRM_DIR . 'templates/header.php';
?>
<section class="gpn-panel">
	<h3 class="gpn-panel-title">Manage Groups / Levels</h3>

	<div class="gpn-table-wrapper">
		<table class="gpn-table" id="gpnGroupTable">
			<thead>
				<tr>
					<th>Group</th><th>Level</th><th>Batch</th><th>Timing</th>
					<th>BC</th><th>GC</th><th>CT</th><th>TA</th><th>Status</th><th>Zoom Link</th>
				</tr>
			</thead>
			<tbody id="gpnGroupBody"><tr><td colspan="10" class="gpn-empty">Loading...</td></tr></tbody>
		</table>
	</div>

	<div class="gpn-btn-row">
		<?php if ( $gpn_is_admin ) : ?>
			<button type="button" class="gpn-btn gpn-btn-primary" id="gpnAddGroupBtn">Add Group</button>
			<button type="button" class="gpn-btn gpn-btn-info" id="gpnEditGroupBtn">Edit</button>
			<button type="button" class="gpn-btn gpn-btn-danger" id="gpnDeleteGroupBtn">Delete</button>
		<?php else : ?>
			<p class="gpn-empty">Only Administrators can add, edit or delete groups.</p>
		<?php endif; ?>
		<button type="button" class="gpn-btn gpn-btn-success" id="gpnOpenGroupZoomBtn">Open Zoom</button>
		<button type="button" class="gpn-btn gpn-btn-secondary" id="gpnRefreshGroupsBtn">Refresh</button>
	</div>
</section>

<?php
require GPN_CRM_DIR . 'templates/modals.php';
require GPN_CRM_DIR . 'templates/footer.php';
