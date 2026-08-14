<?php
/**
 * GPN CRM - User Management page (+ activity log).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_current_user = $current_user;
$gpn_roles        = gpn_roles();

require GPN_CRM_DIR . 'templates/header.php';
?>
<section class="gpn-panel">
	<h3 class="gpn-panel-title">CRM Users &amp; Roles</h3>
	<p class="gpn-note">
		Roles: <strong>Administrator</strong> (full access), <strong>BC / GC / CT / TA</strong>
		(can add sadhaks and edit sadhaks only in groups where they are assigned as the role-holder).
		These are CRM users used to log into this plugin's login screen.
	</p>

	<div class="gpn-table-wrapper">
		<table class="gpn-table" id="gpnUserTable">
			<thead>
				<tr>
					<th>Full Name</th><th>Email</th><th>Username</th><th>Role</th><th>Active</th><th>Created</th><th>Updated</th>
				</tr>
			</thead>
			<tbody id="gpnUserBody"><tr><td colspan="7" class="gpn-empty">Loading...</td></tr></tbody>
		</table>
	</div>

	<div class="gpn-btn-row">
		<button type="button" class="gpn-btn gpn-btn-primary" id="gpnAddUserBtn">Add User</button>
		<button type="button" class="gpn-btn gpn-btn-info" id="gpnEditUserBtn">Edit</button>
		<button type="button" class="gpn-btn gpn-btn-danger" id="gpnDeleteUserBtn">Delete</button>
		<button type="button" class="gpn-btn gpn-btn-secondary" id="gpnRefreshUsersBtn">Refresh</button>
	</div>
</section>

<section class="gpn-panel">
	<h3 class="gpn-panel-title">Recent Activity Log</h3>
	<div class="gpn-table-wrapper">
		<table class="gpn-table" id="gpnLogTable">
			<thead>
				<tr><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP</th><th>Time</th></tr>
			</thead>
			<tbody id="gpnLogBody"><tr><td colspan="6" class="gpn-empty">Loading...</td></tr></tbody>
		</table>
	</div>
	<div class="gpn-btn-row">
		<button type="button" class="gpn-btn gpn-btn-secondary" id="gpnRefreshLogsBtn">Refresh Logs</button>
		<button type="button" class="gpn-btn gpn-btn-danger" id="gpnClearLogsBtn">Clear Logs</button>
	</div>
</section>

<div class="gpn-modal-overlay" id="gpnUserModal">
	<div class="gpn-modal gpn-modal-sm">
		<div class="gpn-modal-header">
			<h3 id="gpnUserModalTitle">Add User</h3>
			<button type="button" class="gpn-modal-close" data-close="gpnUserModal">&times;</button>
		</div>
		<div class="gpn-modal-body">
			<form id="gpnUserForm" autocomplete="off" novalidate>
				<input type="hidden" id="gpnUserId" value="">
				<div class="gpn-field">
					<label for="gpnUserFullName">Full Name *</label>
					<input type="text" id="gpnUserFullName" required>
				</div>
				<div class="gpn-field">
					<label for="gpnUserEmail">Email Address *</label>
					<input type="email" id="gpnUserEmail" required>
				</div>
				<div class="gpn-field">
					<label for="gpnUserUsername">Username *</label>
					<input type="text" id="gpnUserUsername" required autocomplete="off">
				</div>
				<div class="gpn-field">
					<label for="gpnUserPassword">Password <span class="gpn-muted">(leave empty to keep on edit)</span></label>
					<input type="password" id="gpnUserPassword" autocomplete="new-password">
				</div>
				<div class="gpn-field">
					<label for="gpnUserConfirmPassword">Confirm Password *</label>
					<input type="password" id="gpnUserConfirmPassword" autocomplete="new-password">
				</div>
				<div class="gpn-field">
					<label for="gpnUserRole">Role</label>
					<select id="gpnUserRole">
						<?php foreach ( $gpn_roles as $role ) : ?>
							<option value="<?php echo esc_attr( $role ); ?>"><?php echo esc_html( gpn_role_label( $role ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="gpn-switch-field">
					<label class="gpn-switch">
						<input type="checkbox" id="gpnUserActive" checked>
						<span class="gpn-slider"></span>
					</label>
					<div><strong>Active</strong><p>Inactive users cannot log in.</p></div>
				</div>
				<div class="gpn-btn-row">
					<button type="submit" class="gpn-btn gpn-btn-primary" id="gpnSaveUserBtn">Save User</button>
					<button type="button" class="gpn-btn gpn-btn-secondary" data-close="gpnUserModal">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php
require GPN_CRM_DIR . 'templates/footer.php';
