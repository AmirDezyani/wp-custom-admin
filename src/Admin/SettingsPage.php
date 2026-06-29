<?php
/**
 * Branded settings page: menu, Settings API wiring, field helpers, import/export.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Admin;

use WPCustomAdmin\Support\Sanitizer;
use WPCustomAdmin\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the top-level settings screen and handles its save/import/export flows.
 *
 * The visible fields all bind into the single wpca_settings option via array-style
 * name attributes, so core's options.php pipeline handles the nonce, option
 * whitelist, and "Settings saved" notice, while the Sanitizer is the one gate.
 */
final class SettingsPage {

	private const MENU_SLUG        = 'wpca-settings';
	private const PAGE_HOOK        = 'toplevel_page_wpca-settings';
	private const CAPABILITY       = 'manage_options';
	private const MAX_IMPORT_BYTES = 262144;

	/**
	 * Maps a color setting key to the CSS custom property it drives (for live preview).
	 */
	private const COLOR_VARS = array(
		'primary_color'        => '--wpca-primary',
		'primary_hover_color'  => '--wpca-primary-hover',
		'accent_color'         => '--wpca-accent',
		'menu_bg_color'        => '--wpca-menu-bg',
		'menu_text_color'      => '--wpca-menu-text',
		'menu_highlight_color' => '--wpca-menu-highlight',
		'adminbar_bg_color'    => '--wpca-adminbar-bg',
		'login_bg_color'       => '--wpca-login-bg',
	);

	private Settings $settings;

	private string $hook_suffix = '';

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register all admin hooks for the settings screen.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_wpca_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_wpca_import', array( $this, 'handle_import' ) );
	}

	/**
	 * Add the branded top-level menu.
	 */
	public function add_menu(): void {
		$this->hook_suffix = (string) add_menu_page(
			$this->settings->product_name(),
			$this->settings->product_name(),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-admin-customizer',
			2
		);
	}

	/**
	 * Register the single option with the one sanitize callback.
	 */
	public function register_setting(): void {
		register_setting(
			Settings::SETTINGS_GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_option' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize callback for the per-site option: validate, then store only the
	 * keys that diverge from the baseline (so network/default values keep flowing).
	 *
	 * @param mixed $input Raw posted value.
	 * @return array<string,mixed>
	 */
	public function sanitize_option( mixed $input ): array {
		return $this->settings->sparse_for_storage( Sanitizer::sanitize( $input ) );
	}

	/**
	 * Enqueue the color picker, media frame, and page assets — only on our page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( self::PAGE_HOOK !== $hook_suffix ) {
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
	 * Render the settings screen.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-custom-admin' ) );
		}

		require WPCA_PATH . 'src/Admin/views/settings-page.php';
	}

	/**
	 * Stream the resolved settings as a downloadable brand.json file.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-custom-admin' ) );
		}

		check_admin_referer( 'wpca_export' );

		$payload  = wp_json_encode( $this->settings->all(), JSON_PRETTY_PRINT );
		$filename = 'wpca-brand-' . gmdate( 'Ymd-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		exit;
	}

	/**
	 * Validate and import an uploaded brand.json file.
	 */
	public function handle_import(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-custom-admin' ) );
		}

		check_admin_referer( 'wpca_import' );

		$notice = 'import_error';
		$size   = isset( $_FILES['wpca_import_file']['size'] ) ? (int) $_FILES['wpca_import_file']['size'] : 0;

		if ( isset( $_FILES['wpca_import_file']['tmp_name'], $_FILES['wpca_import_file']['error'] )
			&& UPLOAD_ERR_OK === (int) $_FILES['wpca_import_file']['error']
			&& $size > 0 && $size <= self::MAX_IMPORT_BYTES
		) {
			// tmp_name is a server-generated filesystem path, not user text: use it raw
			// (unslashing it would break Windows paths) — is_uploaded_file() is the gate.
			$tmp = $_FILES['wpca_import_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- server path, validated by is_uploaded_file() below.

			if ( is_uploaded_file( $tmp ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local upload temp file.
				$raw     = (string) file_get_contents( $tmp );
				$decoded = json_decode( $raw, true );

				if ( is_array( $decoded ) ) {
					// Imported data passes through the SAME gate as the settings form,
					// then stored sparsely so it overrides only what it specifies.
					update_option( Settings::OPTION, $this->settings->sparse_for_storage( Sanitizer::sanitize( $decoded ) ) );
					$this->settings->flush();
					$notice = 'imported';
				}
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'wpca_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/* --------------------------------------------------------------- helpers -- */

	/**
	 * Current value for a setting key.
	 */
	private function value( string $key, mixed $default = '' ): mixed {
		return $this->settings->get( $key, $default );
	}

	/**
	 * Optional help paragraph markup.
	 */
	private function help( string $text ): string {
		return '' === $text ? '' : '<p class="wpca-help">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Render a text input bound to a setting key.
	 */
	private function field_text( string $key, string $label, string $help = '', string $placeholder = '' ): void {
		printf(
			'<div class="wpca-field"><label for="wpca-%1$s">%2$s</label><div class="wpca-control"><input type="text" id="wpca-%1$s" name="%3$s[%1$s]" value="%4$s" placeholder="%5$s" class="regular-text" />%6$s</div></div>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( Settings::OPTION ),
			esc_attr( (string) $this->value( $key ) ),
			esc_attr( $placeholder ),
			$this->help( $help ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in help().
		);
	}

	/**
	 * Render a textarea bound to a setting key.
	 */
	private function field_textarea( string $key, string $label, string $help = '' ): void {
		printf(
			'<div class="wpca-field"><label for="wpca-%1$s">%2$s</label><div class="wpca-control"><textarea id="wpca-%1$s" name="%3$s[%1$s]" rows="3" class="large-text code">%4$s</textarea>%5$s</div></div>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( Settings::OPTION ),
			esc_textarea( (string) $this->value( $key ) ),
			$this->help( $help ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in help().
		);
	}

	/**
	 * Render a color input enhanced by wp-color-picker.
	 */
	private function field_color( string $key, string $label, string $help = '' ): void {
		$defaults = Settings::defaults();

		printf(
			'<div class="wpca-field wpca-field--color"><label for="wpca-%1$s">%2$s</label><div class="wpca-control"><input type="text" id="wpca-%1$s" name="%3$s[%1$s]" value="%4$s" class="wpca-color" data-default-color="%5$s" data-css-var="%7$s" />%6$s</div></div>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( Settings::OPTION ),
			esc_attr( (string) $this->value( $key ) ),
			esc_attr( (string) ( $defaults[ $key ] ?? '' ) ),
			$this->help( $help ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in help().
			esc_attr( self::COLOR_VARS[ $key ] ?? '' )
		);
	}

	/**
	 * Render a toggle (checkbox) bound to a boolean setting key.
	 */
	private function field_toggle( string $key, string $label, string $help = '' ): void {
		printf(
			'<div class="wpca-field wpca-field--toggle"><label class="wpca-switch"><input type="checkbox" name="%3$s[%1$s]" value="1" %4$s /><span class="wpca-switch-track"></span></label><div class="wpca-toggle-text"><span class="wpca-toggle-label">%2$s</span>%5$s</div></div>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( Settings::OPTION ),
			checked( (bool) $this->value( $key, false ), true, false ),
			$this->help( $help ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in help().
		);
	}

	/**
	 * Render a select bound to a setting key.
	 *
	 * @param array<string,string> $options value => label.
	 */
	private function field_select( string $key, string $label, array $options, string $help = '' ): void {
		$current = (string) $this->value( $key );
		$markup  = '';

		foreach ( $options as $option_value => $option_label ) {
			$markup .= sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $option_value ),
				selected( $current, (string) $option_value, false ),
				esc_html( (string) $option_label )
			);
		}

		printf(
			'<div class="wpca-field"><label for="wpca-%1$s">%2$s</label><div class="wpca-control"><select id="wpca-%1$s" name="%3$s[%1$s]">%4$s</select>%5$s</div></div>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( Settings::OPTION ),
			$markup, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- options escaped above.
			$this->help( $help ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in help().
		);
	}

	/**
	 * Render a Media Library image picker storing only the attachment id.
	 */
	private function field_media( string $key, string $label, string $help = '' ): void {
		$attachment_id = absint( $this->value( $key, 0 ) );
		$preview       = $attachment_id > 0 ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		$preview       = is_string( $preview ) ? $preview : '';

		echo '<div class="wpca-field"><label>' . esc_html( $label ) . '</label>';
		echo '<div class="wpca-control wpca-media" data-key="' . esc_attr( $key ) . '">';
		echo '<div class="wpca-media-preview">';

		if ( '' !== $preview ) {
			echo '<img src="' . esc_url( $preview ) . '" alt="" />';
		}

		echo '</div>';
		echo '<input type="hidden" class="wpca-media-id" name="' . esc_attr( Settings::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $attachment_id ) . '" />';
		echo '<p class="wpca-media-actions">';
		echo '<button type="button" class="button wpca-media-select">' . esc_html__( 'Select image', 'wp-custom-admin' ) . '</button> ';
		echo '<button type="button" class="button-link-delete wpca-media-remove"' . ( '' === $preview ? ' style="display:none"' : '' ) . '>' . esc_html__( 'Remove', 'wp-custom-admin' ) . '</button>';
		echo '</p>';
		echo $this->help( $help ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in help().
		echo '</div></div>';
	}

	/**
	 * Render the live top-level menu list with hide + rename controls.
	 */
	private function field_menu_editor(): void {
		global $menu;

		$hidden  = (array) $this->value( 'menu_hidden', array() );
		$renames = (array) $this->value( 'menu_renames', array() );

		if ( ! is_array( $menu ) || array() === $menu ) {
			echo '<p class="wpca-help">' . esc_html__( 'No admin menu items were detected.', 'wp-custom-admin' ) . '</p>';

			return;
		}

		echo '<p class="wpca-help">' . esc_html__( 'Drag rows to reorder the menu. The chosen order is applied to everyone; newly added plugin menus appear after ordered items.', 'wp-custom-admin' ) . '</p>';

		echo '<table class="wpca-menu-table widefat striped"><thead><tr>';
		echo '<th class="wpca-menu-handle-col"><span class="screen-reader-text">' . esc_html__( 'Reorder', 'wp-custom-admin' ) . '</span></th>';
		echo '<th>' . esc_html__( 'Menu item', 'wp-custom-admin' ) . '</th>';
		echo '<th>' . esc_html__( 'Hide (non-admins)', 'wp-custom-admin' ) . '</th>';
		echo '<th>' . esc_html__( 'Rename to', 'wp-custom-admin' ) . '</th>';
		echo '</tr></thead><tbody class="wpca-menu-sortable">';

		foreach ( $menu as $item ) {
			if ( ! isset( $item[0], $item[2] ) ) {
				continue;
			}

			$slug  = (string) $item[2];
			$label = trim( wp_strip_all_tags( (string) $item[0] ) );

			// Skip separators / empty rows.
			if ( '' === $label || str_starts_with( $slug, 'separator' ) ) {
				continue;
			}

			printf(
				'<tr>'
				. '<td class="wpca-menu-handle"><span class="dashicons dashicons-menu" aria-hidden="true"></span>'
				. '<input type="hidden" class="wpca-menu-order-input" name="%3$s[menu_order][]" value="%4$s" /></td>'
				. '<td><span class="wpca-menu-name">%1$s</span><code>%2$s</code></td>'
				. '<td><input type="checkbox" name="%3$s[menu_hidden][]" value="%4$s" %5$s /></td>'
				. '<td><input type="text" name="%3$s[menu_renames][%4$s]" value="%6$s" placeholder="%1$s" class="regular-text" /></td></tr>',
				esc_html( $label ),
				esc_html( $slug ),
				esc_attr( Settings::OPTION ),
				esc_attr( $slug ),
				checked( in_array( $slug, $hidden, true ), true, false ),
				esc_attr( (string) ( $renames[ $slug ] ?? '' ) )
			);
		}

		echo '</tbody></table>';
	}
}
