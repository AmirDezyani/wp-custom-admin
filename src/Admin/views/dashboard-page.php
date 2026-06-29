<?php
/**
 * Branded "Home" dashboard template.
 *
 * Included from DashboardModule::render(); $this is the DashboardModule instance.
 *
 * @package WPCustomAdmin
 * @var \WPCustomAdmin\Modules\Dashboard\DashboardModule $this
 */

declare( strict_types=1 );

use WPCustomAdmin\Support\Icon;
use WPCustomAdmin\Support\Spark;

defined( 'ABSPATH' ) || exit;

$wpca_user    = wp_get_current_user();
$wpca_name    = $wpca_user instanceof WP_User ? $wpca_user->display_name : '';
$wpca_product = $this->settings->product_name();
$wpca_logo    = $this->settings->logo_url( 'medium' );

$wpca_status_map = array(
	'publish' => array(
		'label' => __( 'Published', 'wp-custom-admin' ),
		'tone'  => 'pos',
	),
	'future'  => array(
		'label' => __( 'Scheduled', 'wp-custom-admin' ),
		'tone'  => 'info',
	),
	'draft'   => array(
		'label' => __( 'Draft', 'wp-custom-admin' ),
		'tone'  => 'muted',
	),
	'pending' => array(
		'label' => __( 'Pending', 'wp-custom-admin' ),
		'tone'  => 'warn',
	),
);
?>
<div class="wrap wpca-dashboard">

	<header class="wpca-hero">
		<div class="wpca-hero-text">
			<p class="wpca-hero-eyebrow"><?php echo esc_html( date_i18n( (string) get_option( 'date_format' ) ) ); ?></p>
			<?php if ( '' !== $wpca_name ) : ?>
				<h1>
					<?php
					/* translators: %s: user display name. */
					printf( esc_html__( 'Welcome back, %s', 'wp-custom-admin' ), esc_html( $wpca_name ) );
					?>
				</h1>
			<?php endif; ?>
			<p class="wpca-hero-sub">
				<?php
				/* translators: %s: product / site name. */
				printf( esc_html__( "Here's what's happening on %s today.", 'wp-custom-admin' ), '<strong>' . esc_html( $wpca_product ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name escaped inline.
				?>
			</p>
		</div>
		<?php if ( '' !== $wpca_logo ) : ?>
			<img class="wpca-hero-logo" src="<?php echo esc_url( $wpca_logo ); ?>" alt="" />
		<?php else : ?>
			<a class="wpca-hero-cta" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">
				<?php echo Icon::svg( 'external', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?>
				<?php esc_html_e( 'View site', 'wp-custom-admin' ); ?>
			</a>
		<?php endif; ?>
	</header>

	<section class="wpca-kpis">
		<?php foreach ( $this->stats() as $wpca_stat ) : ?>
			<?php
			$wpca_trend    = isset( $wpca_stat['trend'] ) && is_array( $wpca_stat['trend'] ) ? $wpca_stat['trend'] : array();
			$wpca_series   = isset( $wpca_trend['series'] ) ? (array) $wpca_trend['series'] : array();
			$wpca_has_data = array_sum( array_map( 'intval', $wpca_series ) ) > 0;
			$wpca_dir      = isset( $wpca_trend['dir'] ) ? (string) $wpca_trend['dir'] : 'flat';
			$wpca_pct      = isset( $wpca_trend['pct'] ) ? (int) $wpca_trend['pct'] : 0;
			?>
			<a class="wpca-kpi" href="<?php echo esc_url( (string) $wpca_stat['url'] ); ?>">
				<div class="wpca-kpi-top">
					<span class="wpca-kpi-chip"><?php echo Icon::svg( (string) $wpca_stat['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<?php if ( $wpca_has_data && 'flat' !== $wpca_dir ) : ?>
						<span class="wpca-delta is-<?php echo esc_attr( $wpca_dir ); ?>">
							<?php echo Icon::svg( 'up' === $wpca_dir ? 'trending-up' : 'trending-down', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?>
							<?php echo esc_html( (string) abs( $wpca_pct ) ); ?>%
						</span>
					<?php endif; ?>
				</div>
				<div class="wpca-kpi-num"><?php echo esc_html( number_format_i18n( (int) $wpca_stat['count'] ) ); ?></div>
				<div class="wpca-kpi-label"><?php echo esc_html( (string) $wpca_stat['label'] ); ?></div>
				<?php if ( $wpca_has_data ) : ?>
					<div class="wpca-kpi-spark"><?php echo Spark::svg( $wpca_series ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- numeric inline SVG. ?></div>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</section>

	<div class="wpca-grid">

		<section class="wpca-panel wpca-activity">
			<h2><?php esc_html_e( 'Recent activity', 'wp-custom-admin' ); ?></h2>
			<?php $wpca_items = $this->activity(); ?>
			<?php if ( empty( $wpca_items ) ) : ?>
				<div class="wpca-empty">
					<span class="wpca-empty-icon"><?php echo Icon::svg( 'inbox', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?></span>
					<p class="wpca-empty-title"><?php esc_html_e( 'Nothing here yet', 'wp-custom-admin' ); ?></p>
					<p class="wpca-empty-sub"><?php esc_html_e( 'Your latest posts and pages will show up here.', 'wp-custom-admin' ); ?></p>
					<?php if ( current_user_can( 'edit_posts' ) ) : ?>
						<a class="wpca-empty-cta" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'Write your first post', 'wp-custom-admin' ); ?></a>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<ul class="wpca-feed">
					<?php foreach ( $wpca_items as $wpca_item ) : ?>
						<?php
						$wpca_st   = $wpca_status_map[ $wpca_item['status'] ] ?? array(
							'label' => $wpca_item['status'],
							'tone'  => 'muted',
						);
						$wpca_init = strtoupper( mb_substr( trim( (string) $wpca_item['author'] ), 0, 1 ) );
						?>
						<li>
							<a href="<?php echo esc_url( (string) $wpca_item['url'] ); ?>">
								<span class="wpca-av"><?php echo esc_html( $wpca_init ); ?></span>
								<span class="wpca-feed-title"><?php echo esc_html( (string) $wpca_item['title'] ); ?></span>
								<span class="wpca-chip is-<?php echo esc_attr( $wpca_st['tone'] ); ?>"><?php echo esc_html( (string) $wpca_st['label'] ); ?></span>
								<span class="wpca-feed-time"><?php echo esc_html( (string) $wpca_item['ago'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<section class="wpca-panel wpca-actions">
			<h2><?php esc_html_e( 'Quick actions', 'wp-custom-admin' ); ?></h2>
			<div class="wpca-action-list">
				<?php foreach ( $this->quick_actions() as $wpca_action ) : ?>
					<a class="wpca-action" href="<?php echo esc_url( (string) $wpca_action['url'] ); ?>">
						<?php echo Icon::svg( (string) $wpca_action['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG. ?>
						<?php echo esc_html( (string) $wpca_action['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</section>

	</div>
</div>
