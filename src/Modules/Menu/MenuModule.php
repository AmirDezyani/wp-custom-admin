<?php
/**
 * Menu module: data-driven admin menu control.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Modules\Menu;

use WPCustomAdmin\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Renames, hides, and (optionally) reorders top-level admin menu items.
 *
 * Renames apply to everyone (cosmetic branding). Hiding applies only to users
 * who LACK the configured full-access capability, so administrators keep the
 * complete menu. Menu hiding is cosmetic — pair with capabilities for real
 * access control (hidden pages remain reachable by URL).
 */
final class MenuModule extends AbstractModule {

	public function id(): string {
		return 'menu';
	}

	public function is_enabled( array $settings ): bool {
		return ! empty( $settings['menu_enabled'] );
	}

	public function register(): void {
		// Priority 999: run after all plugins have registered their menus.
		add_action( 'admin_menu', array( $this, 'apply' ), 999 );

		$order = (array) $this->settings->get( 'menu_order', array() );

		if ( array() !== $order ) {
			// custom_menu_order must return true before menu_order is honored.
			add_filter( 'custom_menu_order', '__return_true' );
			add_filter( 'menu_order', array( $this, 'order' ) );
		}
	}

	/**
	 * Apply renames (everyone) and removals (non full-access users only).
	 */
	public function apply(): void {
		$this->apply_renames();

		$capability = (string) $this->settings->get( 'full_access_capability', 'manage_options' );

		if ( '' !== $capability && current_user_can( $capability ) ) {
			return;
		}

		foreach ( (array) $this->settings->get( 'menu_hidden', array() ) as $slug ) {
			remove_menu_page( (string) $slug );
		}
	}

	/**
	 * Overwrite top-level menu labels by matching on slug (never on index).
	 */
	private function apply_renames(): void {
		$renames = (array) $this->settings->get( 'menu_renames', array() );

		if ( array() === $renames ) {
			return;
		}

		global $menu;

		if ( ! is_array( $menu ) ) {
			return;
		}

		foreach ( $menu as $index => $item ) {
			// $item[2] is the menu slug; $item[0] is the display label.
			if ( isset( $item[2], $renames[ $item[2] ] ) ) {
				$menu[ $index ][0] = $renames[ $item[2] ];
			}
		}
	}

	/**
	 * Return the configured menu order; WordPress appends any unlisted slugs.
	 *
	 * @param array<int,string> $menu_order Current order.
	 * @return array<int,string>
	 */
	public function order( array $menu_order ): array {
		$configured = array_values( array_filter( (array) $this->settings->get( 'menu_order', array() ) ) );

		return array() !== $configured ? $configured : $menu_order;
	}
}
