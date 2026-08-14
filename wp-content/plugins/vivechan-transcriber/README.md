# Vivechan Transcriber

YouTube transcript cleaner with AI (Groq / DeepSeek / Gemini), running entirely inside WordPress. This is a port of the "Learn Geeta Transcriber" desktop app to a WordPress plugin for geetapariwarnepal.org.

- **Frontend**: the existing React app, compiled to static files (no Node needed on the server).
- **Backend**: a WordPress plugin — custom MySQL tables, WP REST API, WP-Cron job runner.
- **Auth**: only logged-in users with the **Vivechak** role (or Administrators) can use it.

## Access & roles

| Role | Can transcribe | Manage system prompts | Manage AI integrations (API keys) |
|------|----------------|----------------------|-----------------------------------|
| **Vivechak** | ✅ | ✅ (own prompts) | ❌ |
| **Administrator** | ✅ | ✅ | ✅ |
| Everyone else | ❌ | ❌ | ❌ |

- The `Vivechak` role is created on activation. Assign it in **Users → Your Profile / Edit User → Role**.
- Non-logged-in visitors see a login prompt. Logged-in users without the role see "access denied".
- Transcripts are scoped per user; administrators can see everything.
- API keys are encrypted with **AES-256-GCM** (key derived from the WP auth salt) and never returned by the API.

## Install (staging.geetapariwarnepal.org)

1. **Upload**: wp-admin → **Plugins → Add New → Upload Plugin** → choose `vivechan-transcriber.zip` → **Install Now** → **Activate**.
   This creates the DB tables, the Vivechak role, the default "Nepali Proofreading" prompt, and schedules the watchdog cron.
2. **Add the tool to a page**: create/edit a page (e.g. under Learn Geeta → Vivechans) and add the shortcode:
   ```
   [vivechan_transcriber]
   ```
   Visitors will see a login prompt; Vivechaks get the app.
3. **(Optional) YouTube Data API key**: wp-admin → **Vivechan** menu → paste a key (used to discover caption languages; stored encrypted).

### Real cron (important)

WP-Cron only runs when someone visits the site. For reliable background processing set up a real cron:

1. In **hPanel → Advanced → Cron Jobs** (or the plugin's File Manager), add a job running every minute:
   ```
   php /home/UNAME/public_html/wp-cron.php >/dev/null 2>&1
   ```
   (Replace the path with your site's path.)
2. In **hPanel File Manager**, open `wp-config.php` and add:
   ```php
   define('DISABLE_WP_CRON', true);
   ```

Jobs are designed for shared hosting: one HTTP call or one AI chunk per cron tick, with self-rescheduling, MySQL advisory locking, and a watchdog that marks lost jobs as errors.

## Migrating data from the desktop app

The old app stored data in `data/transcripts.db` (SQLite). To import it:

1. Upload `transcripts.db` to the server (e.g. `wp-content/plugins/vivechan-transcriber/migration/`).
2. Run via SSH/terminal on the server:
   ```
   php wp-content/plugins/vivechan-transcriber/migration/import-sqlite.php --file=/path/to/transcripts.db
   ```
   Requires `pdo_sqlite`. In-flight transcripts are imported as `ERROR` so they can be retried from the UI. API keys are re-encrypted during import.

## How it works

**Flow:** paste a YouTube URL → subtitles are fetched → status becomes **Review** → a Vivechak reviews/edits the raw transcript → approves → AI cleans it chunk by chunk → **Completed**.

**Subtitle sources (fallback chain):**
1. YouTube `timedtext` endpoint (no key) for en/hi/ne (+ any languages discovered via the optional Data API key)
2. `yt-to-text.com` (the original unofficial endpoint)
3. YouTube Data API v3 (quota-aware, when a key is configured)

**AI providers:** Groq & DeepSeek via their OpenAI-compatible APIs, Gemini via the Generative Language API. Rate limits (429/503) are retried with exponential backoff and respect `Retry-After` / Gemini `retryDelay`.

**Security**
- Every REST route requires a logged-in user with the `vivechan_transcribe` capability + a valid `X-WP-Nonce`.
- Per-user and site-wide concurrency caps + a per-user rate limit on new transcript requests.
- All DB access uses `$wpdb` prepared statements; all output is escaped/sanitized.
- API keys never leave the server unencrypted.

## Development

The React app lives in the sibling `vivechan-translation-main/frontend` directory. After editing it, rebuild and copy the bundle into the plugin:

```bash
cd frontend
npm install && npm run build
rm -rf ../vivechan-transcriber/assets/app/*
cp -R build/. ../vivechan-transcriber/assets/app/
```

The shortcode serves the built `index.html` (parsing its `<link>`/`<script>` tags) so hashed asset names never need updating.

## Notes / limitations

- Hostinger's shared server IP may occasionally be blocked by `yt-to-text.com` — the timedtext-first chain keeps this low impact.
- If a job is stuck on a low-traffic site without the real cron setup, the watchdog marks it `ERROR` after 10 minutes; retry from the UI.
- `yt-to-text.com` is an unofficial endpoint; YouTube's official API and timedtext endpoints are preferred.
