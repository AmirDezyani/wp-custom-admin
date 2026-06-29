<?php
/**
 * Network (multisite) brand defaults page.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Admin;

use WPCustomAdmin\Support\Sanitizer;
use WPCustomAdmin\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * A Network Admin page that stores network-wide brand defaults (identity + palette).
 *
 * These values seed every sub-site through Settings' resolution order; a per-site
 * option still overrides them key-by-key, so a site can always re-brand itself.
 */
final class NetworkSettings {

	private const MENU_SLUG  = 'wpca-network-settings';
	private const CAPABILITY = 'manage_network_options';
	private const ACTION     = 'wpca_network_save';

	/**
	 * Keys the network admin manages. Everything else stays per-site.
	 */
	private const NETWORK_KEYS = array(
		'product_name',
		'logo_id',
		'primary_color',
		'primary_hover_color',
		'accent_color',
		'menu_bg_color',
		'menu_text_color',
		'menu_highlight_color',
		'adminbar_bg_color',
	);

	private Settings $settings;

	private string $hook_suffix = '';

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( 'network_admin_menu', array( $this, 'add_menu' ) );
		add_action( 'network_admin_edit_' . self::ACTION, array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_menu(): void {
		$this->hook_suffix = (string) add_submenu_page(
			'settings.php',
			__( 'WP Custom Admin', 'wp-custom-admin' ),
			__( 'WP Custom Admin', 'wp-custom-admin' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Reuse the settings page assets (color picker + media + page styles).
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_style( 'wpca-settings', WPCA_URL . 'assets/css/settings.css', array(), WPCA_VERSION );
		wp_enqueue_script(
			'wpca-settings',
			WPCA_URL . 'assets/js/settings.js',
			array( 'jquery', 'wp-color-picker', 'wp-i18n', 'jquery-ui-sortable' ),
			WPCA_VERSION,
			true
		);
		wp_set_script_translations( 'wpca-settings', 'wp-custom-admin' );
	}

	/**
	 * Render the network defaults form.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-custom-admin' ) );
		}

		$stored   = get_site_option( Settings::NETWORK_OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = Settings::defaults();
		$option   = Settings::OPTION;

		// Display-only flag set after a successful save.
		$updated = isset( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.

		$value = static function ( string $key ) use ( $stored, $defaults ) {
			return array_key_exists( $key, $stored ) ? $stored[ $key ] : ( $defaults[ $key ] ?? '' );
		};

		$colors = array(
			'primary_color'        => __( 'Primary', 'wp-custom-admin' ),
			'primary_hover_color'  => __( 'Primary (hover)', 'wp-custom-admin' ),
			'accent_color'         => __( 'Accent', 'wp-custom-admin' ),
			'menu_bg_color'        => __( 'Sidebar background', 'wp-custom-admin' ),
			'menu_text_color'      => __( 'Sidebar text', 'wp-custom-admin' ),
			'menu_highlight_color' => __( 'Sidebar active item', 'wp-custom-admin' ),
			'adminbar_bg_color'    => __( 'Top bar background', 'wp-custom-admin' ),
		);

		$logo_id  = absint( $value( 'logo_id' ) );
		$logo_url = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		$logo_url = is_string( $logo_url ) ? $logo_url : '';
		?>
		<div class="wrap wpca-settings-wrap">
			<h1><?php esc_html_e( 'WP Custom Admin', 'wp-custom-admin' ); ?></h1>
			<p class="wpca-help"><?php esc_html_e( 'Network-wide brand defaults. Individual sites can override these in their own settings.', 'wp-custom-admin' ); ?></p>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Network defaults saved.', 'wp-custom-admin' ); ?></p></div>
			<?php endif; ?>

			<form action="<?php echo esc_url( network_admin_url( 'edit.php?action=' . self::ACTION ) ); ?>" method="post">
				<?php wp_nonce_field( self::ACTION ); ?>

				<div class="wpca-card">
					<h2><?php esc_html_e( 'Branding', 'wp-custom-admin' ); ?></h2>
					<div class="wpca-field">
						<label for="wpca-net-product"><?php esc_html_e( 'Product name', 'wp-custom-admin' ); ?></label>
						<div class="wpca-control"><input type="text" id="wpca-net-product" name="<?php echo esc_attr( $option ); ?>[product_name]" value="<?php echo esc_attr( (string) $value( 'product_name' ) ); ?>" class="regular-text" /></div>
					</div>
					<div class="wpca-field">
						<label><?php esc_html_e( 'Admin logo', 'wp-custom-admin' ); ?></label>
						<div class="wpca-control wpca-media" data-key="logo_id">
							<div class="wpca-media-preview"><?php if ( '' !== $logo_url ) : ?><img src="<?php echo esc_url( $logo_url ); ?>" alt="" /><?php endif; ?></div>
							<input type="hidden" class="wpca-media-id" name="<?php echo esc_attr( $option ); ?>[logo_id]" value="<?php echo esc_attr( (string) $logo_id ); ?>" />
							<p class="wpca-media-actions">
								<button type="button" class="button wpca-media-select"><?php esc_html_e( 'Select image', 'wp-custom-admin' ); ?></button>
								<button type="button" class="button-link-delete wpca-media-remove"<?php echo '' === $logo_url ? ' style="display:none"' : ''; ?>><?php esc_html_e( 'Remove', 'wp-custom-admin' ); ?></button>
							</p>
						</div>
					</div>
				</div>

				<div class="wpca-card">
					<h2><?php esc_html_e( 'Color palette', 'wp-custom-admin' ); ?></h2>
					<?php foreach ( $colors as $key => $label ) : ?>
						<div class="wpca-field wpca-field--color">
							<label for="wpca-net-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
							<div class="wpca-control"><input type="text" id="wpca-net-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value( $key ) ); ?>" class="wpca-color" data-default-color="<?php echo esc_attr( (string) ( $defaults[ $key ] ?? '' ) ); ?>" /></div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="wpca-actions">
					<?php submit_button( __( 'Save changes', 'wp-custom-admin' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Validate and persist the network defaults (only the network-managed keys).
	 */
	public function handle_save(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-custom-admin' ) );
		}

		check_admin_referer( self::ACTION );

		$raw = isset( $_POST[ Settings::OPTION ] ) ? wp_unslash( $_POST[ Settings::OPTION ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via Sanitizer::sanitize() next.
		$full   = Sanitizer::sanitize( $raw );
		$subset = array_intersect_key( $full, array_flip( self::NETWORK_KEYS ) );

		update_site_option( Settings::NETWORK_OPTION, $subset );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::MENU_SLUG,
					'updated' => '1',
				),
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}
}
