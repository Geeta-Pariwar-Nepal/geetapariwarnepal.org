<?php
/**
 * GPN CRM - Sadhak registration / edit form.
 * Mirrors the desktop registration panel (auto PRN search, group role
 * holder display, status line, Zoom button).
 *
 * Expects: $current_user
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpn_countries = gpn_country_codes();
$gpn_cnames    = gpn_country_names();
$gpn_default   = GPN_Settings::instance()->get( 'default_country', '+977' );
$gpn_groups    = GPN_Group::instance()->get_all();
?>
<section class="gpn-panel gpn-panel-form">
	<h3 class="gpn-panel-title" id="gpnFormTitle">Register / Edit Sadhak</h3>

	<form id="gpnSadhakForm" autocomplete="off" novalidate>
		<input type="hidden" id="gpnEditingId" value="">

		<div class="gpn-field">
			<label for="gpnPhone">Mobile Number *</label>
			<div class="gpn-phone-row">
				<select id="gpnCountryCode">
					<?php foreach ( $gpn_countries as $code ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $gpn_default ); ?>>
							<?php echo esc_html( $code . ' ' . ( isset( $gpn_cnames[ $code ] ) ? $gpn_cnames[ $code ] : '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="tel" id="gpnPhone" required autofocus>
			</div>
			<p class="gpn-field-note">
				<span class="gpn-note-icon">💡</span>
				Enter the mobile number without the country code (e.g., 9864026061). Name and PRN will be filled automatically if the sadhak already exists.
			</p>
		</div>

		<div class="gpn-field">
			<label for="gpnName">Full Name *</label>
			<input type="text" id="gpnName" required>
		</div>

		<div class="gpn-field">
			<label for="gpnEmail">Email</label>
			<input type="email" id="gpnEmail">
		</div>

		<div class="gpn-field">
			<label for="gpnPrn">PRN (auto-searched)</label>
			<input type="text" id="gpnPrn">
		</div>

		<div class="gpn-field">
			<label for="gpnGroup">Group</label>
			<div class="gpn-group-row">
				<select id="gpnGroup">
					<option value="">-- Select --</option>
					<?php foreach ( $gpn_groups as $g ) : ?>
						<option value="<?php echo esc_attr( $g['id'] ); ?>" data-level="<?php echo esc_attr( $g['level'] ); ?>" data-batch="<?php echo esc_attr( $g['batch'] ); ?>">
							<?php echo esc_html( $g['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="gpn-badge gpn-badge-info" id="gpnLevelDisplay"></span>
				<span class="gpn-badge gpn-badge-secondary" id="gpnBatchDisplay"></span>
				<button type="button" class="gpn-btn gpn-btn-success gpn-btn-sm" id="gpnZoomBtn" disabled>Zoom</button>
			</div>
		</div>

		<div class="gpn-role-holders">
			<p><strong>BC:</strong> <span id="gpnBcDisplay">—</span></p>
			<p><strong>GC:</strong> <span id="gpnGcDisplay">—</span></p>
			<p><strong>CT:</strong> <span id="gpnCtDisplay">—</span></p>
			<p><strong>TA:</strong> <span id="gpnTaDisplay">—</span></p>
		</div>

		<div class="gpn-status-text" id="gpnFormStatus">
			<span class="gpn-spinner" id="gpnFormSpinner" style="display:none"></span>
			<span id="gpnFormStatusText">Ready</span>
		</div>

		<div class="gpn-btn-row">
			<button type="submit" class="gpn-btn gpn-btn-success" id="gpnSaveBtn">Save Sadhak</button>
			<button type="button" class="gpn-btn gpn-btn-secondary" id="gpnClearBtn">Cancel</button>
		</div>
	</form>
</section>
