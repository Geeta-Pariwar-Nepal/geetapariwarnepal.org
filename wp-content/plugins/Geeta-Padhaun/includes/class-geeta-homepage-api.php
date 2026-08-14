<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Geeta_Homepage_API {
    private const CH13_DB = 13;

    public static function register_routes() {
        register_rest_route('geeta-vivechans/v1', '/bootstrap', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_bootstrap'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('geeta-vivechans/v1', '/verse', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_verse'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('geeta-vivechans/v1', '/chapters', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_chapters'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function get_bootstrap(WP_REST_Request $request) {
        $lang = self::sanitize_lang($request->get_param('lang') ?: 'ne');
        $chapter = max(0, (int) ($request->get_param('chapter') ?: 0));
        $sub = max(1, (int) ($request->get_param('sub') ?: 1));
        $verse = max(0, (int) ($request->get_param('verse') ?: 0));

        return rest_ensure_response([
            'languages' => self::languages(),
            'chapters' => self::chapter_list($lang),
            'selected' => [
                'chapter' => $chapter,
                'sub' => $sub,
                'verse' => $verse,
                'lang' => $lang,
            ],
            'verse' => self::verse_payload($chapter, $sub, $verse, $lang),
        ]);
    }

    public static function get_verse(WP_REST_Request $request) {
        $lang = self::sanitize_lang($request->get_param('lang') ?: 'ne');
        $chapter = max(0, (int) $request->get_param('chapter'));
        $sub = max(1, (int) ($request->get_param('sub') ?: 1));
        $verse = max(0, (int) $request->get_param('verse'));

        return rest_ensure_response(self::verse_payload($chapter, $sub, $verse, $lang));
    }

    public static function get_chapters(WP_REST_Request $request) {
        $lang = self::sanitize_lang($request->get_param('lang') ?: 'ne');
        return rest_ensure_response(self::chapter_list($lang));
    }

    private static function is_regular_chapter($chapter) {
        return $chapter >= 1 && $chapter <= 18;
    }

    private static function get_chanting_url($chapter, $sub, $verse_number, $page_type = null) {
        if ($chapter === 0) {
            global $wpdb;
            $sc_table = $wpdb->prefix . 'gp_sub_chapters';
            $sub_info = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT image_start, image_end FROM {$sc_table} WHERE chapter_number = 0 AND sub_chapter = %d",
                    $sub
                ),
                ARRAY_A
            );
            if (!$sub_info) {
                return null;
            }
            $start = (int) $sub_info['image_start'];
            $end = (int) $sub_info['image_end'];
            $img_num = min($start + $verse_number - 1, $end);
            $path = sprintf('%s/geeta-vivechans/00-nyasa/%03d.webp', wp_upload_dir()['basedir'], $img_num);
            if (file_exists($path)) {
                return content_url(sprintf('uploads/geeta-vivechans/00-nyasa/%03d.webp', $img_num));
            }
            return null;
        }

        if ($chapter === 99) {
            if ($sub === 1) {
                $path = sprintf('%s/geeta-vivechans/19-kshama-yachana/%03d.webp', wp_upload_dir()['basedir'], $verse_number);
                if (file_exists($path)) {
                    return content_url(sprintf('uploads/geeta-vivechans/19-kshama-yachana/%03d.webp', $verse_number));
                }
            }
            return null;
        }

        $dir_num = str_pad((string) $chapter, 2, '0', STR_PAD_LEFT);
        $base_dir = sprintf('%s/geeta-vivechans/%s-chapter', wp_upload_dir()['basedir'], $dir_num);
        $base_url = content_url(sprintf('uploads/geeta-vivechans/%s-chapter/', $dir_num));

        if ($page_type === 'title') {
            $filename = 'title.webp';
        } elseif ($page_type === 'intro') {
            $filename = 'intro.webp';
        } elseif ($page_type === 'closure') {
            $filename = 'closure.webp';
        } else {
            $vn = $verse_number;
            if ($chapter === self::CH13_DB) {
                $vn = max(1, $vn - 1);
            }
            $filename = sprintf('%03d.webp', $vn);
        }

        if (file_exists($base_dir . '/' . $filename)) {
            return $base_url . $filename;
        }
        return null;
    }

    private static function verse_payload($chapter, $sub, $verse, $lang) {
        global $wpdb;

        $ch_table = $wpdb->prefix . 'gp_chapters';
        $sc_table = $wpdb->prefix . 'gp_sub_chapters';
        $ve_table = $wpdb->prefix . 'gp_verses';
        $tr_table = $wpdb->prefix . 'gp_verse_translations';
        $vi_table = $wpdb->prefix . 'gp_vivechans';
        $is_regular = self::is_regular_chapter($chapter);

        $chapter_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT chapter_number, name_hi, name_transliterated, name_translated, summary_hi, summary_en, verses_count
                 FROM {$ch_table} WHERE chapter_number = %d LIMIT 1",
                $chapter
            ),
            ARRAY_A
        );

        if (!$chapter_row) {
            return new WP_Error('not_found', 'Chapter not found.', ['status' => 404]);
        }

        if ($chapter === 0) {
            $sub_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT sub_chapter, name_hi, name_transliterated, name_translated, verses_count
                     FROM {$sc_table} WHERE chapter_number = 0 AND sub_chapter = %d",
                    $sub
                ),
                ARRAY_A
            );
            if (!$sub_row) {
                return new WP_Error('not_found', 'Sub-chapter not found.', ['status' => 404]);
            }
            $total_pages = (int) $sub_row['verses_count'];
        } else {
            $total_pages = $is_regular ? (int) $chapter_row['verses_count'] + 3 : (int) $chapter_row['verses_count'];
        }

        $verse = max(0, min($verse, max(0, $total_pages - 1)));

        $sanskrit = '';
        $transliteration = '';
        $word_meanings = '';
        $meaning = '';
        $commentary = '';
        $commentator_name = '';
        $verse_label = '';
        $verse_note = null;
        $vivechans = [];
        $chanting_image_url = null;
        $verse_row = null;

        if ($chapter === 0) {
            $db_order = $verse + 1;
            $verse_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, chapter_number, sub_chapter, verse_number, verse_order, sanskrit_text, transliteration, word_meanings
                     FROM {$ve_table}
                     WHERE chapter_number = 0 AND sub_chapter = %d AND verse_order = %d
                     LIMIT 1",
                    $sub,
                    $db_order
                ),
                ARRAY_A
            );
            if (!$verse_row) {
                return new WP_Error('not_found', 'Verse not found.', ['status' => 404]);
            }

            $sanskrit = (string) $verse_row['sanskrit_text'];
            $transliteration = (string) ($verse_row['transliteration'] ?: $verse_row['word_meanings']);
            $word_meanings = (string) $verse_row['word_meanings'];
            if ($sub === 1) {
                if ($verse === 0) {
                    $verse_label = '0.1';
                } elseif ($verse === 1) {
                    $verse_label = '0.2';
                } else {
                    $verse_label = (string) ($verse - 1);
                }
            } else {
                $verse_label = (string) $verse_row['verse_number'];
            }
            $chanting_image_url = self::get_chanting_url(0, $sub, (int) $verse_row['verse_number']);

        } elseif ($is_regular) {
            if ($verse === 0) {
                $sanskrit = '🏷️ ' . $chapter_row['name_hi'];
                $transliteration = $chapter_row['name_transliterated'];
                $meaning = 'Chapter ' . $chapter . ' — ' . $chapter_row['name_translated'];
                $verse_label = '00-1';
                $chanting_image_url = self::get_chanting_url($chapter, 1, 0, 'title');
            } elseif ($verse === 1) {
                $sanskrit = '📖 ' . $chapter_row['name_hi'];
                $transliteration = 'Introduction to Chapter ' . $chapter;
                $meaning = $lang === 'hi' ? ($chapter_row['summary_hi'] ?? '') : ($chapter_row['summary_en'] ?: $chapter_row['summary_hi']);
                $verse_label = '00-2';
                $chanting_image_url = self::get_chanting_url($chapter, 1, 0, 'intro');
            } elseif ($verse === $total_pages - 1) {
                $sanskrit = '🕉️ इति ' . $chapter_row['name_hi'] . ' समाप्तः';
                $transliteration = 'Colophon / Puṣpikā';
                $meaning = 'Thus concludes Chapter ' . $chapter . ' of the Bhagavad Gita. ॐ तत्सत्।';
                $verse_label = '99';
                $chanting_image_url = self::get_chanting_url($chapter, 1, 0, 'closure');
            } else {
                $db_verse_num = $verse - 1;
                $verse_row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id, chapter_number, sub_chapter, verse_number, verse_order, sanskrit_text, transliteration, word_meanings
                         FROM {$ve_table}
                         WHERE chapter_number = %d AND verse_number = %d
                         LIMIT 1",
                        $chapter,
                        $db_verse_num
                    ),
                    ARRAY_A
                );
                if (!$verse_row) {
                    return new WP_Error('not_found', 'Verse not found.', ['status' => 404]);
                }

                $sanskrit = (string) $verse_row['sanskrit_text'];
                $transliteration = (string) ($verse_row['transliteration'] ?: $verse_row['word_meanings']);
                $word_meanings = (string) $verse_row['word_meanings'];
                $verse_label = (string) $verse_row['verse_number'];

                if ($chapter === self::CH13_DB && (int) $verse_row['verse_number'] === 1) {
                    $verse_label = '1.0*';
                    $verse_note = 'In some editions of the Bhagavad Gita, this verse is omitted, and the next verse figures as the first verse.';
                } elseif ($chapter === self::CH13_DB && (int) $verse_row['verse_number'] === 2) {
                    $verse_label = '1.1';
                }

                $chanting_image_url = self::get_chanting_url($chapter, 1, (int) $verse_row['verse_number']);
            }
        } else {
            $db_order = $verse + 1;
            $verse_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, chapter_number, sub_chapter, verse_number, verse_order, sanskrit_text, transliteration, word_meanings
                     FROM {$ve_table}
                     WHERE chapter_number = %d AND verse_order = %d
                     LIMIT 1",
                    $chapter,
                    $db_order
                ),
                ARRAY_A
            );
            if (!$verse_row) {
                return new WP_Error('not_found', 'Verse not found.', ['status' => 404]);
            }

            $sanskrit = (string) $verse_row['sanskrit_text'];
            $transliteration = (string) ($verse_row['transliteration'] ?: $verse_row['word_meanings']);
            $word_meanings = (string) $verse_row['word_meanings'];
            $verse_label = (string) $verse_row['verse_number'];
            $chanting_image_url = self::get_chanting_url($chapter, (int) $verse_row['sub_chapter'], (int) $verse_row['verse_number']);
        }

        if ($verse_row) {
            $translation_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT lang, meaning_text, commentary_text, commentator_name
                     FROM {$tr_table}
                     WHERE verse_id = %d AND lang IN (%s, 'ne', 'en')
                     ORDER BY FIELD(lang, %s, 'ne', 'en')",
                    (int) $verse_row['id'],
                    $lang,
                    $lang
                ),
                ARRAY_A
            );

            $translation = null;
            foreach ($translation_rows as $row) {
                if (!empty($row['meaning_text']) || !empty($row['commentary_text'])) {
                    $translation = $row;
                    break;
                }
            }

            if ($translation) {
                $meaning = (string) $translation['meaning_text'];
                $commentary = (string) $translation['commentary_text'];
                $commentator_name = (string) $translation['commentator_name'];
            }

            $vivechans = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT speaker, video_url, date_iso, meaning_nepali
                     FROM {$vi_table}
                     WHERE chapter = %d AND sloka_number = %d AND lang = 'ne'
                     ORDER BY date_iso DESC, id DESC",
                    $chapter,
                    (int) $verse_row['verse_number']
                ),
                ARRAY_A
            );

            $vivechans = array_map(static function ($row) {
                return [
                    'speaker' => (string) $row['speaker'],
                    'video_url' => (string) $row['video_url'],
                    'date_iso' => (string) $row['date_iso'],
                    'meaning_nepali' => (string) $row['meaning_nepali'],
                ];
            }, $vivechans);
        }

        return [
            'chapter' => [
                'chapter_number' => (int) $chapter_row['chapter_number'],
                'name_hi' => (string) $chapter_row['name_hi'],
                'name_transliterated' => (string) $chapter_row['name_transliterated'],
                'name_translated' => (string) $chapter_row['name_translated'],
                'summary' => $lang === 'hi' ? (string) $chapter_row['summary_hi'] : (string) ($chapter_row['summary_en'] ?: $chapter_row['summary_hi']),
                'verses_count' => $total_pages,
                'sub' => $chapter === 0 ? $sub : null,
            ],
            'verse' => [
                'chapter_number' => (int) $chapter_row['chapter_number'],
                'verse_number' => $verse,
                'verse_label' => $verse_label,
                'verse_note' => $verse_note,
                'verse_order' => $verse,
                'sanskrit' => $sanskrit,
                'transliteration' => $transliteration,
                'word_meanings' => $word_meanings,
                'meaning' => $meaning,
                'commentary' => $commentary,
                'commentator_name' => $commentator_name,
                'chanting_image_url' => $chanting_image_url,
                'vivechans' => $vivechans,
            ],
        ];
    }

    private static function chapter_list($lang) {
        global $wpdb;

        $ch_table = $wpdb->prefix . 'gp_chapters';
        $sc_table = $wpdb->prefix . 'gp_sub_chapters';

        $rows = $wpdb->get_results(
            "SELECT chapter_number, name_hi, name_transliterated, name_translated, verses_count
             FROM {$ch_table}
             WHERE chapter_number IN (0, 98, 99) OR (chapter_number BETWEEN 1 AND 18)
             ORDER BY CAST(chapter_number AS UNSIGNED) ASC",
            ARRAY_A
        );

        $list = [];
        foreach ($rows as $row) {
            $n = (int) $row['chapter_number'];

            if ($n === 0) {
                $subs = $wpdb->get_results(
                    "SELECT sub_chapter, name_hi, name_transliterated, name_translated, verses_count
                     FROM {$sc_table} WHERE chapter_number = 0 ORDER BY sub_chapter ASC",
                    ARRAY_A
                );
                foreach ($subs as $s) {
                    $list[] = [
                        'chapter_number' => 0,
                        'sub' => (int) $s['sub_chapter'],
                        'verses_count' => (int) $s['verses_count'],
                        'name_hi' => (string) $s['name_hi'],
                        'name_transliterated' => (string) $s['name_transliterated'],
                        'name_translated' => (string) $s['name_translated'],
                        'label' => (string) $s['name_hi'] . ' (' . $s['name_transliterated'] . ')',
                    ];
                }
            } elseif (in_array($n, [98, 99], true)) {
                $list[] = [
                    'chapter_number' => $n,
                    'sub' => null,
                    'verses_count' => (int) $row['verses_count'],
                    'name_hi' => (string) $row['name_hi'],
                    'name_transliterated' => (string) $row['name_transliterated'],
                    'name_translated' => (string) $row['name_translated'],
                    'label' => (string) $row['name_hi'] . ' (' . $row['name_transliterated'] . ')',
                ];
            } else {
                $total = (int) $row['verses_count'] + 3;
                $list[] = [
                    'chapter_number' => $n,
                    'sub' => null,
                    'verses_count' => $total,
                    'name_hi' => (string) $row['name_hi'],
                    'name_transliterated' => (string) $row['name_transliterated'],
                    'name_translated' => (string) $row['name_translated'],
                    'label' => sprintf('Chapter %d — %s', $n, $lang === 'ne' ? $row['name_hi'] : $row['name_translated']),
                ];
            }
        }

        return $list;
    }

    private static function languages() {
        return [
            ['code' => 'ne', 'label' => 'नेपाली'],
            ['code' => 'en', 'label' => 'English'],
            ['code' => 'hi', 'label' => 'Hindi'],
        ];
    }

    private static function sanitize_lang($lang) {
        $lang = strtolower(sanitize_key($lang));
        return in_array($lang, ['ne', 'en', 'hi'], true) ? $lang : 'ne';
    }
}
