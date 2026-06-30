<?php
/**
 * Asset pipeline: enqueue styles and inject the brand CSS-variable block.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the static reskin stylesheets and emits the per-site brand tokens.
 *
 * The static CSS is authored entirely against var(--wpca-*); only the small
 * :root{} block of values is generated per site. This builder is the ONLY place
 * stored color values reach CSS, so it re-validates every value (defense in depth).
 */
final class Assets {

	/**
	 * Allowed font-family stacks, keyed by the stored font_family value.
	 */
	private const FONT_STACKS = array(
		// Vazirmatn/Tahoma fallbacks ensure Persian/Arabic glyphs render on RTL sites.
		'system'    => '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,Vazirmatn,Tahoma,sans-serif',
		'inter'     => '"Inter","Segoe UI",Roboto,Helvetica,Arial,Vazirmatn,Tahoma,sans-serif',
		'georgia'   => 'Georgia,"Times New Roman",Vazirmatn,Tahoma,serif',
		'monospace' => 'ui-monospace,SFMono-Regular,Menlo,Consolas,Vazirmatn,Tahoma,monospace',
	);

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Enqueue the admin reskin and inject the brand tokens.
	 *
	 * @param string $hook_suffix Current admin page hook (unused; always-load is intentional).
	 */
	public function enqueue_admin( string $hook_suffix = '' ): void {
		wp_enqueue_style( 'wpca-fonts', WPCA_URL . 'assets/css/fonts.css', array(), WPCA_VERSION );
		wp_enqueue_style( 'wpca-admin', WPCA_URL . 'assets/css/admin.css', array( 'wpca-fonts' ), WPCA_VERSION );
		wp_add_inline_style( 'wpca-admin', $this->root_css( false ) );

		// Builds the branded header at the top of the menu rail from server-sanitized data.
		wp_enqueue_script( 'wpca-admin', WPCA_URL . 'assets/js/admin.js', array(), WPCA_VERSION, true );
		wp_localize_script( 'wpca-admin', 'wpcaAdmin', $this->brand_data() );
	}

	/**
	 * Enqueue the login reskin and inject the brand tokens.
	 */
	public function enqueue_login(): void {
		wp_enqueue_style( 'wpca-fonts', WPCA_URL . 'assets/css/fonts.css', array(), WPCA_VERSION );
		wp_enqueue_style( 'wpca-login', WPCA_URL . 'assets/css/login.css', array( 'wpca-fonts' ), WPCA_VERSION );
		wp_add_inline_style( 'wpca-login', $this->root_css( true ) );
	}

	/**
	 * Append our scoping marker to the admin body class string.
	 *
	 * Note: admin_body_class passes/returns a space-separated STRING, not an array.
	 *
	 * @param string $classes Existing class string.
	 */
	public function body_class( string $classes ): string {
		$scheme = sanitize_html_class( (string) $this->settings->get( 'color_scheme', 'auto' ) );

		return $classes . ' wpca-admin wpca-scheme-' . $scheme . ' ';
	}

	/**
	 * Build the :root{} custom-property block from sanitized settings.
	 *
	 * @param bool $login Whether to emit the login-specific tokens.
	 */
	private function root_css( bool $login ): string {
		$settings = $this->settings;

		$colors = array(
			'--wpca-primary'        => 'primary_color',
			'--wpca-primary-hover'  => 'primary_hover_color',
			'--wpca-accent'         => 'accent_color',
			'--wpca-menu-bg'        => 'menu_bg_color',
			'--wpca-menu-text'      => 'menu_text_color',
			'--wpca-menu-highlight' => 'menu_highlight_color',
			'--wpca-adminbar-bg'    => 'adminbar_bg_color',
		);

		if ( $login ) {
			$colors['--wpca-login-bg'] = 'login_bg_color';
		}

		$lines = array();

		foreach ( $colors as $var => $key ) {
			$lines[] = $var . ':' . $this->safe_color( (string) $settings->get( $key, '' ) );
		}

		$lines[] = '--wpca-font:' . $this->font_stack( (string) $settings->get( 'font_family', 'system' ) );

		// The admin-menu logo is rendered by assets/js/admin.js; only login needs a CSS URL.
		$logo = $login ? $settings->login_logo_url( 'medium' ) : '';

		$css = ':root{' . implode( ';', $lines ) . ';}';

		// Only override the login logo when one is configured; otherwise leave WP's default.
		if ( $login && '' !== $logo ) {
			$css .= "body.login h1 a{background-image:url('" . esc_url( $logo ) . "');background-size:contain;background-position:center;width:auto;max-width:320px;height:80px;}";
		}

		return $css;
	}

	/**
	 * Brand payload for the menu header injected by assets/js/admin.js.
	 *
	 * @return array<string,string>
	 */
	private function brand_data(): array {
		$name = $this->settings->product_name();
		$logo = $this->settings->logo_url( 'medium' );

		return array(
			'brandName' => $name,
			'logoUrl'   => '' !== $logo ? esc_url_raw( $logo ) : '',
			'initial'   => $this->brand_initial( $name ),
		);
	}

	/**
	 * First letter of the brand name, uppercased, for the monogram fallback.
	 *
	 * @param string $name Brand/product name.
	 */
	private function brand_initial( string $name ): string {
		$name = trim( $name );

		if ( '' === $name ) {
			return 'W';
		}

		// Keep both mbstring calls on one guard: splitting them could feed a
		// multibyte glyph to substr()/strtoupper() and mangle a Persian initial.
		if ( function_exists( 'mb_substr' ) ) {
			return mb_strtoupper( mb_substr( $name, 0, 1 ) );
		}

		return strtoupper( $name[0] );
	}

	/**
	 * Re-validate a hex color at render time; never emit untrusted input into CSS.
	 *
	 * @param string $value Stored color value.
	 */
	private function safe_color( string $value ): string {
		$value = trim( $value );

		if ( function_exists( 'sanitize_hex_color' ) ) {
			$clean = sanitize_hex_color( $value );

			if ( is_string( $clean ) && '' !== $clean ) {
				return $clean;
			}
		} elseif ( 1 === preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ) {
			return $value;
		}

		return '#000000';
	}

	/**
	 * Map a stored font key to a safe, allowlisted CSS stack.
	 *
	 * @param string $key Stored font_family value.
	 */
	private function font_stack( string $key ): string {
		return self::FONT_STACKS[ $key ] ?? self::FONT_STACKS['system'];
	}
}
