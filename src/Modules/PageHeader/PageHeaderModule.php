<?php
/**
 * Page-header module: a breadcrumb trail bound to the screen's H1 + action.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Modules\PageHeader;

use WPCustomAdmin\Modules\AbstractModule;
use WP_Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Injects a breadcrumb ("Section › Current page") just above the screen's `.wrap`,
 * which CSS then visually binds with the existing H1 and primary action into one
 * header band with a single closing hairline.
 *
 * This module is READ-ONLY: it only echoes derived markup, performs no writes, and
 * mirrors menu items the current user already reached (the section label/URL come
 * from `$menu`/`$submenu`, which core already filters per the user's capabilities),
 * so no nonce or capability gate is needed. Core's H1 is never removed or replaced.
 */
final class PageHeaderModule extends AbstractModule {

	/**
	 * Screens that own their header chrome and must not get a breadcrumb.
	 *
	 * The plugin's branded Home page renders its own hero; the settings page has
	 * its own .wpca-header. A breadcrumb on either would duplicate that header.
	 */
	private const SKIP_SCREEN_IDS = array(
		'toplevel_page_wpca-home',
		'toplevel_page_wpca-settings',
	);

	/**
	 * Full-screen, chrome-less screens that hide the classic `.wrap`. is_block_editor()
	 * does NOT cover the Site Editor or the Customizer, so they are matched by base.
	 */
	private const SKIP_SCREEN_BASES = array(
		'site-editor',
		'customize',
	);

	public function id(): string {
		return 'pageheader';
	}

	/**
	 * Requires BOTH its own flag AND branding: the breadcrumb's entire skin (and the
	 * `body.wpca-admin` scope it lives under) is emitted only by BrandingModule. With
	 * branding off the markup would render unstyled, so the page header is gated to the
	 * branded substrate it depends on.
	 *
	 * @param array<string,mixed> $settings Resolved settings.
	 */
	public function is_enabled( array $settings ): bool {
		return ! empty( $settings['pageheader_enabled'] )
			&& ! empty( $settings['branding_enabled'] );
	}

	public function register(): void {
		// Priority PHP_INT_MAX (last) on all_admin_notices: core dispatches the
		// notice actions inside #wpbody-content immediately BEFORE the screen's page
		// callback echoes `.wrap`, and all_admin_notices is the single unified
		// channel that always fires there. Running last guarantees our <nav> lands
		// directly above `.wrap` and BELOW every other notice — notices stay at the
		// top where users expect them, while the breadcrumb hugs the header band.
		// (Load-bearing: if core ever dispatches notices outside #wpbody-content the
		// visual binding to `.wrap` breaks silently; re-verify each WP major.)
		add_action( 'all_admin_notices', array( $this, 'render_breadcrumb' ), PHP_INT_MAX );
	}

	/**
	 * Emit the breadcrumb <nav>, after applying every skip rule.
	 */
	public function render_breadcrumb(): void {
		// Never emit markup in async admin contexts — the notice actions can fire
		// there, but there is no page chrome to bind to.
		if ( wp_doing_ajax() ) {
			return;
		}

		// Leave network admin untouched (CLAUDE.md §8); all_admin_notices fires there too.
		if ( is_network_admin() ) {
			return;
		}

		$screen = get_current_screen();

		// Guard before touching the screen object (also covers screens not yet primed).
		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		// The block editor / full-screen editors replace `.wrap` with their own
		// full-height root and hide the classic header, so the band would float
		// orphaned. is_block_editor() is the supported detection API; the Site
		// Editor and Customizer are chrome-less but NOT block-editor screens, so
		// they are matched by base as well.
		if ( $screen->is_block_editor() ) {
			return;
		}

		if ( in_array( $screen->base, self::SKIP_SCREEN_BASES, true ) ) {
			return;
		}

		// Skip screens that render their own header chrome.
		if ( in_array( $screen->id, self::SKIP_SCREEN_IDS, true ) ) {
			return;
		}

		$current_title = $this->resolve_current_title( $screen );

		// No resolvable title means no meaningful crumb — render nothing rather
		// than a stray separator.
		if ( '' === $current_title ) {
			return;
		}

		list( $section_label, $section_url ) = $this->resolve_section();

		// A self-referential "Dashboard › Dashboard" is noise: when the section is
		// the current page (top-level screens) or no section resolved, show a
		// single unlinked crumb.
		$is_two_segment = '' !== $section_label
			&& '' !== $section_url
			&& $section_label !== $current_title;

		echo '<nav class="wpca-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'wp-custom-admin' ) . '">';

		if ( $is_two_segment ) {
			// Section label/URL come from core menu data (already-localized); do
			// NOT wrap in __(). The label may carry count bubbles → strip first.
			printf(
				'<a class="wpca-crumb" href="%1$s">%2$s</a>',
				esc_url( $section_url ),
				esc_html( wp_strip_all_tags( $section_label ) )
			);
		}

		printf(
			'<span class="wpca-crumb wpca-crumb-current" aria-current="page">%1$s</span>',
			esc_html( wp_strip_all_tags( $current_title ) )
		);

		echo '</nav>';
	}

	/**
	 * Resolve the rightmost (current, unlinked) crumb text.
	 *
	 * WP_Screen labels are primed before admin-header.php dispatches notices (set in
	 * WP_Screen::set_current_screen), so they are the authoritative source — unlike
	 * get_admin_page_title()/$GLOBALS['title'], which are reliable only on
	 * admin.php?page= screens and are often empty/late on core list-table screens.
	 *
	 * Order of trust: post-type / taxonomy label for edit screens → the screen's own
	 * label → get_admin_page_title() → $GLOBALS['title'] → the screen base. All
	 * already-localized core data, so it is not wrapped in __().
	 */
	private function resolve_current_title( WP_Screen $screen ): string {
		$title = $this->screen_object_label( $screen );

		if ( '' === $title && is_string( $screen->label ) ) {
			$title = trim( wp_strip_all_tags( $screen->label ) );
		}

		if ( '' === $title ) {
			$title = trim( wp_strip_all_tags( (string) get_admin_page_title() ) );
		}

		if ( '' === $title && isset( $GLOBALS['title'] ) ) {
			$title = trim( wp_strip_all_tags( (string) $GLOBALS['title'] ) );
		}

		if ( '' === $title ) {
			$title = trim( (string) $screen->base );
		}

		return $title;
	}

	/**
	 * Prefer the post-type's or taxonomy's own object label on edit screens, where
	 * get_admin_page_title() tends to return the section name (collapsing the trail).
	 */
	private function screen_object_label( WP_Screen $screen ): string {
		if ( '' !== (string) $screen->post_type && null !== $screen->post_type ) {
			$object = get_post_type_object( (string) $screen->post_type );

			if ( null !== $object && isset( $object->labels->name ) ) {
				return trim( wp_strip_all_tags( (string) $object->labels->name ) );
			}
		}

		if ( '' !== (string) $screen->taxonomy && null !== $screen->taxonomy ) {
			$object = get_taxonomy( (string) $screen->taxonomy );

			if ( false !== $object && isset( $object->labels->name ) ) {
				return trim( wp_strip_all_tags( (string) $object->labels->name ) );
			}
		}

		return '';
	}

	/**
	 * Resolve the leftmost (section, linked) crumb as [ label, url ].
	 *
	 * Derives the top-level parent slug from the globals core itself uses to
	 * highlight the menu (set in wp-admin/menu-header.php), then matches it against
	 * `$menu` so MenuModule renames are honored — and builds the URL from the matched
	 * menu item's OWN slug (that slug already IS a working menu href), which is more
	 * robust than re-deriving the link from $parent_file. Returns empty strings when
	 * no section resolves, collapsing the trail to a single crumb.
	 *
	 * @return array{0:string,1:string}
	 */
	private function resolve_section(): array {
		$parent = $this->resolve_parent_slug();

		if ( '' === $parent ) {
			return array( '', '' );
		}

		global $menu;

		if ( ! is_array( $menu ) ) {
			return array( '', '' );
		}

		foreach ( $menu as $item ) {
			// $item[2] is the slug; $item[0] the (possibly renamed) display label.
			if ( ! isset( $item[2], $item[0] ) || $parent !== (string) $item[2] ) {
				continue;
			}

			// Build the link from the matched menu item's own slug, not $parent_file.
			return array( (string) $item[0], $this->section_url( (string) $item[2] ) );
		}

		return array( '', '' );
	}

	/**
	 * Compute the effective top-level parent slug for the current screen.
	 *
	 * Core normalizes CPT/parent resolution into $parent_file, so trust it when
	 * set; otherwise derive from $pagenow/$typenow; last resort is $pagenow.
	 *
	 * Audit note: $parent_file / $pagenow / $typenow can reflect request input
	 * ($_GET page=, post_type=). They are used here only to MATCH an existing
	 * $menu entry and to compose a URL that is escaped with esc_url() at the echo
	 * site — that sink is the trust boundary (neutralizes javascript:, attribute
	 * breakout, CRLF). No value reaches output unescaped.
	 */
	private function resolve_parent_slug(): string {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- reading only.
		$parent_file = isset( $GLOBALS['parent_file'] ) ? (string) $GLOBALS['parent_file'] : '';
		$pagenow     = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		$typenow     = isset( $GLOBALS['typenow'] ) ? (string) $GLOBALS['typenow'] : '';
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		if ( '' !== $parent_file ) {
			return $parent_file;
		}

		// Post-type list/edit screens map to their CPT's edit.php parent.
		if ( in_array( $pagenow, array( 'edit.php', 'post-new.php', 'post.php' ), true ) ) {
			if ( '' !== $typenow && 'post' !== $typenow ) {
				return 'edit.php?post_type=' . $typenow;
			}

			return 'edit.php';
		}

		return $pagenow;
	}

	/**
	 * Build the section URL from its (menu-item) slug.
	 *
	 * `admin.php?page=` slugs resolve via menu_page_url(); any other value is itself
	 * a relative admin URL fragment (e.g. 'options-general.php', 'edit.php?post_type=page')
	 * that admin_url() turns into a working link. Escaping is applied at the echo site
	 * (esc_url) — see the audit note on resolve_parent_slug().
	 */
	private function section_url( string $slug ): string {
		if ( 0 === strpos( $slug, 'admin.php?page=' ) ) {
			$page = substr( $slug, strlen( 'admin.php?page=' ) );
			$url  = menu_page_url( $page, false );

			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return admin_url( $slug );
	}
}
