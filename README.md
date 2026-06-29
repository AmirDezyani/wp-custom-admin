# WP Custom Admin

[![CI](https://github.com/AmirDezyani/wp-custom-admin/actions/workflows/ci.yml/badge.svg)](https://github.com/AmirDezyani/wp-custom-admin/actions/workflows/ci.yml)

A reusable, **brandable** WordPress plugin that reskins `wp-admin` into a bespoke control panel that
doesn't feel like WordPress — and re-brands per client site with data only (logo, colors, login page,
admin menu, white-labeling). Built once, dropped into many projects.

> **Zero-build:** runs by copying the folder into `wp-content/plugins/`. No `npm`/webpack/Composer step
> required on a client site. Composer/PHPCS here are dev-only tooling.

## Highlights

- 🎨 **Full reskin** driven entirely by CSS custom properties (`--wpca-*`) — recolor with saved options, no file edits.
- 🖼️ **Brand control:** site logo (Media Library) + color palette, applied live to admin and login.
- 🔐 **Login page** matches the brand (logo, link, palette).
- 📋 **Admin menu control:** rename / hide / reorder per role, with a protected "full access" role.
- 🏷️ **White-label:** remove the WP logo, "Howdy", footer credit, version, update nags, welcome panel (cap-gated).
- 🔁 **Reusable:** export/import `brand.json` to clone branding across sites; or hard-lock per project via `WPCA_CONFIG`.
- 🧱 **Clean architecture:** namespaced OOP, one toggleable module per feature, single sanitized option, strict types.
- 🌐 **RTL + Persian ready:** full right-to-left layout and a bundled Persian (`fa_IR`) translation that loads automatically on Persian sites.

## Requirements

- WordPress **6.5+** (the bundled Persian translation uses the PHP `.l10n.php` format)
- PHP **8.0+**

## Localization & RTL

Set the site language to **فارسی** (Settings → General → Site Language = `fa_IR`). WordPress then loads
the bundled Persian translation automatically and flips the admin to right-to-left; the plugin's own
admin, login, and settings styles include matching RTL rules. Other languages: drop a
`wp-custom-admin-<locale>.l10n.php` (or `.mo`) into `languages/`.

## Install (client site)

1. Copy this folder to `wp-content/plugins/wp-custom-admin/` (or clone the repo there).
2. Activate **WP Custom Admin** in Plugins.
3. Open the top-level **WP Custom Admin** menu and set the logo, colors, and toggles.

It renders a sensible default skin immediately — configuration is optional.

## Reuse across projects

- **UI way:** configure one site → *Tools* tab → **Export** `brand.json` → **Import** on the next site.
- **Code way:** define a hard lock in `wp-config.php` or an mu-plugin:
  ```php
  define( 'WPCA_CONFIG', array(
      'primary_color' => '#0d9488',
      'product_name'  => 'Acme Studio',
  ) );
  ```
  Resolution precedence: defaults → `wpca_default_settings` filter → `WPCA_CONFIG` → network option → per-site option.

## Development

```bash
composer install      # installs PHPCS + WordPress Coding Standards (dev only)
composer phpcs        # lint against the WordPress ruleset
composer phpcbf        # auto-fix what can be fixed
```

See [`CLAUDE.md`](CLAUDE.md) for the binding engineering standards and [`PLAN.md`](PLAN.md) for the roadmap.

## Build a release zip

Dev/build files are marked `export-ignore` in `.gitattributes`, so `git archive` emits a clean,
deploy-ready folder (pure PHP/CSS/JS):

```bash
git archive --format=zip --prefix=wp-custom-admin/ -o wp-custom-admin.zip HEAD
```

Unzip into `wp-content/plugins/` (or upload the zip via Plugins → Add New → Upload).

## License

GPL-2.0-or-later.
