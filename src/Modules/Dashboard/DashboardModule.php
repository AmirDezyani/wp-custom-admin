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

		// Keep the cached trend metrics fresh without querying on every page load.
		foreach ( array( 'save_post', 'deleted_post', 'comment_post', 'transition_comment_status', 'user_register', 'deleted_user' ) as $event ) {
			add_action( $event, array( $this, 'flush_trends' ) );
		}
	}

	/**
	 * Invalidate the cached dashboard metrics.
	 */
	public function flush_trends(): void {
		delete_transient( 'wpca_dash_trends' );
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
	 * KPI tiles: label, count, stroke-icon name, destination URL, and a trend
	 * (sparkline series + delta) computed from cached metrics.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function stats(): array {
		$posts    = wp_count_posts( 'post' );
		$pages    = wp_count_posts( 'page' );
		$comments = wp_count_comments();
		$trends   = $this->trends();

		return array(
			array(
				'label' => __( 'Posts', 'wp-custom-admin' ),
				'count' => isset( $posts->publish ) ? (int) $posts->publish : 0,
				'icon'  => 'file-text',
				'url'   => admin_url( 'edit.php' ),
				'trend' => $trends['post'],
			),
			array(
				'label' => __( 'Pages', 'wp-custom-admin' ),
				'count' => isset( $pages->publish ) ? (int) $pages->publish : 0,
				'icon'  => 'files',
				'url'   => admin_url( 'edit.php?post_type=page' ),
				'trend' => $trends['page'],
			),
			array(
				'label' => __( 'Comments', 'wp-custom-admin' ),
				'count' => isset( $comments->approved ) ? (int) $comments->approved : 0,
				'icon'  => 'message',
				'url'   => admin_url( 'edit-comments.php' ),
				'trend' => $trends['comment'],
			),
			array(
				'label' => __( 'Users', 'wp-custom-admin' ),
				'count' => (int) ( count_users()['total_users'] ?? 0 ),
				'icon'  => 'users',
				'url'   => admin_url( 'users.php' ),
				'trend' => $trends['user'],
			),
		);
	}

	/**
	 * Cached 14-day daily series + 7-vs-7 delta for each metric.
	 *
	 * @return array<string,array{series:int[],pct:int,dir:string}>
	 */
	public function trends(): array {
		$cached = get_transient( 'wpca_dash_trends' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$days  = 14;
		$start = gmdate( 'Y-m-d 00:00:00', time() - ( $days - 1 ) * DAY_IN_SECONDS );

		$queries = array(
			'post'    => "SELECT DATE(post_date) d, COUNT(*) c FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_date >= %s GROUP BY DATE(post_date)",
			'page'    => "SELECT DATE(post_date) d, COUNT(*) c FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_date >= %s GROUP BY DATE(post_date)",
			'comment' => "SELECT DATE(comment_date) d, COUNT(*) c FROM {$wpdb->comments} WHERE comment_approved = '1' AND comment_date >= %s GROUP BY DATE(comment_date)",
			'user'    => "SELECT DATE(user_registered) d, COUNT(*) c FROM {$wpdb->users} WHERE user_registered >= %s GROUP BY DATE(user_registered)",
		);

		$out = array();
		foreach ( $queries as $key => $sql ) {
			$series        = $this->daily_series( $sql, $start, $days );
			$delta         = $this->delta( $series );
			$out[ $key ]   = array(
				'series' => $series,
				'pct'    => $delta['pct'],
				'dir'    => $delta['dir'],
			);
		}

		set_transient( 'wpca_dash_trends', $out, HOUR_IN_SECONDS );

		return $out;
	}

	/**
	 * Run a "count by day" query and fill missing days with zero.
	 *
	 * @param string $sql   Query with a single %s date placeholder.
	 * @param string $start Start datetime (gmdate).
	 * @param int    $days  Number of days in the series.
	 * @return int[]
	 */
	private function daily_series( string $sql, string $start, int $days ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table names are trusted, the date is prepared, and results are cached in a transient by the caller.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $start ), ARRAY_A );

		$by_day = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$by_day[ (string) $row['d'] ] = (int) $row['c'];
			}
		}

		$series = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day      = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			$series[] = $by_day[ $day ] ?? 0;
		}

		return $series;
	}

	/**
	 * Percentage change of the latter half of the series vs the former half.
	 *
	 * @param int[] $series Daily values.
	 * @return array{pct:int,dir:string}
	 */
	private function delta( array $series ): array {
		$count = count( $series );

		if ( $count < 2 ) {
			return array(
				'pct' => 0,
				'dir' => 'flat',
			);
		}

		$half = (int) floor( $count / 2 );
		$prev = array_sum( array_slice( $series, 0, $half ) );
		$curr = array_sum( array_slice( $series, $half ) );

		if ( 0 === $prev ) {
			$pct = $curr > 0 ? 100 : 0;
		} else {
			$pct = (int) round( ( ( $curr - $prev ) / $prev ) * 100 );
		}

		$dir = 'flat';
		if ( $curr > $prev ) {
			$dir = 'up';
		} elseif ( $curr < $prev ) {
			$dir = 'down';
		}

		return array(
			'pct' => $pct,
			'dir' => $dir,
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
				'icon'  => 'edit',
				'url'   => admin_url( 'post-new.php' ),
			);
		}

		if ( current_user_can( 'edit_pages' ) ) {
			$actions[] = array(
				'label' => __( 'New page', 'wp-custom-admin' ),
				'icon'  => 'file-plus',
				'url'   => admin_url( 'post-new.php?post_type=page' ),
			);
		}

		if ( current_user_can( 'upload_files' ) ) {
			$actions[] = array(
				'label' => __( 'Media library', 'wp-custom-admin' ),
				'icon'  => 'image',
				'url'   => admin_url( 'upload.php' ),
			);
		}

		if ( current_user_can( 'manage_options' ) ) {
			$actions[] = array(
				'label' => __( 'Brand settings', 'wp-custom-admin' ),
				'icon'  => 'palette',
				'url'   => admin_url( 'admin.php?page=wpca-settings' ),
			);
		}

		return $actions;
	}

	/**
	 * Recent activity: latest content across statuses, with author, status and a
	 * human-relative time — an activity feed rather than a plain link list.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function activity(): array {
		$posts = get_posts(
			array(
				'numberposts' => 6,
				'post_type'   => array( 'post', 'page' ),
				'post_status' => array( 'publish', 'future', 'draft', 'pending' ),
			)
		);

		$items = array();
		foreach ( $posts as $post ) {
			$items[] = array(
				'title'  => get_the_title( $post ),
				'url'    => (string) get_edit_post_link( $post->ID ),
				'author' => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
				'status' => (string) get_post_status( $post ),
				'ago'    => sprintf(
					/* translators: %s: human-readable time difference, e.g. "2 hours". */
					__( '%s ago', 'wp-custom-admin' ),
					human_time_diff( (int) get_post_timestamp( $post ) )
				),
			);
		}

		return $items;
	}
}
