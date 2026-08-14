<?php
/**
 * Template Name: Geeta-Padhaun App Template
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
$uploads_url = content_url('uploads/geeta-vivechans');
$chapter_pdfs = [];
for ($i = 1; $i <= 18; $i++) {
    $chapter_pdfs[$i] = "https://content.learngeeta.com/book/assets/pdfs/hi/ch{$i}_hi.pdf";
}
?>

<main class="geeta-homepage">
    <section class="geeta-homepage__hero">
        <div class="geeta-homepage__wrap">
            <div class="geeta-homepage__intro">
                <p class="geeta-homepage__eyebrow">गीता पढौँ | पढाऔँ / ज्ञान बाँडौँ | जीवनमा उतारौँ / लागू गरौँ</p>
                <h1>Geeta-Padhaun App</h1>
            </div>

            <div class="geeta-header-row">
                <div class="geeta-header-box">
                    <h3 class="geeta-header-label">Learn Geeta PDF's</h3>
                    <div class="geeta-pdf-strip">
                        <?php foreach ($chapter_pdfs as $ch => $url): ?>
                        <a href="<?php echo esc_url($url); ?>"
                           target="_blank" rel="noopener"
                           class="geeta-pdf-chip">
                            Ch.<?php echo $ch; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="geeta-header-box">
                    <div class="geeta-homepage__controls">
                        <div class="geeta-control-group">
                            <label for="geeta-chapter-select">Chapter</label>
                            <select id="geeta-chapter-select"></select>
                        </div>
                        <div class="geeta-control-group">
                            <label for="geeta-language-select">Language</label>
                            <select id="geeta-language-select"></select>
                        </div>
                    </div>
                </div>
                <div class="geeta-header-box">
                    <h3 class="geeta-header-label">Options</h3>
                    <p class="geeta-empty" style="margin:0;font-size:0.82rem;">More features coming soon…</p>
                </div>
            </div>
        </div>
    </section>

    <section class="geeta-homepage__content">
        <div class="geeta-homepage__wrap geeta-homepage__grid">
            <aside class="geeta-homepage__sidebar">

                <div class="geeta-card" id="geeta-card-title">
                    <h2>Title</h2>
                    <div class="geeta-sidebar-images">
                        <img id="geeta-title-img"
                             src="<?php echo esc_url("$uploads_url/01-chapter/title.webp"); ?>"
                             loading="lazy" alt="Chapter title"
                             class="geeta-protected-img" draggable="false">
                    </div>
                </div>

                <div class="geeta-card">
                    <h2>Slokas</h2>
                    <div id="geeta-verse-list" class="geeta-verse-list"></div>
                </div>

            </aside>

            <div class="geeta-homepage__main">
                <div class="geeta-card" id="geeta-verse-card">
                    <div class="geeta-verse-nav">
                        <button type="button" id="geeta-prev-verse" class="geeta-nav-btn">← Previous</button>
                        <strong id="geeta-current-ref">Chapter 1</strong>
                        <button type="button" id="geeta-next-verse" class="geeta-nav-btn">Next →</button>
                    </div>
                    <div id="geeta-verse-note" class="geeta-verse-note" style="display:none"></div>
                    <div id="geeta-sanskrit" class="geeta-richtext geeta-sanskrit-text"></div>
                    <div class="geeta-toggle-row" id="geeta-toggle-row">
                        <button type="button" id="geeta-toggle-chanting" class="geeta-toggle-btn">Show Chanting Format</button>
                        <button type="button" id="geeta-tv-mode" class="geeta-toggle-btn geeta-tv-btn">📺 TV Mode</button>
                    </div>
                    <img id="geeta-chanting-img" loading="lazy" alt="Chanting format" class="geeta-chanting-img geeta-protected-img" style="display:none" draggable="false" />
                </div>

                <div class="geeta-card">
                    <h2>Transliteration / Word Meaning</h2>
                    <div id="geeta-transliteration" class="geeta-richtext"></div>
                </div>

                <div class="geeta-card">
                    <h2>Meaning</h2>
                    <div id="geeta-meaning" class="geeta-richtext"></div>
                </div>

                <div class="geeta-card">
                    <h2>Commentary</h2>
                    <div id="geeta-commentary" class="geeta-richtext"></div>
                </div>

                <div class="geeta-card">
                    <h2>Vivechans</h2>
                    <div id="geeta-vivechans"></div>
                </div>

                <div class="geeta-card">
                    <h2>Chapter Summary</h2>
                    <div id="geeta-chapter-meta"></div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
