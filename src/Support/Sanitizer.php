<?php
/**
 * The single settings sanitizer.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The one trusted choke point before settings reach the database.
 *
 * Used both as the register_setting() sanitize_callback and when importing a
 * brand.json file, so imported data can never bypass validation. Whitelists
 * known keys only and sanitizes each by its specific type.
 */
final class Sanitizer {

	/**
	 * Boolean keys (absent in POST means unchecked => false).
	 */
	private const BOOLEANS = array(
		'branding_enabled',
		'login_enabled',
		'menu_enabled',
		'whitelabel_enabled',
		'dashboard_enabled',
		'dashboard_landing',
		'hide_wp_logo',
		'replace_howdy',
		'hide_welcome_panel',
		'hide_wp_news',
		'hide_dashboard_widgets',
		'hide_version_footer',
		'hide_update_nag',
		'hide_screen_options',
		'delete_on_uninstall',
	);

	/**
	 * Hex color keys.
	 */
	private const COLORS = array(
		'primary_color',
		'primary_hover_color',
		'accent_color',
		'menu_bg_color',
		'menu_text_color',
		'menu_highlight_color',
		'adminbar_bg_color',
		'login_bg_color',
	);

	/**
	 * Validate and sanitize a raw settings array.
	 *
	 * @param mixed $input Raw input (POST array or decoded JSON).
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = Settings::defaults();
		$out      = array();

		foreach ( self::BOOLEANS as $key ) {
			$out[ $key ] = ! empty( $input[ $key ] );
		}

		foreach ( self::COLORS as $key ) {
			$out[ $key ] = self::color( $input[ $key ] ?? '', (string) $defaults[ $key ] );
		}

		$out['product_name']  = sanitize_text_field( (string) ( $input['product_name'] ?? '' ) );
		$out['logo_id']       = absint( $input['logo_id'] ?? 0 );
		$out['login_logo_id'] = absint( $input['login_logo_id'] ?? 0 );

		$font               = sanitize_key( (string) ( $input['font_family'] ?? 'system' ) );
		$out['font_family'] = in_array( $font, Settings::FONTS, true ) ? $font : 'system';

		$out['login_header_url'] = esc_url_raw( (string) ( $input['login_header_url'] ?? '' ) );

		// Limited HTML only. Modern browsers apply rel="noopener" to target="_blank" automatically.
		$out['footer_text'] = wp_kses(
			(string) ( $input['footer_text'] ?? '' ),
			array(
				'a'      => array(
					'href'   => array(),
					'rel'    => array(),
					'target' => array(),
				),
				'strong' => array(),
				'em'     => array(),
				'span'   => array(),
				'br'     => array(),
			)
		);

		$cap                           = sanitize_key( (string) ( $input['full_access_capability'] ?? 'manage_options' ) );
		$out['full_access_capability'] = '' !== $cap ? $cap : 'manage_options';

		$out['menu_hidden']  = self::slug_list( $input['menu_hidden'] ?? array() );
		$out['menu_order']   = self::slug_list( $input['menu_order'] ?? array() );
		$out['menu_renames'] = self::rename_map( $input['menu_renames'] ?? array() );

		return $out;
	}

	/**
	 * Sanitize a hex color, with a Customizer-independent fallback.
	 *
	 * sanitize_hex_color() only loads with the Customizer, so guard it and fall
	 * back to a strict regex, then to the supplied default.
	 *
	 * @param mixed  $value    Raw color value.
	 * @param string $fallback Default to use when invalid/empty.
	 */
	private static function color( $value, string $fallback ): string {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return $fallback;
		}

		if ( function_exists( 'sanitize_hex_color' ) ) {
			$clean = sanitize_hex_color( $value );

			return ( is_string( $clean ) && '' !== $clean ) ? $clean : $fallback;
		}

		return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ? $value : $fallback;
	}

	/**
	 * Sanitize a list of menu slugs (accepts an array or newline/comma string).
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	private static function slug_list( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\r\n,]+/', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();

		foreach ( $value as $item ) {
			$slug = trim( sanitize_text_field( (string) $item ) );

			if ( '' !== $slug ) {
				$out[] = $slug;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Sanitize a slug => label rename map, dropping empty labels.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string,string>
	 */
	private static function rename_map( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();

		foreach ( $value as $slug => $label ) {
			$slug  = trim( sanitize_text_field( (string) $slug ) );
			$label = trim( sanitize_text_field( (string) $label ) );

			if ( '' !== $slug && '' !== $label ) {
				$out[ $slug ] = $label;
			}
		}

		return $out;
	}
}
