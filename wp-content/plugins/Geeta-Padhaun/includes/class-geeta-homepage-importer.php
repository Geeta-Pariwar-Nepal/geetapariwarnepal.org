<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Geeta_Homepage_Importer {
    public static function register() {
        add_action('admin_post_geeta_vivechans_import_seed', [__CLASS__, 'handle_import']);
        add_action('admin_menu', [__CLASS__, 'register_tools_page']);
    }

    public static function register_tools_page() {
        add_management_page(
            'Geeta-Padhaun App Import',
            'Geeta-Padhaun App Import',
            'manage_options',
            'geeta-vivechans-import',
            [__CLASS__, 'render_tools_page']
        );
    }

    public static function render_tools_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Geeta-Padhaun App Import</h1>
            <p>Use this once to seed the local WordPress MySQL tables from the repo data copied from `dineshpokhrel.com`.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="geeta_vivechans_import_seed">
                <?php wp_nonce_field('geeta_vivechans_import'); ?>
                <?php submit_button('Import Seed Data'); ?>
            </form>
            <?php if (isset($_GET['geeta_imported'])) : ?>
                <div class="notice notice-success"><p>Seed import completed.</p></div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('geeta_vivechans_import');

        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $prefix = $wpdb->prefix;

        dbDelta([
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
        ]);

        $chapters_table = $prefix . 'gp_chapters';
        $verses_table = $prefix . 'gp_verses';
        $translations_table = $prefix . 'gp_verse_translations';
        $vivechans_table = $prefix . 'gp_vivechans';

        $data_dir = GEETA_VIVECHANS_DIR . 'data';
        $chapter_file = $data_dir . '/chapter_summaries_hi.json';
        $verse_file = $data_dir . '/verse.json';
        $sample_translation_file = $data_dir . '/sample_verse_en.json';
        $vivechan_sql_file = $data_dir . '/learngeeta_vivechans_migration.sql';

        $chapters = json_decode((string) file_get_contents($chapter_file), true);
        $verses = json_decode((string) file_get_contents($verse_file), true);
        $sample_translations = json_decode((string) file_get_contents($sample_translation_file), true);

        if (!is_array($chapters) || !is_array($verses)) {
            wp_die('Source JSON files could not be read.');
        }

        $wpdb->query("TRUNCATE TABLE {$chapters_table}");
        $wpdb->query("TRUNCATE TABLE {$verses_table}");
        $wpdb->query("TRUNCATE TABLE {$translations_table}");
        $wpdb->query("TRUNCATE TABLE {$vivechans_table}");

        foreach ($chapters as $chapter) {
            $wpdb->insert($chapters_table, [
                'chapter_number' => (int) $chapter['chapter_number'],
                'name_hi' => (string) $chapter['name_hi'],
                'name_transliterated' => (string) $chapter['name_transliterated'],
                'name_translated' => (string) $chapter['name_translated'],
                'summary_hi' => (string) $chapter['summary_hi'],
                'summary_en' => (string) $chapter['summary_en'],
                'verses_count' => (int) $chapter['verses_count'],
            ]);
        }

        $sample_by_ref = [];
        foreach ($sample_translations as $row) {
            $sample_by_ref[(int) $row['chapter_number'] . ':' . (int) $row['verse_number']] = $row;
        }

        foreach ($verses as $verse) {
            $wpdb->insert($verses_table, [
                'chapter_number' => (int) $verse['chapter_number'],
                'verse_number' => (int) $verse['verse_number'],
                'verse_order' => (int) $verse['verse_order'],
                'external_id' => (string) $verse['externalId'],
                'sanskrit_text' => (string) $verse['text'],
                'transliteration' => (string) $verse['transliteration'],
                'word_meanings' => (string) $verse['word_meanings'],
            ]);

            $verse_id = (int) $wpdb->insert_id;
            $key = (int) $verse['chapter_number'] . ':' . (int) $verse['verse_number'];

            if (isset($sample_by_ref[$key])) {
                $sample = $sample_by_ref[$key];
                $commentary = '';
                $commentator = '';

                if (!empty($sample['commentaries'][0])) {
                    $commentary = (string) $sample['commentaries'][0]['text'];
                    $commentator = (string) $sample['commentaries'][0]['author_name'];
                }

                $wpdb->insert($translations_table, [
                    'verse_id' => $verse_id,
                    'lang' => 'en',
                    'meaning_text' => (string) ($sample['word_meanings'] ?? ''),
                    'commentary_text' => $commentary,
                    'commentator_name' => $commentator,
                ]);
            }
        }

        self::import_vivechans_from_sql($vivechan_sql_file, $vivechans_table, $wpdb);

        geeta_seed_data($wpdb, $prefix);

        wp_safe_redirect(add_query_arg('geeta_imported', '1', admin_url('tools.php?page=geeta-vivechans-import')));
        exit;
    }

    private static function import_vivechans_from_sql($sql_file, $table, $wpdb) {
        $contents = (string) file_get_contents($sql_file);
        if ($contents === '') {
            return;
        }

        preg_match_all(
            "/INSERT INTO learngeeta_vivechans \\(chapter, sloka_number, speaker, video_url, date_iso, meaning_nepali, lang\\) VALUES \\((\\d+), (\\d+), '((?:[^'\\\\]|\\\\.)*)', '((?:[^'\\\\]|\\\\.)*)', '((?:[^'\\\\]|\\\\.)*)', '((?:[^'\\\\]|\\\\.)*)', '((?:[^'\\\\]|\\\\.)*)'\\);/s",
            $contents,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $wpdb->insert($table, [
                'chapter' => (int) $match[1],
                'sloka_number' => (int) $match[2],
                'speaker' => stripslashes($match[3]),
                'video_url' => stripslashes($match[4]),
                'date_iso' => stripslashes($match[5]),
                'meaning_nepali' => stripslashes($match[6]),
                'lang' => stripslashes($match[7]),
            ]);
        }
    }
}
