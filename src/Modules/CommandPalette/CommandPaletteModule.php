<?php
/**
 * Command-palette module: a keyboard-driven quick switcher (Ctrl/Cmd K) that
 * jumps to any admin page the current user can already reach.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Modules\CommandPalette;

use WPCustomAdmin\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues a self-contained vanilla-JS command palette on every admin screen and
 * hands it a server-built list of navigable destinations.
 *
 * This module is READ-ONLY navigation: every item is a link the user activates with
 * a plain GET (location.assign), so it performs NO writes and therefore needs NO
 * nonce. Authorization is handled entirely by core — the $menu/$submenu globals it
 * reads have ALREADY been capability-filtered by the admin-menu pipeline (each item
 * stores its required cap and core drops the ones the user lacks before they render),
 * and every destination URL re-runs WordPress' own admin-page capability check on
 * arrival. The palette is convenience chrome, never an access-control boundary
 * (CLAUDE.md §8: a quick-access list is still just URL-reachable links).
 *
 * It depends on the branded substrate: its entire skin (and the `body.wpca-admin`
 * scope + the `--wpca-*` token set it consumes) is emitted only by BrandingModule.
 * So it is gated on BOTH its own flag AND branding — exactly like PageHeaderModule —
 * because with branding off the overlay would render unstyled.
 */
final class CommandPaletteModule extends AbstractModule {

	public function id(): string {
		return 'command_palette';
	}

	/**
	 * Requires BOTH its own flag AND branding: the palette overlay is skinned entirely
	 * from admin.css under `body.wpca-admin`, and both the stylesheet and that body
	 * class are emitted only by BrandingModule. With branding off the JS would open an
	 * unstyled overlay against undefined tokens, so the palette is tied to the branded
	 * substrate it depends on.
	 *
	 * @param array<string,mixed> $settings Resolved settings.
	 */
	public function is_enabled( array $settings ): bool {
		return ! empty( $settings['palette_enabled'] )
			&& ! empty( $settings['branding_enabled'] );
	}

	public function register(): void {
		// Global chrome: unlike Dashboard/Settings (which gate on a single PAGE_HOOK),
		// the palette is available on every admin screen, so it enqueues unconditionally.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Register the footer script and feed it the items + pre-translated strings.
	 *
	 * Why localize instead of wp.i18n: there is no wp-cli/build step on client sites
	 * (CLAUDE.md zero-build), so the per-locale JS .json that wp_set_script_translations
	 * needs can never be generated. Instead every string is translated here in PHP with
	 * __() (resolved by the .l10n.php that load_plugin_textdomain already loaded) and
	 * shipped pre-translated in the payload — the JS never calls wp.i18n at all.
	 *
	 * @param string $hook_suffix Current admin page hook (unused; palette is global).
	 */
	public function enqueue( string $hook_suffix ): void {
		unset( $hook_suffix );

		// Network admin is left untouched (CLAUDE.md §8/§10): its menu is a different,
		// site-management surface. (No wp_doing_ajax() guard: admin_enqueue_scripts
		// does not fire on admin-ajax.php, so an async context never reaches here.)
		if ( is_network_admin() ) {
			return;
		}

		// Distinct handle: the STYLE handle 'wpca-admin' (registered in Assets) lives in
		// a separate registry, but a unique script handle keeps any future
		// wp_add_inline_script/localize unambiguous to a reader.
		wp_enqueue_script(
			'wpca-command-palette',
			WPCA_URL . 'assets/js/admin.js',
			array(),
			WPCA_VERSION,
			true // In the footer: $menu/$submenu are fully built and the DOM exists.
		);

		// wp_localize_script wp_json_encode()s the whole array, so labels are safely
		// JS-/JSON-escaped inside the inline <script> printed before admin.js.
		wp_localize_script(
			'wpca-command-palette',
			'wpcaPalette',
			array(
				'items'   => $this->build_items(),
				'i18n'    => array(
					'dialogLabel'  => __( 'Command palette', 'wp-custom-admin' ),
					'placeholder'  => __( 'Search admin pages…', 'wp-custom-admin' ),
					'searchLabel'  => __( 'Search', 'wp-custom-admin' ),
					'resultsLabel' => __( 'Results', 'wp-custom-admin' ),
					'noResults'    => __( 'No results found', 'wp-custom-admin' ),
					'openLabel'    => __( 'Open command palette', 'wp-custom-admin' ),
					/* translators: %s: number of matching results. */
					'resultsCount' => __( '%s results available', 'wp-custom-admin' ),
					'escHint'      => __( 'Esc', 'wp-custom-admin' ),
				),
				// Display-only key hint; the platform-correct glyph is chosen in JS and
				// shown both in the trigger button and the input chip.
				'trigger' => array(
					'mac'   => '⌘K',
					'other' => 'Ctrl K',
				),
			)
		);
	}

	/**
	 * Build the navigable destination list from the live admin menu.
	 *
	 * Runs on admin_enqueue_scripts, after admin_menu (priority 10) has populated and
	 * capability-filtered $menu/$submenu, so iterating the globals exposes nothing the
	 * user cannot already see in the sidebar — no per-item current_user_can() needed.
	 *
	 * Each label is wp_strip_all_tags()'d (removes count bubbles / any markup → plain
	 * text consumed via textContent in JS) and each URL is run through esc_url_raw()
	 * (neutralizes javascript:/data:, attribute breakout, CRLF) before it crosses into
	 * the payload — the only attribute JS assigns from item data is the resolved href.
	 *
	 * @return array<int,array{label:string,url:string,parent:string}>
	 */
	private function build_items(): array {
		global $menu, $submenu;

		$items = array();

		if ( ! is_array( $menu ) ) {
			return $items;
		}

		foreach ( $menu as $item ) {
			if ( ! isset( $item[0], $item[2] ) ) {
				continue;
			}

			$slug  = (string) $item[2];
			$label = trim( wp_strip_all_tags( (string) $item[0] ) );

			// Skip separators and label-less rows.
			if ( '' === $label || 0 === strpos( $slug, 'separator' ) ) {
				continue;
			}

			$items[] = array(
				'label'  => $label,
				'url'    => esc_url_raw( $this->slug_to_url( $slug ) ),
				'parent' => '',
			);

			if ( ! isset( $submenu[ $slug ] ) || ! is_array( $submenu[ $slug ] ) ) {
				continue;
			}

			foreach ( $submenu[ $slug ] as $sub ) {
				if ( ! isset( $sub[0], $sub[2] ) ) {
					continue;
				}

				$child_label = trim( wp_strip_all_tags( (string) $sub[0] ) );

				if ( '' === $child_label ) {
					continue;
				}

				$items[] = array(
					'label'  => $child_label,
					'url'    => esc_url_raw( $this->slug_to_url( (string) $sub[2] ) ),
					// Parent label gives the muted breadcrumb suffix (e.g. "Settings").
					'parent' => $label,
				);
			}
		}

		return $items;
	}

	/**
	 * Resolve a menu/submenu slug to a working admin URL.
	 *
	 * A slug is either an admin file fragment (contains '.php', e.g. 'edit.php',
	 * 'options-general.php', 'edit.php?post_type=page') which admin_url() turns into a
	 * link, or a registered page slug (e.g. 'wpca-settings') resolved via
	 * menu_page_url(..., false) (returns rather than echoes), falling back to the
	 * admin.php?page= form if that yields ''. The value is escaped with esc_url_raw()
	 * by the caller before it enters the payload.
	 */
	private function slug_to_url( string $slug ): string {
		if ( false !== strpos( $slug, '.php' ) ) {
			return admin_url( $slug );
		}

		$url = menu_page_url( $slug, false );

		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}

		return admin_url( 'admin.php?page=' . $slug );
	}
}
