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
		'system'    => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif",
		'inter'     => "'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif",
		'georgia'   => "Georgia,'Times New Roman',serif",
		'monospace' => "ui-monospace,SFMono-Regular,Menlo,Consolas,monospace",
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
		wp_enqueue_style( 'wpca-admin', WPCA_URL . 'assets/css/admin.css', array(), WPCA_VERSION );
		wp_add_inline_style( 'wpca-admin', $this->root_css( false ) );
	}

	/**
	 * Enqueue the login reskin and inject the brand tokens.
	 */
	public function enqueue_login(): void {
		wp_enqueue_style( 'wpca-login', WPCA_URL . 'assets/css/login.css', array(), WPCA_VERSION );
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
		return $classes . ' wpca-admin ';
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

		$logo = $login ? $settings->login_logo_url( 'medium' ) : $settings->logo_url( 'medium' );

		if ( ! $login && '' !== $logo ) {
			// Reveal the admin-menu logo block (see assets/css/admin.css).
			$lines[] = "--wpca-logo:url('" . esc_url( $logo ) . "')";
			$lines[] = '--wpca-logo-display:block';
		}

		$css = ':root{' . implode( ';', $lines ) . ';}';

		// Only override the login logo when one is configured; otherwise leave WP's default.
		if ( $login && '' !== $logo ) {
			$css .= "body.login h1 a{background-image:url('" . esc_url( $logo ) . "');background-size:contain;background-position:center;width:auto;max-width:320px;height:80px;}";
		}

		return $css;
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
