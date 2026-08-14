#!/usr/bin/env python3
"""Convert ROMAN MERGED FINAL.pdf to chapter/verse organized WebP images."""

from pdf2image import convert_from_path
from pathlib import Path
import sys

PDF_PATH = "/home/risupro/Downloads/ROMAN MERGED FINAL.pdf"
OUTPUT_DIR = Path("/home/risupro/dev/web/dineshpokhrel.com/staging.geetapariwarnepal.org/wp-content/uploads/geeta-vivechans")
DPI = 150
QUALITY = 85

VERSE_COUNTS = {
    1: 47, 2: 72, 3: 43, 4: 42, 5: 29, 6: 47, 7: 30, 8: 28,
    9: 34, 10: 42, 11: 55, 12: 20, 13: 35, 14: 27, 15: 20,
    16: 24, 17: 28, 18: 78,
}


def page_of(chapter: int, verse: int) -> int:
    """Calculate PDF page number (1-indexed) for a given chapter/verse."""
    pg = 22  # nyasa occupies pages 1-22
    for c in range(1, chapter):
        pg += 3 + VERSE_COUNTS[c]  # title + intro + verses + closure
    pg += 2  # current chapter title + intro
    pg += verse
    return pg


def main():
    print(f"Reading PDF: {PDF_PATH}")
    print(f"Total pages: 778")
    pages = convert_from_path(PDF_PATH, dpi=DPI)
    print(f"Converted {len(pages)} pages at {DPI} DPI")

    # 1) Nyasa – pages 1-22
    nyasa_dir = OUTPUT_DIR / "00-nyasa"
    nyasa_dir.mkdir(parents=True, exist_ok=True)
    for i in range(22):
        out = nyasa_dir / f"{i+1:03d}.webp"
        pages[i].save(str(out), "WEBP", quality=QUALITY)
        print(f"  Nyasa {i+1:03d}.webp")
    print(f"✓ Nyasa: 22 pages")

    # 2) Chapters
    for ch in range(1, 19):
        ch_dir = OUTPUT_DIR / f"{ch:02d}-chapter"
        ch_dir.mkdir(parents=True, exist_ok=True)

        start = page_of(ch, 0) - 2  # title page
        # Title
        pages[start].save(str(ch_dir / "title.webp"), "WEBP", quality=QUALITY)
        print(f"  Ch{ch:02d} title.webp (page {start+1})")
        # Intro
        pages[start + 1].save(str(ch_dir / "intro.webp"), "WEBP", quality=QUALITY)
        print(f"  Ch{ch:02d} intro.webp (page {start+2})")

        # Verses
        for v in range(1, VERSE_COUNTS[ch] + 1):
            pdf_pg = page_of(ch, v)
            out = ch_dir / f"{v:03d}.webp"
            pages[pdf_pg - 1].save(str(out), "WEBP", quality=QUALITY)

        print(f"  Ch{ch:02d} verses 001-{VERSE_COUNTS[ch]:03d}")

        # Closure (for Ch 18, closure is on same page as last verse)
        if ch == 18:
            print(f"  Ch{ch:02d} closure on same page as last verse (page 776)")
        else:
            closure_pg = page_of(ch, VERSE_COUNTS[ch]) + 1
            pages[closure_pg - 1].save(
                str(ch_dir / "closure.webp"), "WEBP", quality=QUALITY
            )
            print(f"  Ch{ch:02d} closure.webp (page {closure_pg})")
        print(f"✓ Chapter {ch} done")

    # 3) Kshama Yachana – pages 777-778
    ky_dir = OUTPUT_DIR / "19-kshama-yachana"
    ky_dir.mkdir(parents=True, exist_ok=True)
    pages[776].save(str(ky_dir / "001.webp"), "WEBP", quality=QUALITY)
    pages[777].save(str(ky_dir / "002.webp"), "WEBP", quality=QUALITY)
    print(f"✓ Kshama Yachana: 2 pages")

    total = 22 + sum(3 + c for c in VERSE_COUNTS.values()) + 2
    print(f"\n✅ Done! {total} WebP files in {OUTPUT_DIR}")
    print(f"   Nyasa:       {nyasa_dir}")
    print(f"   Chapters:    {OUTPUT_DIR}/*-chapter/")
    print(f"   Kshama:      {ky_dir}")


if __name__ == "__main__":
    main()
