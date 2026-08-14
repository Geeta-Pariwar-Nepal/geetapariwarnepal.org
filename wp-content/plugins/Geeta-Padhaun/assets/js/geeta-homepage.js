(function () {
    const state = {
        chapterIdx: -1,
        verse: 0,
        lang: (window.GeetaVivechansConfig && window.GeetaVivechansConfig.defaultLanguage) || "ne",
        chapters: [],
        loading: false,
        imageVisible: false,
        tvMode: false,
        colophonVisible: false,
    };

    const els = {
        chapterSelect: document.getElementById("geeta-chapter-select"),
        languageSelect: document.getElementById("geeta-language-select"),
        chapterMeta: document.getElementById("geeta-chapter-meta"),
        verseList: document.getElementById("geeta-verse-list"),
        currentRef: document.getElementById("geeta-current-ref"),
        sanskrit: document.getElementById("geeta-sanskrit"),
        transliteration: document.getElementById("geeta-transliteration"),
        meaning: document.getElementById("geeta-meaning"),
        commentary: document.getElementById("geeta-commentary"),
        vivechans: document.getElementById("geeta-vivechans"),
        prev: document.getElementById("geeta-prev-verse"),
        next: document.getElementById("geeta-next-verse"),
        chantingImg: document.getElementById("geeta-chanting-img"),
        toggleChanting: document.getElementById("geeta-toggle-chanting"),
        tvMode: document.getElementById("geeta-tv-mode"),
        toggleRow: document.getElementById("geeta-toggle-row"),
        verseNote: document.getElementById("geeta-verse-note"),
        titleImg: document.getElementById("geeta-title-img"),
    };

    var uploadsUrl = null;

    function getCurrentCh() {
        return state.chapters[state.chapterIdx] || null;
    }

    function hasTitleImage(ch) {
        return ch >= 1 && ch <= 18;
    }

    function getUploadsUrl() {
        if (uploadsUrl) return uploadsUrl;
        if (els.titleImg && els.titleImg.src) {
            var match = els.titleImg.src.match(/(.+\/)0\d-chapter\//);
            if (match) uploadsUrl = match[1];
        }
        return uploadsUrl;
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatText(text) {
        return escapeHtml(text).replace(/\n/g, "<br>");
    }

    function goPrev() {
        if (state.chapterIdx === 0 && state.verse === 0) return;
        if (state.verse > 0) {
            state.verse -= 1;
        } else {
            state.chapterIdx -= 1;
            state.verse = state.chapters[state.chapterIdx].verses_count - 1;
            syncSelect();
            updateTitleImage();
        }
        loadVerse();
    }

    function goNext() {
        var ch = getCurrentCh();
        if (!ch) return;
        var maxVerse = ch.verses_count;
        var isLastCh = state.chapterIdx === state.chapters.length - 1;

        if (isLastCh && state.verse >= maxVerse - 1) return;
        if (state.verse < maxVerse - 1) {
            state.verse += 1;
        } else {
            state.chapterIdx += 1;
            state.verse = 0;
            syncSelect();
            updateTitleImage();
        }
        loadVerse();
    }

    function syncSelect() {
        if (els.chapterSelect) {
            els.chapterSelect.value = String(state.chapterIdx);
        }
    }

    function getChapterImageDir(ch) {
        return String(ch).padStart(2, "0");
    }

    function updateTitleImage() {
        if (!els.titleImg) return;
        var ch = getCurrentCh();
        var chNum = ch ? ch.chapter_number : 0;
        var card = els.titleImg.closest ? els.titleImg.closest(".geeta-card") : null;
        if (!hasTitleImage(chNum)) {
            if (card) card.style.display = "none";
            return;
        }
        if (card) card.style.display = "";
        var base = getUploadsUrl();
        if (!base) return;
        els.titleImg.src = base + getChapterImageDir(chNum) + "-chapter/title.webp";
    }

    function setLoading(on) {
        state.loading = on;
        els.prev.disabled = on;
        els.next.disabled = on;
        if (els.chapterSelect) els.chapterSelect.disabled = on;
        if (els.languageSelect) els.languageSelect.disabled = on;
    }

    async function getJson(path) {
        var response = await fetch(GeetaVivechansConfig.apiBase + path, {
            headers: { "X-WP-Nonce": GeetaVivechansConfig.nonce },
        });

        if (!response.ok) {
            throw new Error("Request failed: " + response.status);
        }

        return response.json();
    }

    function renderSelects(data) {
        state.chapters = data.chapters || [];

        if (els.chapterSelect) {
            els.chapterSelect.innerHTML = state.chapters.map(function (chapter, idx) {
                return '<option value="' + idx + '">' + escapeHtml(chapter.label) + "</option>";
            }).join("");
            els.chapterSelect.value = String(state.chapterIdx);
        }

        if (els.languageSelect) {
            els.languageSelect.innerHTML = (data.languages || []).map(function (lang) {
                return '<option value="' + lang.code + '">' + escapeHtml(lang.label) + "</option>";
            }).join("");
            els.languageSelect.value = state.lang;
        }
    }

    function getVerseLabel(ch, sub, total, i) {
        if (ch === 0 && sub === 1) {
            if (i === 0) return "0.1";
            if (i === 1) return "0.2";
            return String(i - 1);
        }
        if (ch === 0 || ch >= 98) {
            return String(i + 1);
        }
        if (i === 0) return "00-1";
        if (i === 1) return "00-2";
        if (i === total - 1) return "Puṣpikā";
        var dbVerse = i - 1;
        if (ch === 13 && dbVerse === 1) return "1.0*";
        if (ch === 13 && dbVerse === 2) return "1.1";
        return String(dbVerse);
    }

    function renderVerseLinks(chapterMeta) {
        if (!els.verseList) return;
        var ch = getCurrentCh();
        if (!ch) return;
        var total = ch.verses_count || 0;
        var html = "";
        for (var i = 0; i < total; i += 1) {
            var active = i === state.verse ? " is-active" : "";
            var label = getVerseLabel(ch.chapter_number, ch.sub || 1, total, i);
            html += '<button type="button" class="geeta-verse-pill' + active + '" data-verse="' + i + '">' + label + "</button>";
        }
        els.verseList.innerHTML = html || '<p class="geeta-empty">No verses.</p>';
    }

    function renderVivechans(vivechans) {
        if (!els.vivechans) return;
        if (!vivechans || vivechans.length === 0) {
            els.vivechans.innerHTML = '<p class="geeta-empty">No vivechan entries available for this verse yet.</p>';
            return;
        }

        els.vivechans.innerHTML = vivechans.map(function (item) {
            return (
                '<article class="geeta-vivechan">' +
                "<h3>" + escapeHtml(item.speaker || "Unknown Speaker") + "</h3>" +
                (item.date_iso ? '<p class="geeta-vivechan__date">' + escapeHtml(item.date_iso) + "</p>" : "") +
                '<div class="geeta-richtext">' + formatText(item.meaning_nepali || "") + "</div>" +
                (item.video_url ? '<p><a href="' + escapeHtml(item.video_url) + '" target="_blank" rel="noopener noreferrer">Watch on YouTube</a></p>' : "") +
                "</article>"
            );
        }).join("");
    }

    function renderVerse(payload) {
        if (!payload || !payload.chapter || !payload.verse) {
            els.sanskrit.innerHTML = "";
            els.transliteration.innerHTML = '<p class="geeta-empty">No data available.</p>';
            els.meaning.innerHTML = "";
            els.commentary.innerHTML = "";
            els.vivechans.innerHTML = "";
            if (els.chapterMeta) els.chapterMeta.innerHTML = "";
            if (els.verseList) els.verseList.innerHTML = "";
            return;
        }

        var chapter = payload.chapter;
        var verse = payload.verse;
        var verseLabel = verse.verse_label || verse.verse_number;

        if (els.currentRef) {
            els.currentRef.textContent = "Chapter " + chapter.chapter_number + ", Verse " + verseLabel;
        }

        if (els.verseNote) {
            if (verse.verse_note) {
                els.verseNote.textContent = verse.verse_note;
                els.verseNote.style.display = "block";
            } else {
                els.verseNote.style.display = "none";
            }
        }

        els.chapterMeta.innerHTML =
            "<p><strong>" + escapeHtml(chapter.name_hi) + "</strong></p>" +
            "<p>" + escapeHtml(chapter.name_transliterated) + " / " + escapeHtml(chapter.name_translated) + "</p>" +
            "<p>" + formatText(chapter.summary || "") + "</p>";

        els.sanskrit.innerHTML = verse.sanskrit
            ? formatText(verse.sanskrit)
            : '<p class="geeta-empty">Sanskrit text not available.</p>';

        els.transliteration.innerHTML = (verse.transliteration || verse.word_meanings)
            ? formatText(verse.transliteration || verse.word_meanings)
            : '<p class="geeta-empty">Transliteration not available.</p>';

        els.meaning.innerHTML = verse.meaning
            ? formatText(verse.meaning)
            : '<p class="geeta-empty">Meaning not imported yet.</p>';

        els.commentary.innerHTML = verse.commentary
            ? '<p class="geeta-commentator">' + escapeHtml(verse.commentator_name || "Commentary") + "</p>" + formatText(verse.commentary)
            : '<p class="geeta-empty">Commentary not imported yet.</p>';

        renderVerseLinks(chapter);
        renderVivechans(verse.vivechans);
        updateNavButtons(chapter.verses_count);

        if (els.chantingImg && els.toggleChanting && els.toggleRow) {
            if (verse.chanting_image_url) {
                els.chantingImg.dataset.src = verse.chanting_image_url;
                els.toggleRow.classList.add("is-visible");

                if (state.imageVisible || state.tvMode) {
                    els.chantingImg.src = verse.chanting_image_url;
                    els.chantingImg.style.display = "block";
                } else {
                    els.chantingImg.style.display = "none";
                }
            } else {
                els.chantingImg.style.display = "none";
                els.toggleRow.classList.remove("is-visible");
                state.imageVisible = false;
            }
        }
    }

    function updateNavButtons(maxVerse) {
        if (!els.prev || !els.next) return;
        if (state.loading) return;
        els.prev.disabled = state.chapterIdx === 0 && state.verse === 0;
        els.next.disabled = state.chapterIdx === state.chapters.length - 1 && state.verse === maxVerse - 1;
    }

    function buildApiPath(base, extra) {
        var ch = getCurrentCh();
        var path = base + "?chapter=" + (ch ? ch.chapter_number : 0) + "&sub=" + (ch ? (ch.sub || 1) : 1) + "&verse=" + state.verse + "&lang=" + state.lang;
        if (extra) path += extra;
        return path;
    }

    async function loadVerse() {
        setLoading(true);
        try {
            var payload = await getJson(buildApiPath("/verse"));
            renderVerse(payload);
        } catch (err) {
            console.error("Failed to load verse:", err);
        } finally {
            setLoading(false);
        }
    }

    async function bootstrap() {
        setLoading(true);
        try {
            var data = await getJson("/bootstrap?chapter=0&sub=1&verse=0&lang=" + state.lang);
            renderSelects(data);

            if (state.chapterIdx < 0 && data.chapters && data.chapters.length > 0) {
                var firstRegular = data.chapters.findIndex(function(item) { return item.chapter_number >= 1; });
                state.chapterIdx = firstRegular >= 0 ? firstRegular : 0;
            }

            await loadVerse();
            updateTitleImage();
        } catch (err) {
            console.error("Failed to bootstrap:", err);
        } finally {
            setLoading(false);
        }
    }

    if (els.chapterSelect) {
        els.chapterSelect.addEventListener("change", async function (event) {
            state.chapterIdx = Number(event.target.value);
            state.verse = 0;
            updateTitleImage();
            await loadVerse();
        });
    }

    if (els.languageSelect) {
        els.languageSelect.addEventListener("change", async function (event) {
            state.lang = event.target.value;
            await bootstrap();
        });
    }

    if (els.verseList) {
        els.verseList.addEventListener("click", async function (event) {
            var button = event.target.closest("[data-verse]");
            if (!button) return;
            state.verse = Number(button.dataset.verse);
            await loadVerse();
        });
    }

    if (els.prev) {
        els.prev.addEventListener("click", goPrev);
    }

    if (els.next) {
        els.next.addEventListener("click", goNext);
    }

    if (els.toggleChanting) {
        els.toggleChanting.addEventListener("click", function () {
            state.imageVisible = !state.imageVisible;
            if (state.imageVisible && els.chantingImg) {
                els.chantingImg.src = els.chantingImg.dataset.src || "";
                els.chantingImg.style.display = "block";
                els.toggleChanting.textContent = "Hide Chanting Format";
            } else if (els.chantingImg) {
                els.chantingImg.style.display = "none";
                els.toggleChanting.textContent = "Show Chanting Format";
            }
        });
    }

    function toggleFullscreen(on) {
        if (on) {
            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(function () {});
            }
        } else {
            if (document.fullscreenElement && document.exitFullscreen) {
                document.exitFullscreen().catch(function () {});
            }
        }
    }

    if (els.tvMode) {
        els.tvMode.addEventListener("click", function () {
            state.tvMode = !state.tvMode;

            if (state.tvMode) {
                document.body.classList.add("geeta-tv-mode");
                els.tvMode.classList.add("is-active");
                els.tvMode.textContent = "📺 TV Mode ON";
                toggleFullscreen(true);

                if (els.chantingImg && els.chantingImg.dataset.src) {
                    els.chantingImg.src = els.chantingImg.dataset.src;
                    els.chantingImg.style.display = "block";
                    state.imageVisible = true;
                    els.toggleChanting.textContent = "Hide Chanting Format";
                }
            } else {
                document.body.classList.remove("geeta-tv-mode");
                els.tvMode.classList.remove("is-active");
                els.tvMode.textContent = "📺 TV Mode";
                toggleFullscreen(false);
            }
        });
    }

    document.addEventListener("keydown", function (e) {
        if (!state.tvMode) return;
        if (state.loading) return;

        if (e.key === "ArrowRight") {
            e.preventDefault();
            goNext();
        } else if (e.key === "ArrowLeft") {
            e.preventDefault();
            goPrev();
        }
    });

    if (els.toggleColophon && els.colophonSection) {
        els.toggleColophon.addEventListener("click", function () {
            state.colophonVisible = !state.colophonVisible;
            els.colophonSection.style.display = state.colophonVisible ? "block" : "none";
            els.toggleColophon.textContent = state.colophonVisible ? "📜 Hide Colophon / Puṣpikā" : "📜 Colophon / Puṣpikā";
        });
    }

    document.addEventListener("contextmenu", function (e) {
        var target = e.target;
        if (target.classList && target.classList.contains("geeta-protected-img")) {
            e.preventDefault();
            return false;
        }
    });

    document.addEventListener("dragstart", function (e) {
        var target = e.target;
        if (target.classList && target.classList.contains("geeta-protected-img")) {
            e.preventDefault();
            return false;
        }
    });

    bootstrap();
})();
