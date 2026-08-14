<?php
/**
 * GPN CRM - shared modals (group manager, group editor, history).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_user_names = GPN_User::instance()->active_names();
?>
<div class="gpn-modal-overlay" id="gpnGroupModal">
	<div class="gpn-modal">
		<div class="gpn-modal-header">
			<h3>Manage Groups / Levels</h3>
			<button type="button" class="gpn-modal-close" data-close="gpnGroupModal">&times;</button>
		</div>
		<div class="gpn-modal-body">
			<div class="gpn-table-wrapper">
				<table class="gpn-table" id="gpnGroupTable">
					<thead>
						<tr>
							<th>Group</th><th>Level</th><th>Batch</th><th>Timing</th>
							<th>BC</th><th>GC</th><th>CT</th><th>TA</th><th>Status</th><th>Zoom</th>
						</tr>
					</thead>
					<tbody id="gpnGroupBody"><tr><td colspan="10" class="gpn-empty">Loading...</td></tr></tbody>
				</table>
			</div>
			<div class="gpn-btn-row">
				<button type="button" class="gpn-btn gpn-btn-primary" id="gpnAddGroupBtn">Add Group</button>
				<button type="button" class="gpn-btn gpn-btn-info" id="gpnEditGroupBtn">Edit</button>
				<button type="button" class="gpn-btn gpn-btn-danger" id="gpnDeleteGroupBtn">Delete</button>
				<button type="button" class="gpn-btn gpn-btn-success" id="gpnOpenGroupZoomBtn">Open Zoom</button>
				<button type="button" class="gpn-btn gpn-btn-secondary" data-close="gpnGroupModal">Close</button>
			</div>
		</div>
	</div>
</div>

<div class="gpn-modal-overlay" id="gpnGroupEditModal">
	<div class="gpn-modal gpn-modal-sm">
		<div class="gpn-modal-header">
			<h3 id="gpnGroupEditTitle">New Group</h3>
			<button type="button" class="gpn-modal-close" data-close="gpnGroupEditModal">&times;</button>
		</div>
		<div class="gpn-modal-body">
			<form id="gpnGroupForm" autocomplete="off" novalidate>
				<input type="hidden" id="gpnGroupEditId" value="">
				<div class="gpn-field">
					<label for="gpnGroupName">Level / Group Name *</label>
					<input type="text" id="gpnGroupName" required>
				</div>
				<div class="gpn-field">
					<label for="gpnGroupLevel">Level</label>
					<select id="gpnGroupLevel">
						<option value="Level 1">Level 1</option>
						<option value="Level 2">Level 2</option>
						<option value="Level 3">Level 3</option>
						<option value="Level 4">Level 4</option>
					</select>
				</div>
				<div class="gpn-field">
					<label for="gpnGroupBatch">Batch</label>
					<select id="gpnGroupBatch">
						<option value="Regular">Regular</option>
						<option value="Kids">Kids</option>
					</select>
				</div>
				<div class="gpn-field">
					<label for="gpnGroupBc">BC (Batch Coordinator)</label>
					<input type="text" id="gpnGroupBc" list="gpnUserList">
				</div>
				<div class="gpn-field">
					<label for="gpnGroupGc">GC (Group Coordinator)</label>
					<input type="text" id="gpnGroupGc" list="gpnUserList">
				</div>
				<div class="gpn-field">
					<label for="gpnGroupCt">CT (Co-Teacher)</label>
					<input type="text" id="gpnGroupCt" list="gpnUserList">
				</div>
				<div class="gpn-field">
					<label for="gpnGroupTa">TA (Teaching Assistant)</label>
					<input type="text" id="gpnGroupTa" list="gpnUserList">
				</div>
				<div class="gpn-field">
					<label for="gpnGroupTiming">Class Timing</label>
					<input type="text" id="gpnGroupTiming">
				</div>
				<div class="gpn-field">
					<label for="gpnGroupZoom">Zoom Meeting Link</label>
					<input type="url" id="gpnGroupZoom">
				</div>
				<div class="gpn-field">
					<label for="gpnGroupStatus">Status</label>
					<select id="gpnGroupStatus">
						<option value="Active">Active</option>
						<option value="Inactive">Inactive</option>
					</select>
				</div>
				<datalist id="gpnUserList">
					<?php foreach ( $gpn_user_names as $uname ) : ?>
						<option value="<?php echo esc_attr( $uname ); ?>">
					<?php endforeach; ?>
				</datalist>
				<div class="gpn-btn-row">
					<button type="submit" class="gpn-btn gpn-btn-primary">Save Group</button>
					<button type="button" class="gpn-btn gpn-btn-secondary" data-close="gpnGroupEditModal">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="gpn-modal-overlay" id="gpnHistoryModal">
	<div class="gpn-modal">
		<div class="gpn-modal-header">
			<h3 id="gpnHistoryTitle">History</h3>
			<button type="button" class="gpn-modal-close" data-close="gpnHistoryModal">&times;</button>
		</div>
		<div class="gpn-modal-body">
			<div class="gpn-table-wrapper">
				<table class="gpn-table" id="gpnHistoryTable">
					<thead>
						<tr>
							<th>#</th><th>Group</th><th>Level</th><th>Batch</th>
							<th>BC</th><th>GC</th><th>CT</th><th>TA</th><th>Changed By</th><th>Date</th>
						</tr>
					</thead>
					<tbody id="gpnHistoryBody"><tr><td colspan="10" class="gpn-empty">No history yet.</td></tr></tbody>
				</table>
			</div>
		</div>
	</div>
</div>
