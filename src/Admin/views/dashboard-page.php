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

defined( 'ABSPATH' ) || exit;

$wpca_user    = wp_get_current_user();
$wpca_name    = $wpca_user instanceof WP_User ? $wpca_user->display_name : '';
$wpca_product = $this->settings->product_name();
$wpca_logo    = $this->settings->logo_url( 'medium' );
?>
<div class="wrap wpca-dashboard">

	<header class="wpca-dash-hero">
		<div class="wpca-dash-hero-text">
			<?php if ( '' !== $wpca_name ) : ?>
				<p class="wpca-dash-eyebrow"><?php echo esc_html( date_i18n( (string) get_option( 'date_format' ) ) ); ?></p>
				<h1>
					<?php
					/* translators: %s: user display name. */
					printf( esc_html__( 'Welcome back, %s', 'wp-custom-admin' ), esc_html( $wpca_name ) );
					?>
				</h1>
			<?php endif; ?>
			<p class="wpca-dash-sub">
				<?php
				/* translators: %s: product / site name. */
				printf( esc_html__( "Here's what's happening on %s today.", 'wp-custom-admin' ), '<strong>' . esc_html( $wpca_product ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name is escaped inline.
				?>
			</p>
		</div>
		<?php if ( '' !== $wpca_logo ) : ?>
			<img class="wpca-dash-hero-logo" src="<?php echo esc_url( $wpca_logo ); ?>" alt="" />
		<?php else : ?>
			<a class="wpca-dash-hero-view" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">
				<span class="dashicons dashicons-external"></span><?php esc_html_e( 'View site', 'wp-custom-admin' ); ?>
			</a>
		<?php endif; ?>
	</header>

	<section class="wpca-dash-stats">
		<?php foreach ( $this->stats() as $wpca_stat ) : ?>
			<a class="wpca-stat-card" href="<?php echo esc_url( (string) $wpca_stat['url'] ); ?>">
				<span class="wpca-stat-icon dashicons <?php echo esc_attr( (string) $wpca_stat['icon'] ); ?>"></span>
				<span class="wpca-stat-count"><?php echo esc_html( number_format_i18n( (int) $wpca_stat['count'] ) ); ?></span>
				<span class="wpca-stat-label"><?php echo esc_html( (string) $wpca_stat['label'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</section>

	<div class="wpca-dash-grid">

		<section class="wpca-card wpca-dash-actions">
			<h2><?php esc_html_e( 'Quick actions', 'wp-custom-admin' ); ?></h2>
			<div class="wpca-action-grid">
				<?php foreach ( $this->quick_actions() as $wpca_action ) : ?>
					<a class="wpca-action" href="<?php echo esc_url( (string) $wpca_action['url'] ); ?>">
						<span class="dashicons <?php echo esc_attr( (string) $wpca_action['icon'] ); ?>"></span>
						<?php echo esc_html( (string) $wpca_action['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="wpca-card wpca-dash-recent">
			<h2><?php esc_html_e( 'Recent posts', 'wp-custom-admin' ); ?></h2>
			<?php $wpca_recent = $this->recent_posts(); ?>
			<?php if ( empty( $wpca_recent ) ) : ?>
				<p class="wpca-empty"><?php esc_html_e( 'Nothing published yet.', 'wp-custom-admin' ); ?></p>
			<?php else : ?>
				<ul class="wpca-recent-list">
					<?php foreach ( $wpca_recent as $wpca_post ) : ?>
						<li>
							<a href="<?php echo esc_url( (string) get_edit_post_link( $wpca_post->ID ) ); ?>">
								<span class="wpca-recent-title"><?php echo esc_html( get_the_title( $wpca_post ) ); ?></span>
								<span class="wpca-recent-date"><?php echo esc_html( get_the_date( '', $wpca_post ) ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

	</div>
</div>
