=== WP Custom Admin ===
Contributors: amirdezyani
Tags: admin, white-label, branding, admin-theme, login
Requires at least: 6.5
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.8.0
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

= 0.8.0 =
* Sidebar redesign — the admin menu now reads as a custom dashboard nav rather
  than recolored WordPress: inset rounded "pill" rows, a soft brand-tinted active
  pill (no left bar), a subtle hover wash, muted-then-brand icons, hairline
  section dividers, a brand logo header zone, a pinned-submenu guide rail,
  elevated rounded fly-out cards, modern count badges (brand for updates, danger
  for moderation), a themed thin scrollbar, and a real keyboard focus ring.
* Top bar redesign — the admin toolbar becomes a thin app header: a single bottom
  hairline, soft rounded hover targets on both clusters, the account node as a
  rounded avatar + name chip, count bubbles restyled as the same pill badges as
  the sidebar, and dropdown menus as elevated rounded cards with rounded rows.
* Page header — a new, toggleable module adds a breadcrumb trail ("Section ›
  Page") above each screen's title and binds it with the H1 and primary action
  into one header band closed by a hairline. Read-only and non-destructive (core's
  H1 is untouched); skips the block editor, network admin, and the plugin's own
  Home/Settings screens. Toggle under Settings → Branding.
* Command palette — a new, toggleable module adds a Ctrl/Cmd+K quick switcher that
  fuzzy-searches every admin page the current user can reach and jumps to it.
  Vanilla JS (no build), accessible (combobox/listbox, full keyboard model, focus
  trap), and read-only (links only — no writes). A discoverable button is added to
  the toolbar. Toggle under Settings → Branding.
* List tables — every core list table (posts, pages, comments, users, plugins)
  becomes a modern data table: a themed uppercase header, hairline rows with a soft
  hover wash, status pill chips (published / draft / pending / scheduled / trash
  mapped to the semantic colors), hover-revealed row actions, brand-tinted active
  sort column / current filter / current page, and tabular numerals. Recolor-only —
  core's responsive stacked view, Quick Edit, and column sorting are untouched.
* Persian/RTL first-class: the page-header and command-palette strings are shipped
  translated to fa_IR, the palette folds Persian/Arabic letter and digit variants
  for reliable search, the shortcut is matched by physical key so it works on
  Persian keyboard layouts, and every directional rule mirrors for RTL.
* Folded (icon-only), RTL/Persian, dark mode, and the <=782px mobile bar are all
  handled; menu width and bar height are left untouched, so the chrome changes
  touch color/spacing only and remain version-resilient.

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
