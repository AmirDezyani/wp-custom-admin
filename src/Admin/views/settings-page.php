<?php
/**
 * Settings page template.
 *
 * Included from SettingsPage::render(), so $this is the SettingsPage instance and
 * its private field_* helpers are available here.
 *
 * @package WPCustomAdmin
 * @var \WPCustomAdmin\Admin\SettingsPage $this
 */

declare( strict_types=1 );

use WPCustomAdmin\Support\Settings;

defined( 'ABSPATH' ) || exit;

$wpca_logo = $this->settings->logo_url( 'medium' );

// Import/export result notice (display-only flag).
$wpca_notice = isset( $_GET['wpca_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpca_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag, no state change.
?>
<div class="wrap wpca-settings-wrap">

	<?php if ( 'imported' === $wpca_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Brand settings imported successfully.', 'wp-custom-admin' ); ?></p></div>
	<?php elseif ( 'import_error' === $wpca_notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Import failed. Please upload a valid brand.json file.', 'wp-custom-admin' ); ?></p></div>
	<?php endif; ?>

	<?php settings_errors(); ?>

	<header class="wpca-header">
		<div class="wpca-header-brand">
			<img class="wpca-header-logo" src="<?php echo esc_url( $wpca_logo ); ?>" alt=""<?php echo '' === $wpca_logo ? ' style="display:none"' : ''; ?> />
			<span class="dashicons dashicons-admin-customizer wpca-header-icon"<?php echo '' !== $wpca_logo ? ' style="display:none"' : ''; ?>></span>
			<div>
				<h1><?php echo esc_html( $this->settings->product_name() ); ?></h1>
				<p class="wpca-header-sub"><?php esc_html_e( 'Brand control panel', 'wp-custom-admin' ); ?></p>
			</div>
		</div>
	</header>

	<nav class="wpca-tabs" role="tablist">
		<button type="button" class="wpca-tab is-active" data-target="branding"><?php esc_html_e( 'Branding', 'wp-custom-admin' ); ?></button>
		<button type="button" class="wpca-tab" data-target="colors"><?php esc_html_e( 'Colors', 'wp-custom-admin' ); ?></button>
		<button type="button" class="wpca-tab" data-target="login"><?php esc_html_e( 'Login', 'wp-custom-admin' ); ?></button>
		<button type="button" class="wpca-tab" data-target="menu"><?php esc_html_e( 'Menu', 'wp-custom-admin' ); ?></button>
		<button type="button" class="wpca-tab" data-target="whitelabel"><?php esc_html_e( 'White-label', 'wp-custom-admin' ); ?></button>
		<button type="button" class="wpca-tab" data-target="tools"><?php esc_html_e( 'Tools', 'wp-custom-admin' ); ?></button>
	</nav>

	<form action="options.php" method="post" class="wpca-form">
		<?php settings_fields( Settings::SETTINGS_GROUP ); ?>

		<!-- Branding -->
		<section class="wpca-panel is-active" data-tab="branding">
			<div class="wpca-card">
				<h2><?php esc_html_e( 'Branding', 'wp-custom-admin' ); ?></h2>
				<?php
				$this->field_toggle( 'branding_enabled', __( 'Enable the custom admin theme', 'wp-custom-admin' ), __( 'Master switch for the admin reskin and brand colors.', 'wp-custom-admin' ) );
				$this->field_text( 'product_name', __( 'Product name', 'wp-custom-admin' ), __( 'Shown in the menu, footer and login. Leave blank to use the site name.', 'wp-custom-admin' ) );
				$this->field_media( 'logo_id', __( 'Admin logo', 'wp-custom-admin' ), __( 'Displayed at the top of the admin sidebar.', 'wp-custom-admin' ) );
				$this->field_select(
					'font_family',
					__( 'Font', 'wp-custom-admin' ),
					array(
						'system'    => __( 'System default', 'wp-custom-admin' ),
						'inter'     => 'Inter',
						'georgia'   => 'Georgia (serif)',
						'monospace' => __( 'Monospace', 'wp-custom-admin' ),
					)
				);
				$this->field_toggle( 'dashboard_enabled', __( 'Custom Home dashboard', 'wp-custom-admin' ), __( 'Adds a branded Home screen in place of the default WordPress dashboard.', 'wp-custom-admin' ) );
				$this->field_toggle( 'dashboard_landing', __( 'Land on Home after login', 'wp-custom-admin' ), __( 'Redirect the default dashboard to the branded Home screen.', 'wp-custom-admin' ) );
				$this->field_select(
					'color_scheme',
					__( 'Color scheme', 'wp-custom-admin' ),
					array(
						'auto'  => __( 'Auto (match the system)', 'wp-custom-admin' ),
						'light' => __( 'Light', 'wp-custom-admin' ),
						'dark'  => __( 'Dark', 'wp-custom-admin' ),
					),
					__( 'Applies a dark theme to the admin content and the Home dashboard.', 'wp-custom-admin' )
				);
				?>
			</div>
		</section>

		<!-- Colors -->
		<section class="wpca-panel" data-tab="colors">
			<div class="wpca-card">
				<h2><?php esc_html_e( 'Color palette', 'wp-custom-admin' ); ?></h2>
				<?php
				$this->field_color( 'primary_color', __( 'Primary', 'wp-custom-admin' ), __( 'Buttons, links and active states.', 'wp-custom-admin' ) );
				$this->field_color( 'primary_hover_color', __( 'Primary (hover)', 'wp-custom-admin' ) );
				$this->field_color( 'accent_color', __( 'Accent', 'wp-custom-admin' ) );
				$this->field_color( 'menu_bg_color', __( 'Sidebar background', 'wp-custom-admin' ) );
				$this->field_color( 'menu_text_color', __( 'Sidebar text', 'wp-custom-admin' ) );
				$this->field_color( 'menu_highlight_color', __( 'Sidebar active item', 'wp-custom-admin' ) );
				$this->field_color( 'adminbar_bg_color', __( 'Top bar background', 'wp-custom-admin' ) );
				?>
			</div>
		</section>

		<!-- Login -->
		<section class="wpca-panel" data-tab="login">
			<div class="wpca-card">
				<h2><?php esc_html_e( 'Login page', 'wp-custom-admin' ); ?></h2>
				<?php
				$this->field_toggle( 'login_enabled', __( 'Brand the login screen', 'wp-custom-admin' ) );
				$this->field_media( 'login_logo_id', __( 'Login logo', 'wp-custom-admin' ), __( 'Falls back to the admin logo when empty.', 'wp-custom-admin' ) );
				$this->field_color( 'login_bg_color', __( 'Login background', 'wp-custom-admin' ) );
				$this->field_text( 'login_header_url', __( 'Logo link URL', 'wp-custom-admin' ), __( 'Where the login logo links to. Defaults to the site home.', 'wp-custom-admin' ), home_url( '/' ) );
				?>
			</div>
		</section>

		<!-- Menu -->
		<section class="wpca-panel" data-tab="menu">
			<div class="wpca-card">
				<h2><?php esc_html_e( 'Admin menu', 'wp-custom-admin' ); ?></h2>
				<?php
				$this->field_toggle( 'menu_enabled', __( 'Customize the admin menu', 'wp-custom-admin' ) );
				$this->field_text( 'full_access_capability', __( 'Full-access capability', 'wp-custom-admin' ), __( 'Users with this capability always see the complete menu. Default: manage_options.', 'wp-custom-admin' ), 'manage_options' );
				?>
				<p class="wpca-help"><?php esc_html_e( 'Renaming applies to everyone. Hiding applies only to users without the full-access capability. Hiding is cosmetic — it does not restrict direct URL access.', 'wp-custom-admin' ); ?></p>
				<?php $this->field_menu_editor(); ?>
			</div>
		</section>

		<!-- White-label -->
		<section class="wpca-panel" data-tab="whitelabel">
			<div class="wpca-card">
				<h2><?php esc_html_e( 'White-label', 'wp-custom-admin' ); ?></h2>
				<?php
				$this->field_toggle( 'whitelabel_enabled', __( 'Enable white-labeling', 'wp-custom-admin' ) );
				$this->field_toggle( 'hide_wp_logo', __( 'Remove the WordPress logo from the toolbar', 'wp-custom-admin' ) );
				$this->field_toggle( 'replace_howdy', __( 'Remove the "Howdy," greeting', 'wp-custom-admin' ) );
				$this->field_toggle( 'hide_welcome_panel', __( 'Hide the dashboard welcome panel', 'wp-custom-admin' ) );
				$this->field_toggle( 'hide_wp_news', __( 'Hide the WordPress events & news widget', 'wp-custom-admin' ) );
				$this->field_toggle( 'hide_dashboard_widgets', __( 'Hide the default dashboard widgets', 'wp-custom-admin' ) );
				$this->field_toggle( 'hide_version_footer', __( 'Hide the WordPress version in the footer', 'wp-custom-admin' ) );
				$this->field_toggle( 'hide_update_nag', __( 'Hide update notices from non-admins', 'wp-custom-admin' ), __( 'Users who can update core still see them.', 'wp-custom-admin' ) );
				$this->field_toggle( 'hide_screen_options', __( 'Hide Screen Options for non-admins', 'wp-custom-admin' ) );
				$this->field_text( 'footer_text', __( 'Footer text', 'wp-custom-admin' ), __( 'Replaces the WordPress footer credit. Basic links allowed. Blank uses the product name.', 'wp-custom-admin' ) );
				?>
			</div>
			<div class="wpca-card">
				<h2><?php esc_html_e( 'Data', 'wp-custom-admin' ); ?></h2>
				<?php $this->field_toggle( 'delete_on_uninstall', __( 'Delete all settings when the plugin is uninstalled', 'wp-custom-admin' ), __( 'Off by default, so branding survives a reinstall.', 'wp-custom-admin' ) ); ?>
			</div>
		</section>

		<div class="wpca-actions">
			<?php submit_button( __( 'Save changes', 'wp-custom-admin' ), 'primary', 'submit', false ); ?>
		</div>
	</form>

	<!-- Tools (outside the settings form: export/import use admin-post.php) -->
	<section class="wpca-panel" data-tab="tools">
		<div class="wpca-card">
			<h2><?php esc_html_e( 'Export', 'wp-custom-admin' ); ?></h2>
			<p class="wpca-help"><?php esc_html_e( 'Download the current brand configuration as a JSON file to reuse on another site.', 'wp-custom-admin' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="wpca_export" />
				<?php wp_nonce_field( 'wpca_export' ); ?>
				<?php submit_button( __( 'Export brand.json', 'wp-custom-admin' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<div class="wpca-card">
			<h2><?php esc_html_e( 'Import', 'wp-custom-admin' ); ?></h2>
			<p class="wpca-help"><?php esc_html_e( 'Upload a brand.json file. Imported values are validated before they are saved.', 'wp-custom-admin' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="wpca_import" />
				<?php wp_nonce_field( 'wpca_import' ); ?>
				<input type="file" name="wpca_import_file" accept="application/json,.json" required />
				<?php submit_button( __( 'Import', 'wp-custom-admin' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
	</section>

</div>
