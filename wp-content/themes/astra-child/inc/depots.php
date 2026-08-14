<?php

defined( 'ABSPATH' ) || exit;

function geeta_get_depots_raw() {
	$posts = get_posts(
		array(
			'post_type'      => 'geeta_depot',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$depots = array();

	foreach ( $posts as $post ) {
		$slug = $post->post_name;

		$depots[ $slug ] = array(
			'name'           => $post->post_title,
			'address'        => get_post_meta( $post->ID, '_depot_address', true ),
			'contact_person' => get_post_meta( $post->ID, '_depot_contact_person', true ),
			'phone'          => get_post_meta( $post->ID, '_depot_phone', true ),
			'email'          => get_post_meta( $post->ID, '_depot_email', true ),
			'is_main'        => (bool) get_post_meta( $post->ID, '_depot_is_main', true ),
			'province'       => get_post_meta( $post->ID, '_depot_province', true ),
			'district'       => get_post_meta( $post->ID, '_depot_district', true ),
		);
	}

	return $depots;
}

function geeta_get_depots() {
	return apply_filters( 'geeta_depots_list', geeta_get_depots_raw() );
}

function geeta_get_depot( $key ) {
	$depots = geeta_get_depots();
	return isset( $depots[ $key ] ) ? $depots[ $key ] : null;
}

function geeta_get_depot_name( $key ) {
	$depot = geeta_get_depot( $key );
	return $depot ? $depot['name'] : '';
}

function geeta_get_depot_email( $key ) {
	$depot = geeta_get_depot( $key );
	if ( ! $depot ) {
		return '';
	}
	if ( ! empty( $depot['is_main'] ) ) {
		return '';
	}
	return $depot['email'];
}

function geeta_get_depot_address( $key ) {
	$depot = geeta_get_depot( $key );
	return $depot ? $depot['address'] : '';
}

function geeta_get_depot_contact_person( $key ) {
	$depot = geeta_get_depot( $key );
	return $depot ? $depot['contact_person'] : '';
}

function geeta_get_depot_phone( $key ) {
	$depot = geeta_get_depot( $key );
	return $depot ? $depot['phone'] : '';
}

function geeta_is_main_depot( $key ) {
	$depot = geeta_get_depot( $key );
	return $depot && ! empty( $depot['is_main'] );
}

function geeta_get_provinces() {
	return apply_filters( 'geeta_provinces_list', array(
		'province-1' => 'प्रदेश नं. १ (कोशी प्रदेश)',
		'province-2' => 'प्रदेश नं. २ (मधेश प्रदेश)',
		'province-3' => 'प्रदेश नं. ३ (बागमती प्रदेश)',
		'province-4' => 'प्रदेश नं. ४ (गण्डकी प्रदेश)',
		'province-5' => 'प्रदेश नं. ५ (लुम्बिनी प्रदेश)',
		'province-6' => 'प्रदेश नं. ६ (कर्णाली प्रदेश)',
		'province-7' => 'प्रदेश नं. ७ (सुदूरपश्चिम प्रदेश)',
		'india'      => 'भारत',
	) );
}

function geeta_get_all_districts_by_province() {
	return apply_filters( 'geeta_all_districts_by_province', array(
		'province-1' => array(
			'ताप्लेजुङ', 'पाँचथर', 'इलाम', 'संखुवासभा', 'तेह्रथुम',
			'धनकुटा', 'भोजपुर', 'खोटाङ', 'सोलुखुम्बु', 'ओखलढुङ्गा',
			'उदयपुर', 'झापा', 'मोरङ', 'सुनसरी',
		),
		'province-2' => array(
			'सप्तरी', 'सिराहा', 'धनुषा', 'महोत्तरी', 'सर्लाही',
			'रौतहट', 'बारा', 'पर्सा',
		),
		'province-3' => array(
			'दोलखा', 'सिन्धुपाल्चोक', 'रसुवा', 'धादिङ', 'नुवाकोट',
			'काठमाडौँ', 'ललितपुर', 'भक्तपुर', 'काभ्रेपलान्चोक',
			'मकवानपुर', 'रामेछाप', 'सिन्धुली', 'चितवन',
		),
		'province-4' => array(
			'गोरखा', 'लमजुङ', 'तनहुँ', 'स्याङ्जा', 'कास्की',
			'मनाङ', 'मुस्ताङ', 'म्याग्दी', 'पर्वत', 'बागलुङ',
			'नवलपरासी',
		),
		'province-5' => array(
			'नवलपरासी', 'रुपन्देही', 'कपिलवस्तु', 'पाल्पा', 'अर्घाखाँची',
			'गुल्मी', 'दाङ', 'प्युठान', 'रोल्पा', 'बाँके', 'बर्दिया', 'पूर्वी रुकुम',
		),
		'province-6' => array(
			'सुर्खेत', 'सल्यान', 'दैलेख', 'जाजरकोट', 'डोल्पा',
			'जुम्ला', 'कालिकोट', 'मुगु', 'हुम्ला', 'पश्चिम रुकुम',
		),
		'province-7' => array(
			'कैलाली', 'अछाम', 'डोटी', 'बझाङ', 'बाजुरा',
			'कञ्चनपुर', 'डडेलधुरा', 'बैतडी', 'दार्चुला',
		),
		'india' => array(
			'सिलिगुडी',
		),
	) );
}
