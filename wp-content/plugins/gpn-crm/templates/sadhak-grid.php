<?php
/**
 * GPN CRM - Sadhak data grid.
 * Mirrors the desktop Sadhak list (search, group filter, toolbar buttons,
 * full column set, sorting, pagination). Rendered client-side by admin.js.
 *
 * Expects: $current_user
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_is_admin = ( 'Admin' === $current_user['role'] );
$gpn_groups   = GPN_Group::instance()->get_all();
?>
<section class="gpn-panel">
	<h3 class="gpn-panel-title">Saved Sadhak Records</h3>

	<div class="gpn-filter-bar">
		<input type="text" id="gpnSearchInput" placeholder="Search name, phone, email, PRN...">
		<select id="gpnFilterGroup">
			<option value="">All Groups</option>
			<?php foreach ( $gpn_groups as $g ) : ?>
				<option value="<?php echo esc_attr( $g['id'] ); ?>"><?php echo esc_html( $g['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="gpn-btn gpn-btn-secondary" id="gpnClearFilterBtn">Clear</button>
	</div>

	<div class="gpn-toolbar">
		<span class="gpn-total-label" id="gpnTotalLabel"></span>
		<div class="gpn-toolbar-actions">
			<button type="button" class="gpn-btn gpn-btn-info" id="gpnEditBtn">Edit</button>
			<button type="button" class="gpn-btn gpn-btn-success" id="gpnWhatsappBtn">WhatsApp</button>
			<button type="button" class="gpn-btn gpn-btn-secondary" id="gpnHistoryBtn">History</button>
			<button type="button" class="gpn-btn gpn-btn-danger" id="gpnDeleteBtn">Delete</button>
			<button type="button" class="gpn-btn gpn-btn-secondary" id="gpnRefreshBtn">Refresh</button>
		</div>
	</div>

	<div class="gpn-table-wrapper">
		<table class="gpn-table" id="gpnSadhakTable">
			<thead>
				<tr>
					<th data-orderby="name">Name</th>
					<th data-orderby="phone">Phone</th>
					<th data-orderby="email">Email</th>
					<th data-orderby="prn">PRN</th>
					<th data-orderby="group_name">Group</th>
					<th data-orderby="level">Level</th>
					<th data-orderby="batch">Batch</th>
					<th data-orderby="bc_name">BC</th>
					<th data-orderby="gc_name">GC</th>
					<th data-orderby="ct_name">CT</th>
					<th data-orderby="ta_name">TA</th>
					<th data-orderby="created_at">Created At</th>
					<th data-orderby="updated_at">Updated At</th>
					<th data-orderby="created_by_name">Created By</th>
					<th data-orderby="updated_by_name">Last Updated By</th>
				</tr>
			</thead>
			<tbody id="gpnSadhakBody">
				<tr><td colspan="15" class="gpn-empty">Loading...</td></tr>
			</tbody>
		</table>
	</div>

	<div class="gpn-pagination">
		<button type="button" class="gpn-btn gpn-btn-secondary gpn-btn-sm" id="gpnPrevPage">Prev</button>
		<span id="gpnPageInfo">Page 1</span>
		<button type="button" class="gpn-btn gpn-btn-secondary gpn-btn-sm" id="gpnNextPage">Next</button>
	</div>
</section>
