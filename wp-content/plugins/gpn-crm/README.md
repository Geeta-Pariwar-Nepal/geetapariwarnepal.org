# Geeta Pariwar Nepal Sadhak CRM (WordPress Plugin)

A complete Sadhak (devotee) management CRM for **Geeta Pariwar Nepal**, migrated
1:1 from the original Python/Tkinter desktop application into a WordPress plugin.

## Features

- **Dashboard** – total sadhaks, today's additions, ready count, group counts,
  level-wise and batch-wise charts, quick actions.
- **Sadhaks** – searchable, sortable, paginated grid with the full desktop column
  set (Name, Phone, Email, PRN, Group, Level, Batch, BC, GC, CT, TA, status,
  timestamps, created/updated by). WhatsApp, History and Delete per record.
- **Add / Edit Sadhak** – registration form with automatic PRN lookup
  (local database first, then LearnGeeta remote fallback), auto-fill of BC / GC /
  CT / TA names from the selected group, Zoom link, and edit permission checks
  (Admin or the BC/GC/CT/TA of the sadhak's group).
- **Groups** – manage levels/batches, role-holder assignments (BC, GC, CT, TA),
  class timing and Zoom meeting links.
- **Sync** – pull or push the full CRM between two WordPress sites running this
  plugin over the WordPress REST API, authenticated by a shared sync token.
- **Import / Export** – CSV and Excel (.xlsx) export and import. Rows are
  upserted by phone number (existing numbers updated, new numbers added).
- **Backup** – full JSON backups of users, groups, sadhaks and history with
  create / download / restore / auto-backup rotation.
- **User Management** – CRM users with roles (Admin, BC, GC, CT, TA, Mentor) and
  an activity log of every change.
- **Settings** – app name, default country, page size, PRN remote search,
  auto-backup, WhatsApp, sync token.

## Requirements

- WordPress 5.8+
- PHP 8.0+
- MySQL 5.7+ (or MariaDB 10.2+)

## Installation

1. Upload `gpn-crm.zip` via **Plugins → Add New → Upload Plugin**, or extract it
   into `wp-content/plugins/gpn-crm/`.
2. **Activate** the plugin. Activation:
   - creates the tables `wp_gpn_users`, `wp_gpn_groups`, `wp_gpn_sadhaks`,
     `wp_gpn_history`, `wp_gpn_logs`;
   - seeds the default administrator account **`admin` / `admin123`**;
   - grants the `gpn_crm_access` / `gpn_crm_admin` capabilities to the WordPress
     `administrator` role;
   - stores default settings and a random sync token.
3. Open **Geeta CRM** in the WordPress admin sidebar and log in with
   `admin` / `admin123`.

> **Change the default password immediately** after first login
> (User Management → Edit → Administrator).

## CRM Roles

| Role | Permissions |
| --- | --- |
| **Admin** | Everything: users, groups, settings, sync, import/export, backup, delete |
| **BC / GC / CT / TA** | Add sadhaks; edit sadhaks only in groups where they are the assigned role-holder |
| **Mentor** | View access |

## PRN Lookup

Search is run automatically while typing a mobile number or email:

1. Clean the phone number (only strips the country code from explicit
   international prefixes such as `+977...` or `00977...`).
2. Search the local `wp_gpn_sadhaks` table.
3. If nothing is found locally and remote search is enabled, POST the term to
   the LearnGeeta participant search endpoint and parse the response.

PRN-by-PRN search is local-only (the remote endpoint only supports
mobile/email lookup).

## Sync

1. On the remote site enable **Settings → Enable REST API sync** and copy its
   sync token.
2. On this site open **Sync**, enter the remote URL, a remote CRM Admin
   username/password and the remote sync token, then **Pull** or **Push**.
   Pulling replaces this site's data after creating a safety backup.

REST endpoint: `POST /wp-json/gpn-crm/v1/sync`

## Backup Location

Backups are stored under `wp-content/uploads/gpn-crm/backups/`. Uninstalling the
plugin deletes this folder along with all tables and options.

## Development

- `includes/functions.php` – shared helpers, country tables, JSON responses.
- `includes/class-gpn-db.php` – schema + table helpers.
- `includes/class-gpn-auth.php` – CRM login, transient-keyed httponly sessions.
- `includes/class-gpn-sadhak.php` – sadhak CRUD, history, stats, permissions.
- `includes/class-gpn-prn.php` – PRN local/remote lookup.
- `includes/class-gpn-group.php` – group CRUD and role-holder resolution.
- `includes/class-gpn-backup.php` – JSON backup/restore.
- `includes/class-gpn-import-export.php` – CSV/XLSX import/export.
- `includes/class-gpn-sync.php`, `api/class-gpn-rest.php` – REST sync.
- `includes/class-gpn-ajax.php` – all AJAX endpoints.
- `admin/*.php`, `templates/*.php` – pages and shared templates.
- `assets/js/admin.js`, `assets/css/admin.css` – the dark-theme interface.

## Changelog

### 1.0.0
- Initial release – 1:1 migration of the desktop CRM into WordPress.
