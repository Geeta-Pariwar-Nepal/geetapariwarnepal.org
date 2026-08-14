<?php
/**
 * GPN CRM - Sadhaks list page (grid + filters + toolbar + modals).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_current_user = $current_user;

require GPN_CRM_DIR . 'templates/header.php';

require GPN_CRM_DIR . 'templates/sadhak-grid.php';
require GPN_CRM_DIR . 'templates/modals.php';

require GPN_CRM_DIR . 'templates/footer.php';
