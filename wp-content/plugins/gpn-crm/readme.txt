=== Geeta Pariwar Nepal CRM ===
Contributors: geetapariwarnepal
Tags: crm, sadhak, devotee, management, geeta, pariwar
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete Sadhak (devotee) management CRM for Geeta Pariwar Nepal – a 1:1 migration of the desktop application into WordPress.

== Description ==

Manage sadhaks, groups, PRN auto-search, history, REST sync, import/export, JSON backup, roles and settings – all in a dark theme with a blue header, fully AJAX-powered and responsive.

= Key features =

* Dashboard with level-wise and batch-wise statistics.
* Sadhak grid: search, sort, paginate, WhatsApp, history, delete.
* Registration form with automatic PRN lookup (local then LearnGeeta remote fallback).
* Groups / levels with BC, GC, CT, TA role-holder assignment and Zoom links.
* Pull/push sync between two WordPress sites over the REST API.
* CSV and Excel (.xlsx) import/export, upserted by phone.
* JSON backups with create/download/restore and auto-backup rotation.
* CRM users with roles (Admin, BC, GC, CT, TA, Mentor) and an activity log.

= Note = The plugin keeps its own user table (identical to the desktop app).
The default administrator account is admin / admin123 – change it right after
first login.

== Installation ==

1. Upload gpn-crm.zip via Plugins → Add New → Upload Plugin (or extract to wp-content/plugins/gpn-crm).
2. Activate the plugin. This creates the wp_gpn_* tables, seeds the default admin account, grants the gpn_crm_access / gpn_crm_admin capabilities to the administrator role and stores default settings.
3. Open Geeta CRM in the WordPress admin menu and log in.

== Frequently Asked Questions ==

= Where is the data stored? =
In the standard MySQL tables wp_gpn_users, wp_gpn_groups, wp_gpn_sadhaks, wp_gpn_history and wp_gpn_logs. No custom post types are used.

= How does the PRN lookup work? =
Typing a mobile number or email searches the local database first, then falls back to the LearnGeeta participant search endpoint when enabled in Settings.

= Can another site sync with mine? =
Yes. Enable REST API sync in Settings and share the sync token. Other sites running this plugin can then pull/push over /wp-json/gpn-crm/v1/sync.

== Changelog ==

= 1.0.0 =
* Initial release – 1:1 migration of the desktop CRM into WordPress.
