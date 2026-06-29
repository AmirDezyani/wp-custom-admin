=== WP Custom Admin ===
Contributors: amirdezyani
Tags: admin, white-label, branding, admin-theme, login
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reskin wp-admin into a bespoke, branded control panel. Configurable logo, colors, login page, admin menu, and white-labeling — reusable across many sites.

== Description ==

WP Custom Admin turns the WordPress admin into a product-grade, branded control panel that does not feel
like stock WordPress, and re-brands per site with data only — no code edits.

* Full admin reskin driven by CSS custom properties.
* Brand control: site logo (Media Library) and color palette, applied to admin and login.
* Login page branding (logo, link, palette).
* Admin menu control: rename, hide, reorder per role, with a protected full-access role.
* White-label: remove the WordPress logo, "Howdy", footer credit, version, update nags, and welcome panel
  (update-related items stay visible to capable administrators).
* Reusable: export/import brand.json, or hard-lock branding per project via the WPCA_CONFIG constant.

Zero build step: copy into wp-content/plugins and activate.

== Installation ==

1. Upload the `wp-custom-admin` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Open the WP Custom Admin menu to set the logo, colors, and toggles.

== Frequently Asked Questions ==

= Does deactivating remove my branding? =
No. Deactivation is non-destructive. Data is only removed on uninstall, and only if you opt in via the
"Remove all data on uninstall" setting.

= Is it multisite friendly? =
Yes. Network admin is left untouched by default; a network-level default can be set with per-site overrides.

== Changelog ==

= 0.2.0 =
* Live color preview on the settings page (the admin recolors as you pick).
* Drag-and-drop reordering of admin menu items.
* Release tooling: .gitattributes export-ignore for clean `git archive` builds.

= 0.1.0 =
* Initial release.
