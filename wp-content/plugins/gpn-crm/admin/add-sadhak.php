<?php
/**
 * GPN CRM - Add Sadhak page (registration form).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_current_user = $current_user;

require GPN_CRM_DIR . 'templates/header.php';
?>
<div class="gpn-single-col">
	<?php require GPN_CRM_DIR . 'templates/sadhak-form.php'; ?>

	<p class="gpn-note">
		<span class="dashicons dashicons-lightbulb"></span>
		Type a mobile number or email and the PRN + name are auto-searched from
		the local database and LearnGeeta. Select a group to auto-fill the BC, GC, CT
		and TA names.
	</p>
</div>
<?php
require GPN_CRM_DIR . 'templates/footer.php';
