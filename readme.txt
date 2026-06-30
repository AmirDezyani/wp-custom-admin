=== WP Custom Admin ===
Contributors: amirdezyani
Tags: admin, white-label, branding, admin-theme, login
Requires at least: 6.5
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reskin wp-admin into a bespoke, branded control panel: colors, logo, login, menu, and white-label. Persian + RTL ready. Reusable across sites.

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

= 0.8.2 =
* Persian (fa_IR) translation completed: the branded Home dashboard, admin menu
  labels, activity statuses, and the newer settings (color scheme, dashboard
  options, network defaults) are now fully translated — nothing falls back to
  English on Persian sites. The .pot template now lists the full string set.
* Settings-page media-picker labels localize through PHP, so they translate
  without a build-step script-translation JSON.

= 0.8.1 =
* Dark mode: WordPress-core admin text that ships with a fixed light-mode color
  (section and meta-box headings, form labels) now follows the theme tokens, and
  secondary core tables flip surface and text together — so nothing renders
  dark-on-dark or light-on-light when the dark scheme is active.

= 0.8.0 =
* Sidebar redesigned to a branded navigation rail: rounded "pill" menu items, a
  solid brand-colored pill for the current screen (replacing the WordPress accent
  bar), and a header with the client logo — or a monogram tile plus product name.
* The admin-menu logo now renders in that header (with a folded/collapsed state)
  instead of as a background image.
* The redundant stock "Dashboard" menu item is removed when the branded Home is the
  landing page, so the rail shows a single "Home" at the top. Core updates remain
  reachable via the toolbar and update notices.

= 0.7.0 =
* Cohesion across the admin: tokenized admin notices, settings form tables, the
  page-title bar, list-table headers, and a refined sidebar active state — so
  every screen matches the dashboard, in light and dark.
* Settings page restyled with the shared tokens (now dark-mode aware).
* Dashboard header gains an optional 14-day activity trend chart.

= 0.6.0 =
* Premium redesign: a tokenized design system (one neutral ramp, 8pt spacing,
  radius ladder, layered shadows, motion + focus tokens) across the reskin.
* Home dashboard upgraded to KPI cards with trend deltas and server-rendered
  inline-SVG sparklines (cached, no JS), an activity feed, a refined hero, and
  stroke icons.
* Self-hosted Inter (UI) and Vazirmatn (Persian) fonts — no CDN.
* Dark mode (auto / light / dark) via Settings → Branding.
* Accessibility: focus-visible rings, prefers-reduced-motion, higher contrast.

= 0.5.0 =
* New: a custom branded "Home" dashboard (hero, stat cards, quick actions, recent
  posts) that replaces the default WordPress dashboard as the landing page.
* Lower PHP requirement to 7.4 for much broader host compatibility.

= 0.4.2 =
* Fix: primary buttons and accent elements now use the brand color on WordPress 7.0
  (override --wp-admin-theme-color; corrected selectors that assumed .wp-core-ui was
  a wrapper rather than a body class).
* Fix: the "Howdy," greeting is now reliably removed via a scoped gettext filter.
* Verified live on WordPress 7.0 via WordPress Playground.

= 0.4.1 =
* CI: GitHub Actions runs PHP lint (8.0–8.3) and WordPress-Extra coding standards on every push.
* Code quality: passes phpcs clean; dropped the deprecated wp_targeted_link_rel() call.

= 0.4.0 =
* Multisite: a Network Admin page for network-wide brand defaults (identity + palette); per-site settings still override them.
* Settings page: the admin logo now previews live in the page header when selected.

= 0.3.0 =
* Full right-to-left (RTL) support for the admin, login, and settings screens.
* Persian (fa_IR) translation bundled; loads automatically on Persian sites.
* Persian/Arabic-safe font fallbacks (Vazirmatn, Tahoma).

= 0.2.0 =
* Live color preview on the settings page (the admin recolors as you pick).
* Drag-and-drop reordering of admin menu items.
* Release tooling: .gitattributes export-ignore for clean `git archive` builds.

= 0.1.0 =
* Initial release.
