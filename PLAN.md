# Build Plan — WP Custom Admin

A reusable, brandable WordPress admin reskin plugin. Zero-build, OOP, secure.
See `CLAUDE.md` for the binding standards. This file is the roadmap.

## Architecture in one line
One `Plugin` singleton boots a set of toggleable **Modules** (Branding, Login, Menu, White-label),
all driven by a single sanitized option array (`wpca_settings`) merged over code defaults, and a
Settings page that edits that array. Theming is pure CSS custom properties (`--wpca-*`) injected from PHP.

---

## Phase 0 — Repo & tooling scaffold
- `.gitignore`, `.editorconfig`, `.distignore`, `composer.json` (dev-only phpcs), `phpcs.xml.dist` (WPCS).
- `README.md` (dev) + `readme.txt` (WP). Initial commit.

## Phase 1 — Core foundation (the spine)
- `wp-custom-admin.php` — header + bootstrap.
- `src/autoload.php` — PSR-4 `spl_autoload_register`.
- `src/Plugin.php` — singleton/container; builds `Settings` + `Assets`; registers + boots modules.
- `src/Contracts/Module.php` — `id()`, `is_enabled()`, `register()`.
- `src/Activation.php` — activate/deactivate (non-destructive).
- `uninstall.php` — guarded, opt-in cleanup (single + multisite).

## Phase 2 — Settings + sanitization + defaults
- `src/Support/Settings.php` — `defaults()`, `all()`, `get()`, config-precedence resolution.
- `src/Support/Sanitizer.php` — the single `sanitize_callback`; typed per-field sanitizers + hex guard.

## Phase 3 — Asset pipeline + base reskin
- `src/Support/Assets.php` — admin + login enqueue; `:root{--wpca-*}` via `wp_add_inline_style`; `admin_body_class`.
- `assets/css/admin.css` — the reskin authored against `var(--wpca-*)` (adminbar, menu, content, buttons, tables, forms).
- `assets/css/login.css`, `assets/img/menu-icon.svg`, self-hosted font hook.

## Phase 4 — Feature modules
- `Modules/Branding/BrandingModule.php` — logo in admin bar/menu, palette tokens, dashboard cleanup toggle.
- `Modules/Login/LoginModule.php` — login logo/url/text + login CSS tokens.
- `Modules/Menu/MenuModule.php` — data-driven remove/rename/reorder, per-role, priority 999, full-access exemption.
- `Modules/WhiteLabel/WhiteLabelModule.php` — wp-logo, "Howdy", footer, version, update nag (cap-gated), welcome panel.

## Phase 5 — Settings page UI
- `src/Admin/SettingsPage.php` — top-level menu, tabbed Settings API, scoped color-picker + media enqueue, import/export via `admin-post.php`.
- `src/Admin/views/*.php` — Branding · Colors · Login · Menu · White-label · Tools tabs.
- `assets/js/settings.js` — color picker init, media frame, tab switching (vanilla).
- `assets/css/settings.css` — settings page styling.

## Phase 6 — Hardening & docs
- Self-review against §6 security checklist; run `phpcs`; confirm zero-config default render.
- `languages/wp-custom-admin.pot`; finalize `README.md`/`readme.txt`. Tag `v0.1.0`.

---

## Testing note
No local PHP/WP here. Testing happens on a real WP site (or `wp-env`/Local). The plugin is built to be
copied into `wp-content/plugins/wp-custom-admin/` and activated. `phpcs` is the static gate we run locally.
