# CLAUDE.md — WP Custom Admin

Project rules for **WP Custom Admin**: a single, reusable WordPress plugin that fully reskins
`wp-admin` into a bespoke, branded control panel and is configurable per client site
(logo, color palette, login page, admin menu, white-labeling). Built once, dropped into
many client projects. **These rules override default behavior — follow them exactly.**

---

## 1. Product goals (the "why")

- **Doesn't feel like WordPress.** A consistent, product-grade admin look for every user — not a
  per-user color picker.
- **Reusable across many sites.** Re-brand a site with data only (saved options or an imported
  `brand.json`), never by editing code.
- **Zero-build deployment.** The plugin runs by copying the folder into `wp-content/plugins/`.
  No `npm`/webpack/Composer step is required on a client site. Pure PHP + CSS + vanilla JS.
- **Safe & non-destructive.** Toggling/deactivating never wipes a client's branding. Data is only
  removed on uninstall, and only behind an explicit opt-in.

---

## 2. Naming & identity (NEVER deviate — these are collision boundaries)

| Thing | Value |
|---|---|
| Plugin slug / folder / text domain | `wp-custom-admin` |
| PHP namespace root | `WPCustomAdmin\` |
| Function / hook / option prefix | `wpca` / `wpca_` |
| Single option key | `wpca_settings` |
| Network option key (multisite) | `wpca_network_settings` |
| DB/settings version option | `wpca_db_version` |
| Constants | `WPCA_VERSION`, `WPCA_FILE`, `WPCA_PATH`, `WPCA_URL`, `WPCA_BASENAME` |
| Defaults filter | `wpca_default_settings` |
| Hard-lock config constant | `WPCA_CONFIG` (array, defined in `wp-config.php`/mu-plugin) |
| CSS custom-property prefix | `--wpca-` |
| Admin body-class marker | `wpca-admin` |

Every hook callback, option key, and CSS variable carries the `wpca` / `--wpca-` prefix.
A new top-level menu slug is `wpca-settings`.

---

## 3. Architecture

```
wp-custom-admin/
├─ wp-custom-admin.php        # Header + bootstrap ONLY (constants, autoloader, lifecycle, plugins_loaded init)
├─ uninstall.php              # Guarded destructive cleanup (opt-in)
├─ readme.txt / README.md     # WP readme + GitHub readme
├─ src/
│  ├─ autoload.php            # ~15-line spl_autoload_register (PSR-4: WPCustomAdmin\ -> src/)
│  ├─ Plugin.php              # THE single singleton / minimal container; builds + boots modules
│  ├─ Activation.php          # activate() / deactivate() (deactivate is non-destructive)
│  ├─ Contracts/Module.php    # interface: id(), is_enabled(array $s), register()
│  ├─ Support/
│  │  ├─ Settings.php         # read/merge-over-defaults/defaults()/get(); config precedence
│  │  ├─ Sanitizer.php        # the ONE sanitize_callback; typed per-field sanitizers
│  │  └─ Assets.php           # admin + login enqueue; emits :root{--wpca-*} via wp_add_inline_style
│  ├─ Admin/
│  │  ├─ SettingsPage.php     # top-level menu, tabs, Settings API wiring, import/export
│  │  └─ views/*.php          # presentation templates (escape at echo site)
│  └─ Modules/
│     ├─ Branding/BrandingModule.php
│     ├─ Login/LoginModule.php
│     ├─ Menu/MenuModule.php
│     └─ WhiteLabel/WhiteLabelModule.php
├─ assets/css/{admin,login,settings}.css   # author once against var(--wpca-*)
├─ assets/js/{settings,admin}.js            # vanilla (+ core wp-color-picker / wp.media on settings only)
├─ assets/img/menu-icon.svg
└─ languages/wp-custom-admin.pot
```

**Rules:**
- The root file is bootstrap only: header, `ABSPATH` guard, constants, `require src/autoload.php`,
  `register_activation_hook`/`register_deactivation_hook`, and one
  `add_action('plugins_loaded', fn() => Plugin::instance()->boot())`. No feature logic.
- **One singleton** (`Plugin::instance()`) acting as a tiny container. It builds `Settings` + `Assets`
  once and injects them into modules. Do **not** scatter `::instance()` singletons across classes.
- Each feature is a **Module** implementing `Contracts\Module`. `Plugin::boot()` iterates modules and
  calls `register()` **only on enabled ones**. Modules read enable-flags from `Settings`.
- **Never register hooks in a constructor.** Hooks go inside `register()`, which runs only when enabled.
- Autoloading is a hand-written `spl_autoload_register`. **Do not** bundle Composer's `vendor/autoload.php`
  into the shipped plugin. Composer/phpcs are dev-only tools.

---

## 4. Coding standards (clean code, WP-compatible)

- `declare(strict_types=1);` at the top of **every** `src/` PHP file; namespaced `PascalCase` classes;
  typed properties, parameters, and return types everywhere.
- Follow **WordPress Coding Standards** on the WP-facing surface: **tabs** for indentation,
  **Yoda conditions** in comparisons, `snake_case` for option keys / hook names. Method names are
  lowercase `snake_case` (e.g. `add_brand_styles()`) so they read naturally as WP callbacks.
- `final` classes by default; small, single-responsibility methods; no God-classes — one feature per module.
- No magic strings: option keys, hook names, and defaults come from constants or `Settings`.
- Comment the **why**, not the **what**. Document load-bearing hook priorities inline (they are fragile).
- Validate with `composer phpcs` (WordPress ruleset, `phpcs.xml.dist`) — must pass clean before "done".

---

## 5. Data model — ONE option array

- All config lives in a single autoloaded option, `get_option('wpca_settings')`, registered **once** via
  `register_setting()` with a single `sanitize_callback` (`Sanitizer::sanitize`).
- Read through `Settings::all()` / `Settings::get($key, $default)`, which merges stored values over
  `Settings::defaults()` with `wp_parse_args()` **at read time** (so a fresh install is already branded
  and new keys self-heal across versions).
- **Config resolution precedence** (lowest → highest):
  1. hardcoded `Settings::defaults()`
  2. `apply_filters('wpca_default_settings', $defaults)`
  3. `WPCA_CONFIG` constant (optional per-project hard lock)
  4. network option (multisite)
  5. per-site DB option
- `show_in_rest => false` for the option (admin-only; don't widen surface area).
- **Export/Import** of `brand.json` is a first-class feature and the cross-site reuse mechanism.
  Import re-runs the **same** `sanitize_callback` before `update_option` — never trust imported JSON.

---

## 6. Security — MANDATORY checklist (every PR)

Nonces authenticate intent; capabilities authorize the user — **you need both, every time.**

- [ ] `if ( ! defined( 'ABSPATH' ) ) { exit; }` at the top of **every** PHP file (incl. `uninstall.php`, views).
- [ ] `current_user_can('manage_options')` checked **server-side** in every settings page render AND every
      save / AJAX / `admin-post` / REST handler. Menu capability ≠ access control (URLs are still reachable).
- [ ] Nonce verified on every write: `settings_fields()` for the options.php form; `check_admin_referer()`
      for custom handlers; `check_ajax_referer()` for AJAX; `permission_callback` for REST.
- [ ] `wp_unslash()` then sanitize every `$_POST`/`$_GET`; access keys with `isset()`/`??` (PHP 8 safe).
- [ ] Type-specific sanitization on **input**: `sanitize_hex_color()` (colors), `absint()` (attachment IDs),
      `esc_url_raw()` (stored URLs), `sanitize_text_field()` (labels), `sanitize_key()` (slugs).
      `sanitize_hex_color()` ships with the Customizer — guard with `function_exists()` + a strict hex
      regex fallback, or it fatals on plain admin/AJAX requests.
- [ ] Escape on **output** by context: `esc_attr()`, `esc_html()`, `esc_url()`, `esc_attr_e()`/`esc_html_e()`.
      Re-escape even DB-trusted data (another code path may have tampered with the row).
- [ ] **Logo = Media Library attachment ID only** (`absint`), selected via `wp_enqueue_media()`. Never accept a
      raw file upload or free-text URL. Render with `wp_get_attachment_image()` / `wp_get_attachment_image_url()`.
- [ ] Colors → inline CSS: re-sanitize with `sanitize_hex_color()` and wrap in `esc_attr()` before composing the
      `:root{}` block. The inline-style injection point is the ONLY CSS sink — keep it singular and audited.
- [ ] Limited HTML (e.g. login footer) → `wp_kses()` with an explicit allowlist, never `esc_html()` (destroys it)
      or raw output (stored XSS).

Run **Plugin Check (PCP)** + `phpcs` (WordPress-Extra) before shipping.

---

## 7. Theming (the reskin)

- Author the entire skin **once** in `assets/css/admin.css` (and `login.css`) using `var(--wpca-*)` tokens.
  Only the small `:root{ --wpca-primary: …; }` block is generated per client.
- Enqueue admin assets on `admin_enqueue_scripts` (never `wp_enqueue_scripts`). Enqueue login assets on
  `login_enqueue_scripts`. Inject the dynamic token block with `wp_add_inline_style()` **attached to the
  enqueued handle** (the handle MUST be registered/enqueued first or the inline CSS is silently dropped).
- Force the skin for **all** admin users (scoped under `body.wpca-admin`, added via the `admin_body_class`
  filter — which takes/returns a **string**). Do not rely on WP's per-user `wp_admin_css_color` picker.
- Target **stable** core selectors only: `#wpadminbar`, `#adminmenu` / `#adminmenuwrap` / `#adminmenuback`,
  `#wpcontent`, `#wpfooter`, `.wp-core-ui .button-primary` (+ `:hover`/`:focus`), `#adminmenu .wp-submenu`.
  Treat this set as the version-resilience contract; review it each WP major release.
- Win by **load order + body-class scope**, not an `!important` war. Self-host fonts (no Google Fonts/`@import`);
  expose family as `--wpca-font`. Version every asset with `filemtime()` (dev) / `WPCA_VERSION` (prod) for cache-busting.
- Login page: override `#login h1 a` with the client logo; set `login_headerurl` (home) + `login_headertext`
  (site name). Reuse the same `--wpca-*` token vocabulary as admin.

---

## 8. White-label & menu control

- All hooks here are **load-bearing on priority** — document priorities in comments:
  - `admin_bar_menu` @ **999**: `remove_node('wp-logo')`; rewrite the `my-account` node to drop "Howdy, "
    (do NOT use a global `gettext` filter — it taxes every string on every request).
  - `admin_menu` @ **999**: `remove_menu_page()` / `remove_submenu_page()`, renames (mutate `$menu`/`$submenu`,
    match by **slug** `$menu[$i][2]`, never index), and visibility — all gated by `current_user_can()`.
  - Reorder: `custom_menu_order` must return `true` **and** `menu_order` returns the slug array (required pair).
  - `admin_footer_text` (left) + `update_footer` @ **11** (right/version).
  - Dashboard: `remove_meta_box()` on `wp_dashboard_setup`; `remove_action('welcome_panel','wp_welcome_panel')`.
  - Update nag: `remove_action('admin_notices','update_nag',3)` — **gate behind `update_core` capability** so real
    admins still see security updates.
- **Menu hiding is cosmetic, not security** — a hidden page is still reachable by URL. Always keep a configurable
  "full access" role (default `manage_options`) exempt from every removal/nag rule, so a site never becomes
  unmaintainable. For true restriction, pair with capabilities.
- Make rules **data-driven**: store `{ role/capability => removals/renames/order }` in `wpca_settings` and apply
  generically in one callback. No per-client code forks.
- **Multisite:** leave network admin untouched by default; guard with `is_multisite()` / `is_network_admin()`.

---

## 9. Settings UI

- Own **top-level** menu (`add_menu_page`, custom SVG icon) named from the configurable product name — reinforces
  "bespoke product". Tabs: Branding · Colors · Login · Menu · White-label · Tools (import/export).
- Use the **Settings API** for plumbing (`register_setting` + `add_settings_section`/`add_settings_field` +
  `settings_fields`/`do_settings_sections`, form posts to `options.php`) so core handles nonce, the option
  whitelist, and `settings_errors`. Bind every field into the one option via `name="wpca_settings[key]"`.
- Enqueue `wp-color-picker` (Iris, ships with core) + `wp_enqueue_media()` **only** on the settings page hook
  (`toplevel_page_wpca-settings`). Export via `admin-post.php` download; import re-validates through the sanitizer.

---

## 10. Lifecycle & i18n

- **Activation:** set default options if absent, set `wpca_db_version`. **Deactivation:** clear only
  cron/transients — **never delete branding data.**
- **uninstall.php:** `if ( ! defined('WP_UNINSTALL_PLUGIN') ) exit;` then delete only if
  `$opts['delete_on_uninstall']` is true. On multisite, loop `get_sites()`/`switch_to_blog()` and
  `delete_site_option()` for the network. Prefer `uninstall.php` over `register_uninstall_hook()`.
- **i18n:** literal text domain `'wp-custom-admin'` in every `__()`/`esc_html_e()`; `load_plugin_textdomain()`
  on `init`; bundle `/languages`. Header: `Requires at least: 6.5`, `Requires PHP: 8.0`, `Update URI: false`
  (no self-hosted updater → avoids wp.org slug-collision auto-updates). Ship Persian (`fa_IR`) as a
  first-class locale (`.l10n.php` + `.po`).
- **RTL:** every directional CSS rule the plugin adds must have an RTL counterpart. Prefer logical
  properties (`margin-inline-*`, `text-align: start`); otherwise scope overrides under `body.rtl`
  (admin/settings) or `body.rtl.login`. WP core flips its own admin chrome on RTL locales — only the
  plugin's own rules need handling. Include Persian/Arabic-safe font fallbacks in the `--wpca-font` stacks.

---

## 11. Definition of done (quality gates)

A change is done only when:
1. `composer phpcs` passes clean against the WordPress ruleset.
2. The security checklist (§6) is satisfied for every new I/O boundary.
3. Strict types + full typing present; no new God-class; feature lives in a toggleable module.
4. All theming flows through `--wpca-*` tokens (no hardcoded client colors in CSS).
5. New user-facing strings are translatable with the correct text domain.
6. Defaults updated so a fresh install renders correctly with zero configuration.

> Dev-only files (`composer.json`, `phpcs.xml.dist`, `node_modules/`, `vendor/`) must be excluded from the
> deployed/zipped artifact via `.distignore`. The copied plugin folder is pure PHP/CSS/JS.
