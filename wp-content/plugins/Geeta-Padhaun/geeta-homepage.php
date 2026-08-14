<?php
/**
 * Plugin Name: Geeta-Padhaun App
 * Description: गीता पढौँ | पढाऔँ / ज्ञान बाँडौँ | जीवनमा उतारौँ / लागू गरौँ — Bhagavad Gita reader with chanting images and vivechans.
 * Version:     1.2.0
 * Author:      Geeta Pariwar Nepal
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GEETA_VIVECHANS_DIR', plugin_dir_path(__FILE__));
define('GEETA_VIVECHANS_URL', plugin_dir_url(__FILE__));

require_once GEETA_VIVECHANS_DIR . 'includes/class-geeta-homepage-api.php';
require_once GEETA_VIVECHANS_DIR . 'includes/class-geeta-homepage-importer.php';

add_action('rest_api_init', ['Geeta_Homepage_API', 'register_routes']);
add_action('admin_post_geeta_vivechans_import_seed', ['Geeta_Homepage_Importer', 'handle_import']);
add_action('admin_menu', ['Geeta_Homepage_Importer', 'register_tools_page']);

register_activation_hook(__FILE__, function () {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $prefix = $wpdb->prefix;

    $tables = [
        "CREATE TABLE {$prefix}gp_chapters (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chapter_number INT NOT NULL,
            name_hi TEXT NOT NULL,
            name_transliterated TEXT NOT NULL,
            name_translated TEXT NOT NULL,
            summary_hi LONGTEXT NULL,
            summary_en LONGTEXT NULL,
            verses_count INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY chapter_number (chapter_number)
        )",
        "CREATE TABLE {$prefix}gp_sub_chapters (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chapter_number INT NOT NULL,
            sub_chapter INT NOT NULL,
            name_hi TEXT NOT NULL,
            name_transliterated TEXT NOT NULL,
            name_translated TEXT NOT NULL,
            verses_count INT NOT NULL DEFAULT 0,
            image_start INT NOT NULL DEFAULT 1,
            image_end INT NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY ch_sub (chapter_number, sub_chapter)
        )",
        "CREATE TABLE {$prefix}gp_verses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chapter_number INT NOT NULL,
            sub_chapter INT NOT NULL DEFAULT 1,
            verse_number INT NOT NULL,
            verse_order INT NOT NULL DEFAULT 0,
            external_id VARCHAR(100) NULL,
            sanskrit_text LONGTEXT NOT NULL,
            transliteration LONGTEXT NULL,
            word_meanings LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ch_sub_verse (chapter_number, sub_chapter, verse_number)
        )",
        "CREATE TABLE {$prefix}gp_verse_translations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            verse_id BIGINT UNSIGNED NOT NULL,
            lang VARCHAR(10) NOT NULL,
            meaning_text LONGTEXT NULL,
            commentary_text LONGTEXT NULL,
            commentator_name VARCHAR(255) NULL,
            PRIMARY KEY (id),
            KEY verse_lang (verse_id, lang)
        )",
        "CREATE TABLE {$prefix}gp_vivechans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chapter INT NOT NULL,
            sloka_number INT NOT NULL,
            speaker VARCHAR(255) NOT NULL,
            video_url TEXT NULL,
            date_iso VARCHAR(32) NULL,
            meaning_nepali LONGTEXT NULL,
            lang VARCHAR(10) NOT NULL DEFAULT 'ne',
            PRIMARY KEY (id),
            KEY chapter_sloka_lang (chapter, sloka_number, lang)
        )",
    ];

    foreach ($tables as $sql) {
        dbDelta($sql);
    }

    if (get_option('geeta_db_version', '0') !== '1.2') {
        geeta_migrate_v12();
        geeta_seed_data($wpdb, $prefix);
    }
});

add_filter('theme_page_templates', function ($templates) {
    $templates['page-geeta-home.php'] = 'Geeta-Padhaun App Template';
    return $templates;
});

add_filter('template_include', function ($template) {
    if (is_page() && get_page_template_slug() === 'page-geeta-home.php') {
        $plugin_template = GEETA_VIVECHANS_DIR . 'templates/page-geeta-home.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
});

add_action('wp_enqueue_scripts', function () {
    if (!is_page_template('page-geeta-home.php')) {
        return;
    }

    wp_enqueue_style('geeta-homepage', GEETA_VIVECHANS_URL . 'assets/css/geeta-homepage.css', [], '1.2.0');
    wp_enqueue_script('geeta-homepage', GEETA_VIVECHANS_URL . 'assets/js/geeta-homepage.js', [], '1.2.0', true);

    wp_localize_script('geeta-homepage', 'GeetaVivechansConfig', [
        'apiBase' => rest_url('geeta-vivechans/v1'),
        'nonce' => wp_create_nonce('wp_rest'),
        'defaultLanguage' => 'ne',
    ]);
});

function geeta_seed_data($wpdb, $prefix) {
    $ch_table = $prefix . 'gp_chapters';
    $sc_table = $prefix . 'gp_sub_chapters';
    $ve_table = $prefix . 'gp_verses';
    $tr_table = $prefix . 'gp_verse_translations';

    $chapters = [
        ['chapter_number' => 0, 'name_hi' => '0-प्रारम्भिक', 'name_transliterated' => 'Preliminary', 'name_translated' => 'Preliminary', 'summary_hi' => 'Nyasa, Mahatmya, and Dhyanam — preparatory recitations before the Gita.', 'summary_en' => 'Nyasa, Mahatmya, and Dhyanam — preparatory recitations before the Gita.', 'verses_count' => 22],
        ['chapter_number' => 99, 'name_hi' => '99-समापन', 'name_transliterated' => 'Concluding', 'name_translated' => 'Concluding', 'summary_hi' => 'Kshamaprarthana and Geeta Aarati.', 'summary_en' => 'Prayer for forgiveness and hymn in praise of the Gita.', 'verses_count' => 3],
    ];

    foreach ($chapters as $ch) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ch_table} WHERE chapter_number = %d", $ch['chapter_number']));
        if ($exists) {
            $wpdb->update($ch_table, $ch, ['chapter_number' => $ch['chapter_number']]);
        } else {
            $wpdb->insert($ch_table, $ch);
        }
    }

    $sub_chapters = [
        ['chapter_number' => 0, 'sub_chapter' => 1, 'name_hi' => '0.1-न्यासः', 'name_transliterated' => 'Nyasah', 'name_translated' => 'Nyasah', 'verses_count' => 13, 'image_start' => 1, 'image_end' => 13],
        ['chapter_number' => 0, 'sub_chapter' => 2, 'name_hi' => '0.2-माहात्म्य', 'name_transliterated' => 'Mahatmya', 'name_translated' => 'Mahatmya', 'verses_count' => 7, 'image_start' => 14, 'image_end' => 20],
        ['chapter_number' => 0, 'sub_chapter' => 3, 'name_hi' => '0.3-ध्यानम्', 'name_transliterated' => 'Dhyanam', 'name_translated' => 'Dhyanam', 'verses_count' => 2, 'image_start' => 21, 'image_end' => 22],
    ];

    foreach ($sub_chapters as $sc) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$sc_table} WHERE chapter_number = %d AND sub_chapter = %d", $sc['chapter_number'], $sc['sub_chapter']));
        if ($exists) {
            $wpdb->update($sc_table, $sc, ['chapter_number' => $sc['chapter_number'], 'sub_chapter' => $sc['sub_chapter']]);
        } else {
            $wpdb->insert($sc_table, $sc);
        }
    }

    $sub1_verses = [
        1 => ['sanskrit' => "श्रीहरिः।\nश्रीमद्भगवद्गीता\nन्यास, माहात्म्य एवं ध्यान\nNyāsah, Gītā Māhātmyam and Dhyānam", 'transliteration' => 'Śrīhariḥ। Śrīmadbhagavadgītā nyāsa, māhātmya evaṃ dhyānam।', 'word_meanings' => ''],
        2 => ['sanskrit' => "ॐ श्रीपरमात्मने नमः ।\nश्रीमद्भगवद्गीता\nअथ करन्यासः ।", 'transliteration' => 'Om śrīparamātmane namaḥ। Śrīmadbhagavadgītā atha karanyāsaḥ।', 'word_meanings' => 'Om. Salutations to the Supreme Self. Now begins the hand-nyasa for the Bhagavad Gita.'],
        3 => ['sanskrit' => "ॐ अस्य श्रीमद्भगवद्गीतामालामन्त्रस्य भगवान्वेदव्यास ऋषिः। अनुष्टुप् छन्दः। श्रीकृष्णः परमात्मा देवता। अशोच्यानन्वशोचस्त्वं प्रज्ञावादांश्च भाषसे इति बीजम्। सर्वधर्मान् परित्यज्य मामेकं शरणं व्रज इति शक्तिः। अहं त्वा सर्वपापेभ्यो मोक्षयिष्यामि मा शुच इति कीलकम्। नैनं छिन्दन्ति शस्त्राणि नैनं दहति पावक इत्यङ्गुष्ठाभ्यां नमः।", 'transliteration' => 'Om asya śrīmadbhagavadgītāmālāmantrasya bhagavānvedavyāsa ṛṣiḥ। Anuṣṭup chandaḥ। Śrīkṛṣṇaḥ paramātmā devatā। Aśocyānanvaśocastvaṃ prajñāvādāṃśca bhāṣase iti bījam। Sarvadharmān parityajya māmekaṃ śaraṇaṃ vraja iti śaktiḥ। Ahaṃ tvā sarvapāpebhyo mokṣayiṣyāmi mā śuca iti kīlakam। Nainaṃ chindanti śastrāṇi nainaṃ dahati pāvaka ityaṅguṣṭhābhyāṃ namaḥ।', 'word_meanings' => ''],
        4 => ['sanskrit' => "न चैनं क्लेदयन्त्यापो न शोषयति मारुत इति तर्जनीभ्यां नमः।\nअच्छेद्योऽयमदाह्योऽयमक्लेद्योऽशोष्य एव च इति मध्यमाभ्यां नमः।\nनित्यः सर्वगतः स्थाणुरचलोऽयं सनातन इत्यनामिकाभ्यां नमः।\nपश्य मे पार्थ रूपाणि शतशोऽथ सहस्रश इति कनिष्ठिकाभ्यां नमः।\nनानाविधानि दिव्यानि नानावर्णाकृतीनि च इति करतलकरपृष्ठाभ्यां नमः।\nइति करन्यासः।", 'transliteration' => 'Na cainaṃ kledayantyāpo na śoṣayati māruta iti tarjanībhyāṃ namaḥ। Acchedyo\'yamadāhyo\'yamakledyo\'śoṣya eva ca iti madhyamābhyāṃ namaḥ। Nityaḥ sarvagataḥ sthāṇuracalo\'yaṃ sanātana ityanāmikābhyāṃ namaḥ। Paśya me pārtha rūpāṇi śataśo\'tha sahasraśa iti kaniṣṭhikābhyāṃ namaḥ। Nānāvidhāni divyāni nānāvarṇākṛtīni ca iti karatalakarapṛṣṭhābhyāṃ namaḥ। Iti karanyāsaḥ।', 'word_meanings' => ''],
        5 => ['sanskrit' => "अथ हृदयादिन्यासः।\n\nनैनं छिन्दन्ति शस्त्राणि नैनं दहति पावक इति हृदयाय नमः।\nन चैनं क्लेदयन्त्यापो न शोषयति मारुत इति शिरसे स्वाहा।\nअच्छेद्योऽयमदाह्योऽयमक्लेद्योऽशोष्य एव चेति शिखायै वषट्।\nनित्यः सर्वगतः स्थाणुरचलोऽयं सनातन इति कवचाय हुम्।\nपश्य मे पार्थ", 'transliteration' => 'Atha hṛdayādinyāsaḥ। Nainaṃ chindanti śastrāṇi nainaṃ dahati pāvaka iti hṛdayāya namaḥ। Na cainaṃ kledayantyāpo na śoṣayati māruta iti śirase svāhā। Acchedyo\'yamadāhyo\'yamakledyo\'śoṣya eva ceti śikhāyai vaṣaṭ। Nityaḥ sarvagataḥ sthāṇuracalo\'yaṃ sanātana iti kavacāya hum। Paśya me pārtha', 'word_meanings' => ''],
        6 => ['sanskrit' => "रूपाणि शतशोऽथ सहस्रश इति नेत्रत्रयाय वौषट्।\nनानाविधानि दिव्यानि नानावर्णाकृतीनि चेति अस्त्राय फट्।\nश्रीकृष्णप्रीत्यर्थे पाठे विनियोगः।\nइति हृदयादिन्यासः।", 'transliteration' => 'Rūpāṇi śataśo\'tha sahasraśa iti netratrayāya vauṣaṭ। Nānāvidhāni divyāni nānāvarṇākṛtīni ceti astrāya phaṭ। Śrīkṛṣṇaprītyarthe pāṭhe viniyogaḥ। Iti hṛdayādinyāsaḥ।', 'word_meanings' => ''],
        7 => ['sanskrit' => 'ॐ पार्थाय प्रतिबोधितां भगवता नारायणेन स्वयम् । व्यासेन ग्रथितां पुराणमुनिना मध्ये महाभारतम्। अद्वैतामृतवर्षिणीं भगवतीमष्टादशाध्यायिनीम् । अम्ब त्वामनुसन्दधामि भगवद्गीते भवद्वेषिणीम् ॥१॥', 'transliteration' => 'Om Pārthāya pratibodhitām bhagavatā nārāyaṇena svayam। Vyāsena grathitām purāṇamuninā madhye mahābhāratam। Advaitāmṛtavarṣiṇīm bhagavatīmaṣṭādaśādhyāyinīm। Amba tvāmanusandadhāmi bhagavadgīte bhavadveṣiṇīm ॥1॥', 'word_meanings' => ''],
        8 => ['sanskrit' => 'नमो अस्तु ते व्यास विशालबुद्धे फुल्लारविन्दायतपत्रनेत्र। येन त्वया भारततैलपूर्णः प्रज्वालितो ज्ञानमयः प्रदीपः ॥२॥', 'transliteration' => 'Namo astu te vyāsa viśālabuddhe phullāravindāyatapatranetra। Yena tvayā bhāratatailapūrṇaḥ prajvālito jñānamayaḥ pradīpaḥ ॥2॥', 'word_meanings' => ''],
        9 => ['sanskrit' => 'प्रपन्नपारिजाताय तोत्रवेत्रैकपाणये। ज्ञानमुद्राय कृष्णाय गीतामृतदुहे नमः ॥३॥', 'transliteration' => 'Prapannapārijātāya totravetraikapāṇaye। Jñānamudrāya kṛṣṇāya gītāmṛtaduhe namaḥ ॥3॥', 'word_meanings' => ''],
        10 => ['sanskrit' => 'वसुदेवसुतं देवं कंसचाणूरमर्दनम्। देवकीपरमानन्दं कृष्णं वन्दे जगद्गुरुम् ॥४॥', 'transliteration' => 'Vasudevasutaṃ devaṃ kaṃsacāṇūramardanam। Devakīparamānandaṃ kṛṣṇaṃ vande jagadgurum ॥4॥', 'word_meanings' => ''],
        11 => ['sanskrit' => 'भीष्मद्रोणतटा जयद्रथजला गान्धारनीलोत्पला। शल्यग्राहवती कृपेण वहनी कर्णेन वेलाकुला। अश्वत्थामविकर्णघोरमकरा दुर्योधनावर्तिनी। सोत्तीर्णा खलु पाण्डवै रणनदी कैवर्तकः केशवः ॥५॥', 'transliteration' => 'Bhīṣmadroṇataṭā jayadrathajalā gāndhārānīlotpalā। Śalyagrāhavatī kṛpeṇa vahanī karṇena velākulā। Aśvatthāmavikarṇaghoramakarā duryodhanāvartinī। Sottīrṇā khalu pāṇḍavai raṇanadī kaivartakaḥ keśavaḥ ॥5॥', 'word_meanings' => ''],
        12 => ['sanskrit' => 'पाराशर्यवचः सरोजममलं गीतार्थगन्धोत्कटम्। नानाख्यानककेसरं हरिकथासम्बोधनाबोधितम्। लोके सज्जनषट्पदैरहरहः पेपीयमानं मुदा। भूयाद्भारतपङ्कजं कलिमलप्रध्वंसि नः श्रेयसे ॥६॥', 'transliteration' => 'Pārāśaryavacaḥ sarojamamalaṃ gītārthagandhotkaṭam। Nānākhyānakakesaraṃ harikathāsambodhanābodhitam। Loke sajjanaṣaṭpadairaharahaḥ pepīyamānaṃ mudā। Bhūyādbhāratapaṅkajaṃ kalimalapradhvaṃsi naḥ śreyase ॥6॥', 'word_meanings' => ''],
        13 => ['sanskrit' => 'मूकं करोति वाचालं पङ्गुं लङ्घयते गिरिम्। यत्कृपा तमहं वन्दे परमानन्दमाधवम् ॥७॥', 'transliteration' => 'Mūkaṃ karoti vācālaṃ paṅguṃ laṅghayate girim। Yatkṛpā tamahaṃ vande paramānandamādhavam ॥7॥', 'word_meanings' => ''],
    ];

    $mahatmya_sloka = [
        1 => ['dev' => "गीता शास्त्रं इदं पुण्यं यः पठेत् प्रयतः पुमान् ।\nविष्णोः पादं अवाप्नोति भय शोकादि वर्जितः ।।", 'trans' => 'Gita-shastramidam punyam yah pathet prayatah puman | Vishnoh padam avapnoti bhaya-shokadi-varjitah ||', 'meaning_hi' => 'जो इसे ध्यानपूर्वक पढ़ता है और इसके उपदेशों का पालन करता है, वह भगवान् विष्णु का आश्रय प्राप्त करता है जो कि समस्त भय तथा चिंताओं से मुक्त है।', 'meaning_en' => 'If one properly follows the instructions of Bhagavad-gita, one can be freed from all miseries and anxieties in this life, and attain the abode of Lord Vishnu.'],
        2 => ['dev' => "गीताध्ययनशीलस्य प्राणायामपरस्य च ।\nनैव सन्ति हि पापानि पूर्वजन्मकृतानि च ।।", 'trans' => 'Gitadhyayana-shilasya pranayama-parasya ca | Naiva santi hi papani purva-janma-kritani ca ||', 'meaning_hi' => 'यदि कोई भगवद्गीता को निष्ठा तथा गम्भीरता के साथ पढ़ता है तो भगवान् की कृपा से उसके सारे पूर्व दुष्कर्मों के फ़लों का उस पर कोई प्रभाव नहीं पड़ता।', 'meaning_en' => 'If one reads Bhagavad-gita very sincerely and with all seriousness, then by the grace of the Lord the reactions of his past misdeeds will not act upon him.'],
        3 => ['dev' => "मलिनेमोचनं पुंसां जलस्नानं दिने दिने ।\nसकृद्गीतामृतस्नानं संसारमलनाशनम् ।।", 'trans' => 'Maline mocanam pumsam jala-snanam dine dine | Sakrid gitamrita-snanam samsara-mala-nashanam ||', 'meaning_hi' => 'मनुष्य जल में स्नान करके नित्य अपने को स्वच्छ कर सकता है, लेकिन यदि कोई भगवद्गीता-रूपी पवित्र गंगा-जल में एक बार भी स्नान कर ले तो वह भौतिक जीवन की मलिनता से सदा के लिए मुक्त हो जाता है।', 'meaning_en' => 'One may cleanse himself daily by taking a bath in water, but if one takes a bath even once in the sacred Ganges water of Bhagavad-gita, for him the dirt of material life is altogether vanquished.'],
        4 => ['dev' => "गीता सुगीताकर्तव्या किमन्यौः शास्त्रविस्तरैः ।\nया स्वयं पद्मनाभस्य मुखपद्माद्विनिःसृता ।।", 'trans' => 'Gita sugita kartavya kim anyaih shastra-vistaraih | Ya svayam padmanabhasya mukha-padmad vinih srita ||', 'meaning_hi' => 'चूँकि भगवद्गीता भगवान् के मुख से निकली है, अतएव किसी अन्य वैदिक साहित्य को पढ़ने की आवश्कता नहीं रहती। केवल भगवद्गीता का ही ध्यानपूर्वक तथा मनोयोग से श्रवण तथा पठन करना चाहिए।', 'meaning_en' => 'Because Bhagavad-gita is spoken by the Supreme Personality of Godhead, one need not read any other Vedic literature. One need only attentively and regularly hear and read Bhagavad-gita.'],
        5 => ['dev' => "भारतामृतसर्वस्वं विष्णुवक्त्राद्विनिःसृतम् ।\nगीतागङ्गोदकं पीत्वा पुनर्जन्म न विद्यते ।।", 'trans' => 'Bharatamrita-sarvasvam vishnu-vaktrad vinih sritam | Gita-gangodakam pitva punar-janma na vidyate ||', 'meaning_hi' => 'जो गंगाजल पीता है, वह मुक्ति प्राप्त करता है। अतएव उसके लिए क्या कहा जाय जो भगवद्गीता का अमृत पान करता हो? भगवद्गीता महाभारत का अमृत है और इसे भगवान् कृष्ण ने स्वयं सुनाया है।', 'meaning_en' => 'One who drinks the water of the Ganges attains salvation, so what to speak of one who drinks the nectar of Bhagavad-gita? Bhagavad-gita is the essential nectar of the Mahabharata, and it is spoken by Lord Krishna Himself.'],
        6 => ['dev' => "सर्वोपनिषदो गावो दोग्धा गोपालनन्दनः ।\nपार्थो वत्सः सुधीर्भोक्ता दुग्धं गीतामृतं महत् ।।", 'trans' => 'Sarvopanishado gavo dogdha gopala-nandanah | Partho vatsah su-dhir bhokta dugdham gitamritam mahat ||', 'meaning_hi' => 'यह गीतोपनीषद, भगवद्गीता, जो समस्त उपनिषदों का सार है, गाय के तुल्य है और ग्वालबाल के रूप में विख्यात भगवान् कृष्ण इस गाय को दुह रहे हैं। अर्जुन बछड़े के समान है, और सारे विद्वान् तथा शुद्ध भक्त भगवद्गीता के अमृतमय दूध का पान करने वाले हैं।', 'meaning_en' => 'This Gitopanishad, Bhagavad-gita, the essence of all the Upanishads, is just like a cow, and Lord Krishna is milking this cow. Arjuna is just like a calf, and learned scholars and pure devotees are to drink the nectarean milk of Bhagavad-gita.'],
        7 => ['dev' => "एकं शास्त्रं देवकीपुत्रगीतम् ।\nएको देवो देवकीपुत्र एव ।।\nएको मन्त्रस्तस्य नामानि यानि ।\nकर्माप्येकं तस्य देवस्य सेवा ।।", 'trans' => 'Ekam shastram devaki-putra-gitam | Eko devo devaki-putra eva | Eko mantras tasya namani yani | Karmapy ekam tasya devasya seva ||', 'meaning_hi' => 'एक शास्त्र — देवकीपुत्र की गीता, एक देव — देवकीपुत्र श्रीकृष्ण, एक मन्त्र — उनके नाम का कीर्तन, एक कार्य — भगवान् की सेवा।', 'meaning_en' => 'Let there be one scripture only — Bhagavad-gita. Let there be one God for the whole world — Sri Krishna, and one mantra — the chanting of His name: Hare Krishna, and let there be one work only — the service unto Him.'],
    ];

    $sub1_meaning = [
        2 => ['meaning' => 'Now begins the hand-nyasa for the Bhagavad Gita garland-mantra.'],
        3 => ['meaning' => 'The sage is Vedavyasa, the meter is Anushtup, the deity is Lord Krishna. The bija, shakti, and kilaka of the Gita-mantra are invoked, and the first finger (thumb) is touched.'],
        5 => ['meaning' => 'The heart nyasa for the Gita mantra — the six limbs (heart, head, crown, armor, eyes, weapon) are touched with specific verses for the pleasure of Lord Krishna.'],
    ];

    foreach ($sub1_verses as $vn => $v) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ve_table} WHERE chapter_number = 0 AND sub_chapter = 1 AND verse_number = %d", $vn));
        $data = ['chapter_number' => 0, 'sub_chapter' => 1, 'verse_number' => $vn, 'verse_order' => $vn, 'sanskrit_text' => $v['sanskrit'], 'transliteration' => $v['transliteration'], 'word_meanings' => $v['word_meanings']];
        if ($exists) {
            $wpdb->update($ve_table, $data, ['chapter_number' => 0, 'sub_chapter' => 1, 'verse_number' => $vn]);
        } else {
            $wpdb->insert($ve_table, $data);
        }
        $vid = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$ve_table} WHERE chapter_number = 0 AND sub_chapter = 1 AND verse_number = %d LIMIT 1", $vn));
        if ($vid && isset($sub1_meaning[$vn])) {
            $exists_t = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tr_table} WHERE verse_id = %d AND lang = 'en'", $vid));
            if (!$exists_t) {
                $wpdb->insert($tr_table, ['verse_id' => $vid, 'lang' => 'en', 'meaning_text' => $sub1_meaning[$vn]['meaning'], 'commentary_text' => '', 'commentator_name' => '']);
            }
        }
    }

    foreach ($mahatmya_sloka as $vn => $s) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ve_table} WHERE chapter_number = 0 AND sub_chapter = 2 AND verse_number = %d", $vn));
        $data = ['chapter_number' => 0, 'sub_chapter' => 2, 'verse_number' => $vn, 'verse_order' => $vn, 'sanskrit_text' => $s['dev'], 'transliteration' => $s['trans'], 'word_meanings' => ''];
        if ($exists) {
            $wpdb->update($ve_table, $data, ['chapter_number' => 0, 'sub_chapter' => 2, 'verse_number' => $vn]);
        } else {
            $wpdb->insert($ve_table, $data);
        }
        $vid = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$ve_table} WHERE chapter_number = 0 AND sub_chapter = 2 AND verse_number = %d LIMIT 1", $vn));
        if ($vid) {
            foreach (['hi' => $s['meaning_hi'], 'en' => $s['meaning_en']] as $lang => $txt) {
                $et = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tr_table} WHERE verse_id = %d AND lang = %s", $vid, $lang));
                if (!$et) {
                    $wpdb->insert($tr_table, ['verse_id' => $vid, 'lang' => $lang, 'meaning_text' => $txt, 'commentary_text' => '', 'commentator_name' => '']);
                }
            }
        }
    }

    $dhyana_conclusion = [
        1 => ['sanskrit' => "अथ ध्यानम्।\n\nशान्ताकारं भुजगशयनं पद्मनाभं सुरेशम्।\nविश्वाधारं गगनसदृशं मेघवर्णं शुभाङ्गम्।\nलक्ष्मीकान्तं कमलनयनं योगिभिर्ध्यानगम्यम्।\nवन्दे विष्णुं भवभयहरं सर्वलोकैकनाथम्।", 'transliteration' => 'Atha dhyānam। Śāntākāraṃ bhujagaśayanaṃ padmanābhaṃ sureśam। Viśvādhāraṃ gaganasadṛśaṃ meghavarṇaṃ śubhāṅgam। Lakṣmīkāntaṃ kamalanayanaṃ yogibhirdhyānagamyaṃ। Vande viṣṇuṃ bhavabhayaharaṃ sarvalokaikanātham।', 'word_meanings' => ''],
        2 => ['sanskrit' => "यं ब्रह्मा वरुणेन्द्ररुद्रमरुतः स्तुवन्ति दिव्यैः स्तवैः।\nवेदैः साङ्गपदक्रमोपनिषदैर्गायन्ति यं सामगाः।\nध्यानावस्थिततद्गतेन मनसा पश्यन्ति यं योगिनः।\nयस्यान्तं न विदुः सुरासुरगणा देवाय तस्मै नमः।", 'transliteration' => 'Yaṃ brahmā varuṇendrarudramarutaḥ stuvanti divyaiḥ stavaiḥ। Vedaiḥ sāṅgapadakramopaniṣadairgāyanti yaṃ sāmagāḥ। Dhyānāvasthitatadgatena manasā paśyanti yaṃ yoginaḥ। Yasyāntaṃ na viduḥ surāsuragaṇā devāya tasmai namaḥ।', 'word_meanings' => ''],
    ];

    foreach ($dhyana_conclusion as $vn => $s) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ve_table} WHERE chapter_number = 0 AND sub_chapter = 3 AND verse_number = %d", $vn));
        $data = ['chapter_number' => 0, 'sub_chapter' => 3, 'verse_number' => $vn, 'verse_order' => $vn, 'sanskrit_text' => $s['sanskrit'], 'transliteration' => $s['transliteration'], 'word_meanings' => $s['word_meanings']];
        if ($exists) {
            $wpdb->update($ve_table, $data, ['chapter_number' => 0, 'sub_chapter' => 3, 'verse_number' => $vn]);
        } else {
            $wpdb->insert($ve_table, $data);
        }
    }

    $kshama_verses = [
        1 => ['sanskrit' => "यस्य स्मृत्या च नामोक्त्या तपोयज्ञक्रियादिषु ।\nन्यूनं सम्पूर्णतां याति सद्यो वन्दे तमच्युतम् ॥", 'transliteration' => 'Yasya smrtya ca namoktya tapoyajnakriyadisu | Nyunam sampurnatam yati sadyo vande tam acyutam ||', 'word_meanings' => '', 'meaning' => 'I bow to that Acyuta (the infallible Lord), by remembering whom and uttering whose name, whatever is lacking in penance, sacrifice, and actions becomes complete instantly.'],
        2 => ['sanskrit' => "यदक्षरपदभ्रष्टं मात्राहीनं च यद्भवेत् ।\nतत्सर्वं क्षम्यतां देव नारायण नमोऽस्तु ते ॥", 'transliteration' => 'Yadakshara-pada-bhrashtam matra-hinam ca yad bhavet | Tatsarvam kshamyatam deva narayana namo \'stu te ||', 'word_meanings' => '', 'meaning' => 'O Lord Narayana, please forgive any mistakes in syllables, words, meter, or cadence. My obeisances to You.'],

    ];

    foreach ($kshama_verses as $vn => $v) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ve_table} WHERE chapter_number = 99 AND sub_chapter = 1 AND verse_number = %d", $vn));
        $data = ['chapter_number' => 99, 'sub_chapter' => 1, 'verse_number' => $vn, 'verse_order' => $vn, 'sanskrit_text' => $v['sanskrit'], 'transliteration' => $v['transliteration'], 'word_meanings' => $v['word_meanings']];
        if ($exists) {
            $wpdb->update($ve_table, $data, ['chapter_number' => 99, 'sub_chapter' => 1, 'verse_number' => $vn]);
        } else {
            $wpdb->insert($ve_table, $data);
        }
        $vid = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$ve_table} WHERE chapter_number = 99 AND sub_chapter = 1 AND verse_number = %d LIMIT 1", $vn));
        if ($vid) {
            $et = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tr_table} WHERE verse_id = %d AND lang = 'en'", $vid));
            if (!$et) {
                $wpdb->insert($tr_table, ['verse_id' => $vid, 'lang' => 'en', 'meaning_text' => $v['meaning'], 'commentary_text' => '', 'commentator_name' => '']);
            }
        }
    }

    $aarati_text = "॥ जय भगवद् गीते ॥\n\nजय भगवद् गीते,\nजय भगवद् गीते।\nहरि-हिय-कमल-विहारिणि,\nसुन्दर सुपुनीते।\nजय भगवद् गीते\n\nकर्म-सुमर्म-प्रकाशिनि,\nकामासक्तिहरा।\nतत्त्वज्ञान-विकाशिनि,\nविद्या ब्रह्म परा।\nजय भगवद् गीते\n\nनिश्चल-भक्ति-विधायिनि,\nनिर्मल मलहारी।\nशरण-सहस्य-प्रदायिनि,\nसब विधि सुखकारी।\nजय भगवद् गीते\n\nराग-द्वेष-विदारिणि,\nकारिणि मोद सदा।\nभव-भय-हारिणि,\nतारिणि परमानन्दप्रदा।\nजय भगवद् गीते\n\nआसुर-भाव-विनाशिनि,\nनाशिनि तम रजनी।\nदैवी सद्-गुणदायिनि,\nहरि-रसिका सजनी।\nजय भगवद् गीते\n\nसमता, त्याग सिखावनि,\nहरि-मुख की बानी।\nसकल शास्त्र की स्वामिनि,\nश्रुतियों की रानी।\nजय भगवद् गीते\n\nदया-सुधा बरसावनि,\nमातु! कृपा कीजै।\nहरिपद-प्रेम दान कर,\nअपनो कर लीजै।\nजय भगवद् गीते\n\nजय भगवद् गीते।\nहरि-हिय-कमल-विहारिणि,\nसुन्दर सुपुनीते।";
    $aarati_translit = "Jai Bhagavad Gite,\nJai Bhagavad Gite.\nHari-Hiy-Kamal-viharini,\nSundar Supunite.\nJai Bhagavad Gite\n\nKarm-Sumarm-Prakashini,\nKamasaktihara.\nTattvagyan-vikashini,\nVidya Brahm Para.\nJai Bhagavad Gite\n\nNishchal-Bhakti-Vidhayini,\nNirmal Malahari.\nSharan-Sahasy-Pradayini,\nSab Vidhi Sukhkari.\nJai Bhagavad Gite\n\nRag-Dvesh-Vidarini,\nKarini Mod Sada.\nBhav-Hhay-Harini,\nTarini Paramanandaprada.\nJai Bhagavad Gite\n\nAasur-Bhav-Vinashini,\nNashini Tam Rajani.\nDaivi Sad Gunadayini,\nHari-Rasika Sajani.\nJai Bhagavad Gite\n\nSamata, Tyag Sikhavani,\nHari-Mukh Ki Baani.\nSakal Shastra Ki Svamini,\nShrutiyon Ki Rani.\nJai Bhagavad Gite\n\nDaya-Sudha Barasavani,\nMaatu! Kripa Keejai.\nHaripad-Prem Daan Kar,\nApano Kar Leejai.\nJai Bhagavad Gite\n\nJai Bhagavad Gite.\nHari-Hiy-Kamal-viharini,\nSundar Supunite.";

    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ve_table} WHERE chapter_number = 99 AND sub_chapter = 2 AND verse_number = 1"));
    $data = ['chapter_number' => 99, 'sub_chapter' => 2, 'verse_number' => 1, 'verse_order' => 3, 'sanskrit_text' => $aarati_text, 'transliteration' => $aarati_translit, 'word_meanings' => ''];
    if ($exists) {
        $wpdb->update($ve_table, $data, ['chapter_number' => 99, 'sub_chapter' => 2, 'verse_number' => 1]);
    } else {
        $wpdb->insert($ve_table, $data);
    }
}

function geeta_migrate_v12() {
    global $wpdb;
    $prefix = $wpdb->prefix;

    $wpdb->query("ALTER TABLE {$prefix}gp_verses ADD COLUMN sub_chapter INT NOT NULL DEFAULT 1 AFTER chapter_number");
    $wpdb->query("ALTER TABLE {$prefix}gp_verses DROP INDEX chapter_verse");
    $wpdb->query("ALTER TABLE {$prefix}gp_verses ADD UNIQUE KEY ch_sub_verse (chapter_number, sub_chapter, verse_number)");

    $old_nyasa = [19, 20, 21, 22];
    $new_nyasa = [19 => 0, 20 => 0, 21 => 0, 22 => 99];

    foreach ($old_nyasa as $old_ch) {
        $new_ch = $new_nyasa[$old_ch];
        $wpdb->update($prefix . 'gp_chapters', ['chapter_number' => $new_ch], ['chapter_number' => $old_ch]);
        $wpdb->update($prefix . 'gp_verses', ['chapter_number' => $new_ch], ['chapter_number' => $old_ch]);
        $wpdb->update($prefix . 'gp_vivechans', ['chapter' => $new_ch], ['chapter' => $old_ch]);
    }

    $wpdb->update($prefix . 'gp_verses', ['sub_chapter' => 1], ['chapter_number' => 0]);
    $wpdb->update($prefix . 'gp_verses', ['sub_chapter' => 1], ['chapter_number' => 99, 'verse_number' => 1]);
    $wpdb->update($prefix . 'gp_verses', ['sub_chapter' => 1], ['chapter_number' => 99, 'verse_number' => 2]);

    delete_option('geeta_db_version');
    update_option('geeta_db_version', '1.2');
}
