CREATE TABLE wp_gp_chapters (
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
);

CREATE TABLE wp_gp_verses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  chapter_number INT NOT NULL,
  verse_number INT NOT NULL,
  verse_order INT NOT NULL DEFAULT 0,
  external_id VARCHAR(100) NULL,
  sanskrit_text LONGTEXT NOT NULL,
  transliteration LONGTEXT NULL,
  word_meanings LONGTEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY chapter_verse (chapter_number, verse_number)
);

CREATE TABLE wp_gp_verse_translations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  verse_id BIGINT UNSIGNED NOT NULL,
  lang VARCHAR(10) NOT NULL,
  meaning_text LONGTEXT NULL,
  commentary_text LONGTEXT NULL,
  commentator_name VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY verse_lang (verse_id, lang)
);

CREATE TABLE wp_gp_vivechans (
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
);
