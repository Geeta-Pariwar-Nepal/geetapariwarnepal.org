<?php

namespace Vivechan;

defined('ABSPATH') || exit;

/**
 * Activation / deactivation: DB schema, roles & capabilities, defaults, cron.
 */
final class Activator {

	const ROLE_VIVECHAK      = 'vivechak';
	const ROLE_EDITOR        = 'vivechan_editor';
	const CAP_TRANSCRIBE     = 'vivechan_transcribe';
	const CAP_PUBLISH        = 'vivechan_publish';
	const CAP_MANAGE         = 'vivechan_manage';

	const DEFAULT_PROMPT_TITLE = 'Nepali Proofreading';

	public static function activate() {
		self::create_tables();
		self::migrate();
		self::add_roles_and_caps();
		Services\Publication::add_caps();
		self::seed_default_prompt();
		Services\Publication::ensure_index_page();
		Cron\Cron::schedule_recurring();
	}

	public static function deactivate() {
		Cron\Cron::clear_recurring();
		$admin = get_role( 'administrator' );
		if ( $admin instanceof \WP_Role ) {
			$admin->remove_cap( self::CAP_TRANSCRIBE );
			$admin->remove_cap( self::CAP_PUBLISH );
			$admin->remove_cap( self::CAP_MANAGE );
		}
		Services\Publication::remove_caps();
	}

	/**
	 * Add columns added after first release (chapter, post_id).
	 */
	public static function migrate() {
		global $wpdb;
		$table = $wpdb->prefix . 'vivechan_transcripts';

		$cols = array(
			'chapter'   => 'int(11) DEFAULT NULL',
			'post_id'   => 'bigint(20) unsigned DEFAULT NULL',
			'locked_by' => 'bigint(20) unsigned DEFAULT NULL',
		);
		$existing = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
		foreach ( $cols as $name => $definition ) {
			if ( ! in_array( $name, $existing, true ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$name} {$definition}" );
			}
		}
	}

	/**
	 * Create the three custom tables via dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$sql = array(
			"CREATE TABLE {$p}vivechan_system_prompts (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) NOT NULL,
				content longtext NOT NULL,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY created_by (created_by)
			) {$charset};",

			"CREATE TABLE {$p}vivechan_ai_integrations (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) NOT NULL,
				api_key longtext NOT NULL,
				type varchar(20) NOT NULL,
				model varchar(255) DEFAULT NULL,
				chunk_size int(11) NOT NULL DEFAULT 800,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY created_by (created_by)
			) {$charset};",

			"CREATE TABLE {$p}vivechan_transcripts (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				video_id varchar(32) NOT NULL,
				filename varchar(255) NOT NULL,
				name varchar(255) DEFAULT NULL,
				title varchar(255) DEFAULT NULL,
				model varchar(255) DEFAULT NULL,
				raw_length int(11) NOT NULL DEFAULT 0,
				processed_raw_length int(11) NOT NULL DEFAULT 0,
				chunks int(11) NOT NULL DEFAULT 0,
				used_chunk_size int(11) DEFAULT NULL,
				prompt_used longtext,
				chapter int(11) DEFAULT NULL,
				post_id bigint(20) unsigned DEFAULT NULL,
				status varchar(16) NOT NULL DEFAULT 'PENDING',
				error text,
				content longtext,
				raw_transcript longtext,
				processed_chunks longtext,
				system_prompt_id bigint(20) unsigned DEFAULT NULL,
				integration_id bigint(20) unsigned DEFAULT NULL,
				locked_by bigint(20) unsigned DEFAULT NULL,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY created_by (created_by)
			) {$charset};",

			// Per-object sharing. Nothing is visible to everyone: a row is
			// reachable by its creator and by users it is shared with.
			"CREATE TABLE {$p}vivechan_shares (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				object_type varchar(20) NOT NULL,
				object_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY object_user (object_type,object_id,user_id),
				KEY user_id (user_id)
			) {$charset};",
		);

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Create the "Vivechak" role and grant capabilities.
	 */
	public static function add_roles_and_caps() {
		// Vivechak: transcribe and review. Vivechan Editor: the same, plus the
		// authority to edit finished text and publish it publicly — senior
		// review, without handing over the whole site.
		add_role( self::ROLE_VIVECHAK, 'Vivechak', array( 'read' => true ) );
		add_role( self::ROLE_EDITOR, 'Vivechan Editor', array( 'read' => true ) );

		self::grant( self::ROLE_VIVECHAK, array( 'read', self::CAP_TRANSCRIBE ) );
		self::grant( self::ROLE_EDITOR, array( 'read', self::CAP_TRANSCRIBE, self::CAP_PUBLISH ) );

		$admin = get_role( 'administrator' );
		if ( $admin instanceof \WP_Role ) {
			$admin->add_cap( self::CAP_TRANSCRIBE );
			$admin->add_cap( self::CAP_PUBLISH );
			$admin->add_cap( self::CAP_MANAGE );
		}
	}

	/**
	 * Apply capabilities to a role that may already exist.
	 *
	 * add_role() is a no-op when the role is already present, so capabilities
	 * cannot be declared there alone: a release that adds one would never reach
	 * sites where the role was created by an earlier version. Granting them
	 * separately makes upgrades work.
	 */
	private static function grant( $role_name, $caps ) {
		$role = get_role( $role_name );
		if ( ! $role instanceof \WP_Role ) {
			return;
		}
		foreach ( $caps as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Seed the default "Nepali Proofreading" system prompt if none exists.
	 */
	public static function seed_default_prompt() {
		$table = Models\PromptRepo::table();

		if ( Models\PromptRepo::find_by_title( self::DEFAULT_PROMPT_TITLE ) ) {
			return;
		}

		global $wpdb;
		$wpdb->insert(
			$table,
			array(
				'title'      => self::DEFAULT_PROMPT_TITLE,
				'content'    => self::default_prompt_content(),
				'created_by' => 0,
				'created_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
	}

	public static function default_prompt_content() {
		return "हिन्दी भाषामा लेखिएको युटुबको ट्रान्स्कृप्ट दिन्छु । ट्रान्स्कृप्टमा दोहोरो परेको विषय हटाएर नेपालीमा एकीकृतरुपमा स्तरिय लेखन सहित प्रूफ रिडिङ्ग गरिदिनुस ।  प्रूफ रिडिङ गर्दा बिशेषत तलका मार्गदर्शन अक्षरस पालना गर्न हुन अनुरोध छ ।
प्रूफ-रिडिङ सहितको लेखन: प्रत्येक सामग्रीलाई गम्भीरतापूर्वक प्रूफ-रिडिङ गरी उच्च स्तरीय लेखन-शैलीमा प्रस्तुत गरिदिनुहोस् ।
विषयवस्तुको एकीकरण: उपलब्ध सबै विषयवस्तुलाई कायम राख्दै, यदि कुनै प्रसङ्ग दोहोरिएको खण्डमा त्यसलाई एकीकृत गरी सिलसिला मिलाएर लेखिदिनुहोस् तर थप ब्याख्या गर्दै नगर्नुस ।
व्याकरण र शब्द चयन: नेपाली व्याकरणका नियमहरूलाई मसिनोसँग केलाएर शुद्धता कायम गरिदिनुहोस् । साथै, प्रसङ्गअनुसार उपयुक्त शब्दहरूको चयन गरी वाक्य संरचनालाई सरल, स्पष्ट र ओजपूर्ण बनाइदिनुहोस् ।
नेपाली शब्द चयनः नेपाली शब्द चयन गर्दा नेपाल प्रज्ञा-प्रतिष्ठानको नेपाली बृहत् शब्दकोश, नेपाल प्रज्ञा-प्रतिष्ठानकै संस्कृत-नेपाली शब्दकोश र के एन स्वामी एप' (Kosha.app) प्राज्ञिक पद्धतिलाई बिशेष आधार मानेर लेखिदिनुस ।
हिज्जे र चिन्हको शुद्धता: ह्रस्व-दीर्घ, अनुस्वार, विसर्ग र चन्द्रबिन्दुको उचित प्रयोगमा विशेष ध्यान दिनुहोस् ।
पूर्ण विरामको व्यवस्थापन: प्रत्येक पूर्ण विराम (।) भन्दा अगाडि अनिवार्य रूपमा एक स्पेस राखिदिनुहोस् ।
भगवान् शब्दको मानक प्रयोग: भगवान् शब्द लेख्दा 'न' मा सधैँ हलन्त प्रयोग गरिदिनुहोस्, यसलाई आधा 'न' नलेख्नुहोस् र बोल्ड पनि नगर्नुस ।
संस्कृत श्लोकको शुद्धीकरण: संस्कृतका श्लोकहरू मानक लिपि, शुद्ध पदच्छेद र व्याकरण अनुसार पूर्ण रूपमा शुद्ध लेखेर श्लोकलाई बोल्ड बनाइ दिनुस र बीचमा आएका संस्कृतका शव्दहरुलाई पनि शुद्ध गरेर बोल्ड बनाइ दिनुस ।
मराठी हिन्दी वा अन्य भाषाका भजन, दोहा र कविता जस्ताको तस्तै पूर्णरुपमा रूपमा शुद्ध लेखेर बोल्ड पनि गरिदिनुस ।
कुनै पनि बोल्ड गरिएका शब्द, श्लोक वा वाक्यको अगाडि र पछाडि सिङ्गल वा डबल इन्भर्टेड कमा ( ' ' वा \" \") कदापि प्रयोग नगर्नुहोस् ।
लेखनकोक्रमा आएका को, लाई, बाट, ले, सम्वन्धित, मा, सङ्ग जस्ता सबै विभक्तिहरु नाम र सर्वनामसङ्ग जोडेर लेख्नुहोस ।
चाही, के रे जस्ता थेगो र बोल्ने क्रममा आएक अँ, हँ जस्ता अनावश्यक शव्दहरु हटाउनुहोस ।
लेखनको अल्पविराम पुर्णविराम सिङ्गल र डवल इन्भर्टेट कमा र प्रश्नवाचक चिन्ह र विस्मयाबोधक चिन्ह राख्दा तलको फरमेटमा राखिदिनुस ।
प्रश्नवाचक (?) र विस्मयाबोधक (!) चिन्ह अगाडि स्पेस नराख्ने ।
डवल र सिङ्गल इन्भर्टेड कमा स्पेश नराखी बन्द गर्ने । जस्तै \"मेरो नाम राम हो\" । म 'कपन'मा बस्छु ।
इन्भर्टेट कमा राख्दा सधै सुरु हुँदा दाहिनेतिर (\" ) र बन्द हुँदा देब्रेतिर (\" ) फर्किने मानक नेपाली उद्धरण चिह्नहरूको प्रयोग गर्न हुन ।
विराम चिह्नहरु पूर्ण विराम, प्रश्नवाचक चिह्न, विश्मयावोधक ( ।, ?, ! ) सधैँ इन्भर्टेड कमा बन्द भएपछि मात्र राख्ने । उदाहरणको लागि (\" । )";
	}
}
