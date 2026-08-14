# Geeta Pariwar Nepal — Parayan Booking System

**श्रीमद्भगवद्गीता पारायण बाचन बुकिङ** — a complete, production-ready WordPress plugin that replaces the old Google Form with a professional Gita Parayan booking portal. Built for **15,000+ volunteers**.

- Plugin: `gpn-parayan-booking`
- Text Domain: `gpn-parayan-booking`
- Requires: WordPress 6.0+, PHP 8.0+, MySQL 5.7+ (or MariaDB 10.3+)
- License: GPL-2.0-or-later

---

## 1. Installation

1. Copy the `gpn-parayan-booking` folder to `wp-content/plugins/`.
2. Activate **Geeta Pariwar Nepal Parayan Booking** from the Plugins screen.
3. On activation the plugin creates 6 custom tables and seeds default settings + notification templates.
4. Create a page, place the shortcode below, publish.

### Shortcode

```
[gpn_parayan_booking]
```

Optional `view` attribute for dedicated pages:

```
[gpn_parayan_booking view="booking"]
[gpn_parayan_booking view="my-booking"]
[gpn_parayan_booking view="chapters"]
```

> Tip: create one page with `[gpn_parayan_booking]` (full portal). The four landing buttons internally switch views on the same page.

### Recommended setup

- Create **Parayan Dates** first (`Geeta Parayan → Parayan Dates`) — daily, weekly, special or festival.
- Review **Settings** (`Geeta Parayan → Settings`) — enable/disable daily/weekly/waiting-list/approval/email/WhatsApp, booking closing hour, max booking days, landing copy and the declaration text.
- Configure **Email Templates** and **WhatsApp Templates**.
- Set up a cron job for reminders (see *Scheduled reminders*).

---

## 2. Features

### Frontend (public)
- **Landing page** — title, description and four buttons: Daily Parayan, Weekly Parayan, My Booking, Available Chapters.
- **Booking form** — 5 sections (Personal, Sadhak, Booking, Previous Participation, Declaration) with a stepper and smooth transitions.
- **Chapter cards** — 18 beautiful cards with colour states:
  - 🟢 Available (green) · 🔴 Booked (red) · 🟡 Waiting (yellow) · ⚪ Closed (grey)
  - Clicking an **available** chapter temporarily **reserves** it (10 min, 3 slots max) to prevent double-booking during checkout.
  - Booked chapters are disabled; fully-booked chapters can still join the **waiting list**.
- **My Booking** — search by PRN *or* mobile; shows upcoming bookings, status, chapter, date, approval state and admin remarks.
- **Available Chapters** — browse availability per date.

### Booking rules (all dynamic)
- One confirmed/approved booking per chapter per date.
- No duplicates: same PRN **or** same mobile cannot register twice for the same date.
- Full chapter → waiting list (if enabled).
- Cancellation of a confirmed booking **auto-promotes** the next waiting volunteer.
- Admin can override (force approve, move chapter, confirm).

### Admin (Geeta Parayan menu)
| Screen | Purpose |
|---|---|
| Dashboard | Today's bookings, upcoming, pending, waiting, cancelled + statistics + last-7-days chart + recent bookings + audit stream |
| Bookings | Searchable/filterable table (name, PRN, mobile, date, type, chapter, status) with pagination and actions: Approve, Reject, Confirm, Edit notes, Move Chapter, Send Email, Send WhatsApp, Print slip, Delete |
| Volunteers | Full sadhak database — every booking auto-creates/updates the profile (PRN, name, phone, email, level, trainer, services, participation history), QR code, edit, delete |
| Parayan Dates | Create/edit/close/delete daily, weekly, special, festival dates — 18 chapters, max bookings, open/closed |
| Chapter Management | Live per-date chapter matrix with assign/move |
| Approvals | Pending queue + waiting list with promote/reject |
| Reports | Daily / Weekly / Monthly / Yearly with export → CSV, Excel, PDF (print) |
| Email Templates | Placeholder-based templates |
| WhatsApp Templates | Placeholder-based templates, API-ready |
| Settings | All toggles, WhatsApp Cloud API keys, landing copy, declaration, dark mode, QR |
| Audit Log | Every action logged (user, action, IP, description) with search + purge |

### Notifications
- Automatic **confirmation**, **pending**, **approved**, **rejected**, **cancelled** and **waiting** emails via `wp_mail()`.
- **Reminder** email for tomorrow's approved/confirmed bookings.
- Admin notified on every new booking.
- WhatsApp messages rendered from templates and delivered as `wa.me` deep links out of the box; plug the **WhatsApp Business Cloud API** token/phone-id into Settings to send directly through the `transport()` abstraction.

### Reports & exports
- Aggregates: total/confirmed/pending/waiting/cancelled, unique volunteers, chapter distribution, participation trend, top volunteers.
- Exports from the Reports screen: CSV, Excel (CSV+BOM) and PDF (print-optimised HTML — save as PDF from the browser).

### Security
- All AJAX endpoints verify **nonces** (`check_ajax_referer`).
- Admin actions gated by `manage_options` (filterable via `gpn_pb_capability`).
- Every DB query uses `$wpdb->prepare()`.
- All inputs sanitized (`sanitize_text_field`, `absint`, `wp_kses_post`…), all outputs escaped (`esc_html`, `esc_attr`, `esc_url`).
- Custom tables (no `postmeta` abuse), indexed for 20k+ rows.
- JSON **Backup / Restore** in Settings.

### Bonus features
- Dashboard widgets, **Dark Mode** (admin, persisted per user), volunteer **QR codes**, print **booking slip**, **admin notes**, booking/audit **history**, **backup & restore**.

---

## 3. Database schema

| Table | Purpose |
|---|---|
| `{prefix}gpn_volunteers` | Sadhak profiles (unique PRN, indexed mobile/email) |
| `{prefix}gpn_parayan_dates` | Daily/Weekly/Special/Festival dates, chapters, capacity, status |
| `{prefix}gpn_bookings` | Bookings + waiting list (status, waiting_rank, chapter, booking_ref) |
| `{prefix}gpn_templates` | Email + WhatsApp templates |
| `{prefix}gpn_audit_log` | Full audit trail |
| `{prefix}gpn_settings` | Key/value settings (JSON) |

Booking statuses: `pending` · `approved` · `confirmed` · `waiting` · `rejected` · `cancelled`.

---

## 4. Scheduled reminders (recommended)

Add a cron line to your `wp-config.php`:

```php
define( 'DISABLE_WP_CRON', false );
```

and schedule from your theme's `functions.php` or a system cron (once daily, e.g. 09:00 Asia/Kathmandu):

```php
add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'gpn_pb_daily_reminder' ) ) {
        wp_schedule_event( time() + 60, 'daily', 'gpn_pb_daily_reminder' );
    }
} );
add_action( 'gpn_pb_daily_reminder', function () {
    GPN_PB_Mailer::send_daily_reminders();
} );
```

---

## 5. REST API (bonus)

Namespace `gpn-pb/v1`:

| Endpoint | Method | Access | Purpose |
|---|---|---|---|
| `/dates` | GET | Public | Upcoming open parayan dates |
| `/availability/{id}` | GET | Public | Chapter availability cards for a date |
| `/bookings/{query}` | GET | Public | My-booking lookup by PRN/mobile |
| `/stats` | GET | Admin | Dashboard statistics |

---

## 6. Developer hooks

```php
// Custom admin capability.
add_filter( 'gpn_pb_capability', fn() => 'manage_options' );

// Override the WhatsApp transport (connect your provider).
// class GPN_PB_WhatsApp::transport() is the single point of integration.
```

Template placeholders (both email & WhatsApp):

```
{name} {prn} {mobile} {email} {date} {chapter} {type} {booking_ref}
{status} {admin_notes} {site_name} {site_url}
```

---

## 7. Files & structure

```
gpn-parayan-booking/
├── gpn-parayan-booking.php        Main bootstrap + autoloader
├── uninstall.php                  Clean table removal
├── README.md
├── includes/                      PHP classes (autoloaded)
│   ├── class-gpn-activator.php
│   ├── class-gpn-database.php     Schema + migrations
│   ├── class-gpn-volunteer.php
│   ├── class-gpn-booking.php      Business rules + waiting list + reservations
│   ├── class-gpn-parayan-date.php
│   ├── class-gpn-setting.php
│   ├── class-gpn-template.php
│   ├── class-gpn-mailer.php
│   ├── class-gpn-whatsapp.php
│   ├── class-gpn-audit-log.php
│   ├── class-gpn-report.php
│   ├── class-gpn-rest-api.php
│   ├── class-gpn-admin.php        Menus + AJAX + exports
│   ├── class-gpn-public.php       Shortcode + front-end AJAX
│   └── helpers.php
├── admin/
│   ├── css/admin.css
│   ├── js/admin.js
│   └── views/                     Admin page templates
├── public/
│   ├── css/public.css
│   ├── js/public.js
│   └── views/                     Landing / form / my-booking / chapters
├── assets/                        Icons & fonts
└── languages/
```

---

## 8. Notes & third-party assets

- **Bootstrap 5** and the **QR code image** are loaded from public CDNs (jsdelivr / api.qrserver.com). For fully offline operation, download Bootstrap into the plugin and update the enqueue URLs in `class-gpn-public.php` / `class-gpn-admin.php`.
- PDF export is browser-print friendly HTML (open and “Save as PDF”) — no external binary required.
- WhatsApp API integration is ready — add the Cloud API token + phone number ID in Settings.

Hare Krishna! 🕉️
