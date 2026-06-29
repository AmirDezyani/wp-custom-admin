<?php
/**
 * White-label module: removes WordPress branding from the admin.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Modules\WhiteLabel;

use WPCustomAdmin\Modules\AbstractModule;
use WP_Admin_Bar;

defined( 'ABSPATH' ) || exit;

/**
 * Strips the WP logo, "Howdy" greeting, footer credit/version, update nags,
 * welcome panel, and news widget. Update-related items stay visible to users
 * who can actually update core, so sites never silently miss security updates.
 */
final class WhiteLabelModule extends AbstractModule {

	public function id(): string {
		return 'whitelabel';
	}

	public function is_enabled( array $settings ): bool {
		return ! empty( $settings['whitelabel_enabled'] );
	}

	public function register(): void {
		// Priority 999: the target nodes must already exist.
		add_action( 'admin_bar_menu', array( $this, 'tweak_admin_bar' ), 999 );
		add_filter( 'admin_footer_text', array( $this, 'footer_text' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_cleanup' ) );

		if ( $this->settings->flag( 'hide_version_footer' ) ) {
			// Priority 11: after core_update_footer (10).
			add_filter( 'update_footer', '__return_empty_string', 11 );
		}

		if ( $this->settings->flag( 'hide_update_nag' ) ) {
			add_action( 'admin_init', array( $this, 'hide_update_nag' ) );
		}

		if ( $this->settings->flag( 'hide_screen_options' ) ) {
			add_filter( 'screen_options_show_screen', array( $this, 'maybe_hide_screen_options' ) );
		}
	}

	/**
	 * Remove the WP logo node and strip the "Howdy," greeting.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Toolbar instance.
	 */
	public function tweak_admin_bar( WP_Admin_Bar $wp_admin_bar ): void {
		if ( $this->settings->flag( 'hide_wp_logo' ) ) {
			$wp_admin_bar->remove_node( 'wp-logo' );
		}

		if ( $this->settings->flag( 'replace_howdy' ) ) {
			$node = $wp_admin_bar->get_node( 'my-account' );

			if ( is_object( $node ) && isset( $node->title ) ) {
				$title = (string) $node->title;

				// The greeting is whatever precedes the display-name span. Stripping by
				// that marker is locale-agnostic and robust to wording changes ("Howdy,").
				$pos = strpos( $title, '<span class="display-name">' );

				if ( false !== $pos ) {
					$title = substr( $title, $pos );
				} else {
					/* translators: %s: user display name (core string, reused to derive the prefix). */
					$title = str_replace( sprintf( __( 'Howdy, %s' ), '' ), '', $title ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string.
				}

				$wp_admin_bar->add_node(
					array(
						'id'     => 'my-account',
						'title'  => $title,
						'parent' => $node->parent,
					)
				);
			}
		}
	}

	/**
	 * White-label the left-hand admin footer text.
	 *
	 * @param string $text Existing footer text.
	 */
	public function footer_text( string $text ): string {
		$custom = (string) $this->settings->get( 'footer_text', '' );

		// Stored footer text is already passed through wp_kses on save.
		return '' !== $custom ? $custom : esc_html( $this->settings->product_name() );
	}

	/**
	 * Remove default dashboard widgets per the configured toggles.
	 */
	public function dashboard_cleanup(): void {
		if ( $this->settings->flag( 'hide_welcome_panel' ) ) {
			remove_action( 'welcome_panel', 'wp_welcome_panel' );
		}

		if ( $this->settings->flag( 'hide_wp_news' ) ) {
			remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		}

		if ( $this->settings->flag( 'hide_dashboard_widgets' ) ) {
			$widgets = array(
				array( 'dashboard_activity', 'normal' ),
				array( 'dashboard_right_now', 'normal' ),
				array( 'dashboard_quick_press', 'side' ),
				array( 'dashboard_site_health', 'normal' ),
			);

			foreach ( $widgets as $widget ) {
				remove_meta_box( $widget[0], 'dashboard', $widget[1] );
			}
		}
	}

	/**
	 * Hide the "please update" nag from users who cannot update core.
	 */
	public function hide_update_nag(): void {
		if ( ! current_user_can( 'update_core' ) ) {
			remove_action( 'admin_notices', 'update_nag', 3 );
			remove_action( 'network_admin_notices', 'update_nag', 3 );
		}
	}

	/**
	 * Hide the Screen Options tab for non full-access users.
	 *
	 * @param bool $show_screen Whether to show the Screen Options tab.
	 */
	public function maybe_hide_screen_options( bool $show_screen ): bool {
		$capability = (string) $this->settings->get( 'full_access_capability', 'manage_options' );

		if ( '' !== $capability && current_user_can( $capability ) ) {
			return $show_screen;
		}

		return false;
	}
}
