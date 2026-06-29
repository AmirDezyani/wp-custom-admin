<?php
/**
 * Settings access + resolution.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for plugin configuration.
 *
 * All config lives in ONE option (wpca_settings). Reads merge stored values over
 * code defaults at read time, so a fresh install is already branded and new keys
 * self-heal across versions.
 *
 * Resolution precedence (lowest -> highest):
 *   defaults() -> 'wpca_default_settings' filter -> WPCA_CONFIG constant
 *   -> network option (multisite) -> per-site option.
 */
final class Settings {

	public const OPTION         = 'wpca_settings';
	public const NETWORK_OPTION = 'wpca_network_settings';
	public const SETTINGS_GROUP = 'wpca_settings_group';

	/**
	 * Allowed font-family choices (value => maps to a stack in Assets).
	 */
	public const FONTS = array( 'system', 'inter', 'georgia', 'monospace' );

	/**
	 * Lazily-resolved settings cache.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $resolved = null;

	/**
	 * The fully resolved settings array.
	 *
	 * @return array<string,mixed>
	 */
	public function all(): array {
		if ( null === $this->resolved ) {
			$this->resolved = $this->resolve();
		}

		return $this->resolved;
	}

	/**
	 * Get a single setting with a fallback.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Value to return when the key is absent.
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Boolean accessor.
	 */
	public function flag( string $key ): bool {
		return (bool) $this->get( $key, false );
	}

	/**
	 * The white-label product name, falling back to the site name.
	 */
	public function product_name(): string {
		$name = trim( (string) $this->get( 'product_name', '' ) );

		return '' !== $name ? $name : (string) get_bloginfo( 'name' );
	}

	/**
	 * Resolve the admin logo URL from its stored attachment id.
	 *
	 * @param string $size Registered image size.
	 */
	public function logo_url( string $size = 'medium' ): string {
		$id = absint( $this->get( 'logo_id', 0 ) );

		if ( 0 === $id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $id, $size );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Resolve the login logo URL, falling back to the admin logo.
	 *
	 * @param string $size Registered image size.
	 */
	public function login_logo_url( string $size = 'medium' ): string {
		$id = absint( $this->get( 'login_logo_id', 0 ) );

		if ( 0 === $id ) {
			return $this->logo_url( $size );
		}

		$url = wp_get_attachment_image_url( $id, $size );

		return is_string( $url ) ? $url : $this->logo_url( $size );
	}

	/**
	 * Clear the in-memory cache (used after a save in the same request).
	 */
	public function flush(): void {
		$this->resolved = null;
	}

	/**
	 * Compose the resolved settings using the documented precedence.
	 *
	 * @return array<string,mixed>
	 */
	private function resolve(): array {
		$base = self::defaults();

		/**
		 * Filter the baseline default settings (applied before stored values).
		 *
		 * @param array<string,mixed> $base Default settings.
		 */
		$base = (array) apply_filters( 'wpca_default_settings', $base );

		if ( defined( 'WPCA_CONFIG' ) && is_array( WPCA_CONFIG ) ) {
			$base = array_merge( $base, WPCA_CONFIG );
		}

		$resolved = $base;

		if ( is_multisite() ) {
			$network = get_site_option( self::NETWORK_OPTION, array() );

			if ( is_array( $network ) ) {
				$resolved = array_merge( $resolved, $network );
			}
		}

		$site = get_option( self::OPTION, array() );

		if ( is_array( $site ) ) {
			$resolved = array_merge( $resolved, $site );
		}

		return $resolved;
	}

	/**
	 * Hardcoded defaults. A fresh install renders correctly from these alone.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Module switches.
			'branding_enabled'       => true,
			'login_enabled'          => true,
			'menu_enabled'           => false,
			'whitelabel_enabled'     => true,

			// Brand identity.
			'product_name'           => '',
			'logo_id'                => 0,
			'login_logo_id'          => 0,
			'font_family'            => 'system',

			// Color palette (admin).
			'primary_color'          => '#4f46e5',
			'primary_hover_color'    => '#4338ca',
			'accent_color'           => '#06b6d4',
			'menu_bg_color'          => '#18181b',
			'menu_text_color'        => '#cbd5e1',
			'menu_highlight_color'   => '#4f46e5',
			'adminbar_bg_color'      => '#111827',

			// Login page.
			'login_bg_color'         => '#0f172a',
			'login_header_url'       => '',

			// Admin menu control (data-driven; applied to non full-access users).
			'full_access_capability' => 'manage_options',
			'menu_hidden'            => array(),
			'menu_renames'           => array(),
			'menu_order'             => array(),

			// White-label switches.
			'hide_wp_logo'           => true,
			'replace_howdy'          => true,
			'hide_welcome_panel'     => true,
			'hide_wp_news'           => true,
			'hide_dashboard_widgets' => false,
			'footer_text'            => '',
			'hide_version_footer'    => false,
			'hide_update_nag'        => false,
			'hide_screen_options'    => false,

			// Lifecycle.
			'delete_on_uninstall'    => false,
		);
	}
}
