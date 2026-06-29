<?php
/**
 * Dashboard module: a custom, branded "Home" screen that replaces the default
 * WordPress dashboard as the admin landing page.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Modules\Dashboard;

use WPCustomAdmin\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a bespoke top-level "Home" page and (optionally) redirects the stock
 * dashboard to it, so the first thing a user sees is a product-grade screen rather
 * than WordPress' widget grid.
 */
final class DashboardModule extends AbstractModule {

	private const MENU_SLUG = 'wpca-home';
	private const PAGE_HOOK  = 'toplevel_page_wpca-home';

	public function id(): string {
		return 'dashboard';
	}

	public function is_enabled( array $settings ): bool {
		return ! empty( $settings['dashboard_enabled'] );
	}

	public function register(): void {
		// Priority 2: place "Home" at the very top of the menu.
		add_action( 'admin_menu', array( $this, 'add_page' ), 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		if ( $this->settings->flag( 'dashboard_landing' ) ) {
			add_action( 'load-index.php', array( $this, 'redirect_to_home' ) );
		}
	}

	/**
	 * Register the branded Home page.
	 */
	public function add_page(): void {
		add_menu_page(
			$this->settings->product_name(),
			__( 'Home', 'wp-custom-admin' ),
			'read',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-grid-view',
			2
		);
	}

	/**
	 * Send the stock dashboard to the branded Home page.
	 */
	public function redirect_to_home(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	/**
	 * Enqueue the dashboard styles on the Home page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( self::PAGE_HOOK !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wpca-dashboard', WPCA_URL . 'assets/css/dashboard.css', array(), WPCA_VERSION );
	}

	/**
	 * Render the Home page.
	 */
	public function render(): void {
		require WPCA_PATH . 'src/Admin/views/dashboard-page.php';
	}

	/**
	 * Dashboard stat tiles: label, count, dashicon, and a destination URL.
	 *
	 * @return array<int,array<string,string|int>>
	 */
	public function stats(): array {
		$posts    = wp_count_posts( 'post' );
		$pages    = wp_count_posts( 'page' );
		$comments = wp_count_comments();

		return array(
			array(
				'label' => __( 'Posts', 'wp-custom-admin' ),
				'count' => isset( $posts->publish ) ? (int) $posts->publish : 0,
				'icon'  => 'dashicons-admin-post',
				'url'   => admin_url( 'edit.php' ),
			),
			array(
				'label' => __( 'Pages', 'wp-custom-admin' ),
				'count' => isset( $pages->publish ) ? (int) $pages->publish : 0,
				'icon'  => 'dashicons-admin-page',
				'url'   => admin_url( 'edit.php?post_type=page' ),
			),
			array(
				'label' => __( 'Comments', 'wp-custom-admin' ),
				'count' => isset( $comments->approved ) ? (int) $comments->approved : 0,
				'icon'  => 'dashicons-admin-comments',
				'url'   => admin_url( 'edit-comments.php' ),
			),
			array(
				'label' => __( 'Users', 'wp-custom-admin' ),
				'count' => (int) ( count_users()['total_users'] ?? 0 ),
				'icon'  => 'dashicons-admin-users',
				'url'   => admin_url( 'users.php' ),
			),
		);
	}

	/**
	 * Quick-action shortcuts, filtered by capability.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function quick_actions(): array {
		$actions = array();

		if ( current_user_can( 'edit_posts' ) ) {
			$actions[] = array(
				'label' => __( 'New post', 'wp-custom-admin' ),
				'icon'  => 'dashicons-edit',
				'url'   => admin_url( 'post-new.php' ),
			);
		}

		if ( current_user_can( 'edit_pages' ) ) {
			$actions[] = array(
				'label' => __( 'New page', 'wp-custom-admin' ),
				'icon'  => 'dashicons-plus-alt',
				'url'   => admin_url( 'post-new.php?post_type=page' ),
			);
		}

		if ( current_user_can( 'upload_files' ) ) {
			$actions[] = array(
				'label' => __( 'Media library', 'wp-custom-admin' ),
				'icon'  => 'dashicons-format-image',
				'url'   => admin_url( 'upload.php' ),
			);
		}

		if ( current_user_can( 'manage_options' ) ) {
			$actions[] = array(
				'label' => __( 'Brand settings', 'wp-custom-admin' ),
				'icon'  => 'dashicons-admin-customizer',
				'url'   => admin_url( 'admin.php?page=wpca-settings' ),
			);
		}

		return $actions;
	}

	/**
	 * Most recent posts for the activity panel.
	 *
	 * @return \WP_Post[]
	 */
	public function recent_posts(): array {
		return get_posts(
			array(
				'numberposts' => 5,
				'post_status' => 'publish',
			)
		);
	}
}
