<?php
/**
 * GPN CRM - shared helpers.
 *
 * Holds the country code / country name tables (identical to the desktop
 * application) and small utility functions used across the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All country codes, longest-first matching is handled at call sites.
 */
function gpn_country_codes() {
	return array(
		'+93', '+355', '+213', '+1684', '+376', '+244', '+1264', '+1268',
		'+54', '+374', '+297', '+61', '+43', '+994', '+1242', '+973',
		'+880', '+1246', '+375', '+32', '+501', '+229', '+1441', '+975',
		'+591', '+387', '+267', '+55', '+1284', '+673', '+359', '+226',
		'+257', '+855', '+237', '+1', '+238', '+1345', '+236', '+235',
		'+56', '+86', '+57', '+269', '+682', '+506', '+225',
		'+385', '+53', '+357', '+420', '+243', '+45', '+253', '+1767',
		'+1809', '+670', '+593', '+20', '+503', '+240', '+291', '+372',
		'+251', '+500', '+298', '+679', '+358', '+33', '+594', '+689',
		'+241', '+220', '+995', '+49', '+233', '+350', '+30', '+1473',
		'+590', '+1671', '+502', '+224', '+245', '+592', '+509', '+504',
		'+852', '+36', '+354', '+91', '+62', '+98', '+964', '+353',
		'+972', '+39', '+1876', '+81', '+962', '+7', '+254', '+686',
		'+383', '+965', '+996', '+856', '+371', '+961', '+266', '+231',
		'+218', '+423', '+370', '+352', '+853', '+261', '+265', '+60',
		'+960', '+223', '+356', '+692', '+222', '+230', '+262', '+52',
		'+691', '+373', '+377', '+976', '+382', '+1664', '+212', '+258',
		'+95', '+264', '+674', '+977', '+31', '+599', '+687', '+64',
		'+505', '+227', '+234', '+683', '+672', '+389', '+1670', '+47',
		'+968', '+92', '+680', '+970', '+507', '+675', '+595', '+51',
		'+63', '+48', '+351', '+1939', '+974', '+242', '+40',
		'+250', '+290', '+1869', '+1758', '+508',
		'+1784', '+685', '+378', '+239', '+966', '+221', '+381', '+248',
		'+232', '+65', '+1721', '+421', '+386', '+677', '+252', '+27',
		'+82', '+211', '+34', '+94', '+249', '+597', '+268', '+46',
		'+41', '+963', '+886', '+992', '+255', '+66', '+228', '+690',
		'+676', '+1868', '+216', '+90', '+993', '+1649', '+688', '+256',
		'+380', '+971', '+44', '+598', '+998', '+678', '+379',
		'+58', '+84', '+1340', '+681', '+967', '+260', '+263',
	);
}

/**
 * Country code -> name map (identical to desktop).
 */
function gpn_country_names() {
	return array(
		'+93' => 'Afghanistan', '+355' => 'Albania', '+213' => 'Algeria', '+1684' => 'American Samoa',
		'+376' => 'Andorra', '+244' => 'Angola', '+1264' => 'Anguilla', '+1268' => 'Antigua & Barbuda',
		'+54' => 'Argentina', '+374' => 'Armenia', '+297' => 'Aruba', '+61' => 'Australia',
		'+43' => 'Austria', '+994' => 'Azerbaijan', '+1242' => 'Bahamas', '+973' => 'Bahrain',
		'+880' => 'Bangladesh', '+1246' => 'Barbados', '+375' => 'Belarus', '+32' => 'Belgium',
		'+501' => 'Belize', '+229' => 'Benin', '+1441' => 'Bermuda', '+975' => 'Bhutan',
		'+591' => 'Bolivia', '+387' => 'Bosnia & Herzegovina', '+267' => 'Botswana', '+55' => 'Brazil',
		'+1284' => 'British Virgin Islands', '+673' => 'Brunei', '+359' => 'Bulgaria', '+226' => 'Burkina Faso',
		'+257' => 'Burundi', '+855' => 'Cambodia', '+237' => 'Cameroon', '+1' => 'Canada / USA',
		'+238' => 'Cape Verde', '+1345' => 'Cayman Islands', '+236' => 'Central African Republic',
		'+235' => 'Chad', '+56' => 'Chile', '+86' => 'China', '+57' => 'Colombia', '+269' => 'Comoros',
		'+682' => 'Cook Islands', '+506' => 'Costa Rica', '+225' => "Côte d'Ivoire", '+385' => 'Croatia',
		'+53' => 'Cuba', '+357' => 'Cyprus', '+420' => 'Czech Republic', '+243' => 'DR Congo',
		'+45' => 'Denmark', '+253' => 'Djibouti', '+1767' => 'Dominica', '+1809' => 'Dominican Republic',
		'+670' => 'East Timor', '+593' => 'Ecuador', '+20' => 'Egypt', '+503' => 'El Salvador',
		'+240' => 'Equatorial Guinea', '+291' => 'Eritrea', '+372' => 'Estonia', '+251' => 'Ethiopia',
		'+500' => 'Falkland Islands', '+298' => 'Faroe Islands', '+679' => 'Fiji', '+358' => 'Finland',
		'+33' => 'France', '+594' => 'French Guiana', '+689' => 'French Polynesia', '+241' => 'Gabon',
		'+220' => 'Gambia', '+995' => 'Georgia', '+49' => 'Germany', '+233' => 'Ghana', '+350' => 'Gibraltar',
		'+30' => 'Greece', '+1473' => 'Grenada', '+590' => 'Guadeloupe', '+1671' => 'Guam',
		'+502' => 'Guatemala', '+224' => 'Guinea', '+245' => 'Guinea-Bissau', '+592' => 'Guyana',
		'+509' => 'Haiti', '+504' => 'Honduras', '+852' => 'Hong Kong', '+36' => 'Hungary',
		'+354' => 'Iceland', '+91' => 'India', '+62' => 'Indonesia', '+98' => 'Iran', '+964' => 'Iraq',
		'+353' => 'Ireland', '+972' => 'Israel', '+39' => 'Italy', '+1876' => 'Jamaica', '+81' => 'Japan',
		'+962' => 'Jordan', '+7' => 'Kazakhstan / Russia', '+254' => 'Kenya', '+686' => 'Kiribati',
		'+383' => 'Kosovo', '+965' => 'Kuwait', '+996' => 'Kyrgyzstan', '+856' => 'Laos', '+371' => 'Latvia',
		'+961' => 'Lebanon', '+266' => 'Lesotho', '+231' => 'Liberia', '+218' => 'Libya', '+423' => 'Liechtenstein',
		'+370' => 'Lithuania', '+352' => 'Luxembourg', '+853' => 'Macau', '+261' => 'Madagascar',
		'+265' => 'Malawi', '+60' => 'Malaysia', '+960' => 'Maldives', '+223' => 'Mali', '+356' => 'Malta',
		'+692' => 'Marshall Islands', '+222' => 'Mauritania', '+230' => 'Mauritius', '+262' => 'Mayotte / Réunion',
		'+52' => 'Mexico', '+691' => 'Micronesia', '+373' => 'Moldova', '+377' => 'Monaco', '+976' => 'Mongolia',
		'+382' => 'Montenegro', '+1664' => 'Montserrat', '+212' => 'Morocco', '+258' => 'Mozambique',
		'+95' => 'Myanmar', '+264' => 'Namibia', '+674' => 'Nauru', '+977' => 'Nepal', '+31' => 'Netherlands',
		'+599' => 'Netherlands Antilles', '+687' => 'New Caledonia', '+64' => 'New Zealand',
		'+505' => 'Nicaragua', '+227' => 'Niger', '+234' => 'Nigeria', '+683' => 'Niue', '+672' => 'Norfolk Island',
		'+389' => 'North Macedonia', '+1670' => 'Northern Mariana Islands', '+47' => 'Norway', '+968' => 'Oman',
		'+92' => 'Pakistan', '+680' => 'Palau', '+970' => 'Palestine', '+507' => 'Panama', '+675' => 'Papua New Guinea',
		'+595' => 'Paraguay', '+51' => 'Peru', '+63' => 'Philippines', '+48' => 'Poland', '+351' => 'Portugal',
		'+1939' => 'Puerto Rico', '+974' => 'Qatar', '+242' => 'Republic of the Congo', '+40' => 'Romania',
		'+250' => 'Rwanda', '+290' => 'Saint Helena', '+1869' => 'Saint Kitts & Nevis', '+1758' => 'Saint Lucia',
		'+508' => 'Saint Pierre & Miquelon', '+1784' => 'Saint Vincent & Grenadines', '+685' => 'Samoa',
		'+378' => 'San Marino', '+239' => 'São Tomé & Príncipe', '+966' => 'Saudi Arabia', '+221' => 'Senegal',
		'+381' => 'Serbia', '+248' => 'Seychelles', '+232' => 'Sierra Leone', '+65' => 'Singapore',
		'+1721' => 'Sint Maarten', '+421' => 'Slovakia', '+386' => 'Slovenia', '+677' => 'Solomon Islands',
		'+252' => 'Somalia', '+27' => 'South Africa', '+82' => 'South Korea', '+211' => 'South Sudan',
		'+34' => 'Spain', '+94' => 'Sri Lanka', '+249' => 'Sudan', '+597' => 'Suriname', '+268' => 'Eswatini',
		'+46' => 'Sweden', '+41' => 'Switzerland', '+963' => 'Syria', '+886' => 'Taiwan', '+992' => 'Tajikistan',
		'+255' => 'Tanzania', '+66' => 'Thailand', '+228' => 'Togo', '+690' => 'Tokelau', '+676' => 'Tonga',
		'+1868' => 'Trinidad & Tobago', '+216' => 'Tunisia', '+90' => 'Turkey', '+993' => 'Turkmenistan',
		'+1649' => 'Turks & Caicos Islands', '+688' => 'Tuvalu', '+256' => 'Uganda', '+380' => 'Ukraine',
		'+971' => 'United Arab Emirates', '+44' => 'United Kingdom', '+598' => 'Uruguay', '+998' => 'Uzbekistan',
		'+678' => 'Vanuatu', '+379' => 'Vatican City', '+58' => 'Venezuela', '+84' => 'Vietnam',
		'+1340' => 'US Virgin Islands', '+681' => 'Wallis & Futuna', '+967' => 'Yemen', '+260' => 'Zambia',
		'+263' => 'Zimbabwe',
	);
}

/**
 * Allowed CRM roles (kept identical to the desktop application).
 */
function gpn_roles() {
	return array( 'Admin', 'BC', 'GC', 'CT', 'TA', 'Mentor' );
}

/**
 * Human readable role label.
 */
function gpn_role_label( $role ) {
	$labels = array(
		'Admin'  => 'Administrator',
		'BC'     => 'BC (Batch Coordinator)',
		'GC'     => 'GC (Group Coordinator)',
		'CT'     => 'CT (Co-Teacher)',
		'TA'     => 'TA (Teaching Assistant)',
		'Mentor' => 'Mentor',
	);
	return isset( $labels[ $role ] ) ? $labels[ $role ] : $role;
}

/**
 * MySQL-style local timestamp, matching SQLite datetime('now','localtime').
 */
function gpn_now() {
	return current_time( 'mysql' );
}

/**
 * SHA-256 hash (identical to the Python desktop app).
 */
function gpn_hash_password( $password ) {
	return hash( 'sha256', $password );
}

/**
 * Strip non-digit characters.
 */
function gpn_digits( $raw ) {
	return preg_replace( '/\D/', '', (string) $raw );
}

/**
 * Convert PHP error to a safe string for JSON responses.
 */
function gpn_sanitize_out( $value ) {
	if ( null === $value ) {
		return '';
	}
	return (string) $value;
}

/**
 * JSON response helper for AJAX.
 */
function gpn_json( $payload, $status = 200 ) {
	wp_send_json( $payload, $status );
}

/**
 * Escape + JSON-encode helper.
 */
function gpn_json_esc( $payload ) {
	return wp_json_encode( $payload );
}

/**
 * Get the remote client IP (best effort).
 */
function gpn_client_ip() {
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		return trim( $ip[0] );
	}
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

/**
 * HTML-escape shorthand.
 */
function gpn_esc( $value ) {
	return esc_html( (string) $value );
}

/**
 * JSON decode with sanity checks.
 */
function gpn_json_decode( $raw, $assoc = true ) {
	$data = json_decode( (string) $raw, $assoc );
	return $data;
}
