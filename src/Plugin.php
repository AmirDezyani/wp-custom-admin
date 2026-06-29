<?php
/**
 * Plugin container.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin;

use WPCustomAdmin\Admin\NetworkSettings;
use WPCustomAdmin\Admin\SettingsPage;
use WPCustomAdmin\Contracts\Module;
use WPCustomAdmin\Modules\Branding\BrandingModule;
use WPCustomAdmin\Modules\Dashboard\DashboardModule;
use WPCustomAdmin\Modules\Login\LoginModule;
use WPCustomAdmin\Modules\Menu\MenuModule;
use WPCustomAdmin\Modules\WhiteLabel\WhiteLabelModule;
use WPCustomAdmin\Support\Assets;
use WPCustomAdmin\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The single plugin singleton, acting as a minimal service container.
 *
 * Builds the shared Settings and Assets services once and injects them into
 * each feature module. Only enabled modules are registered.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private Settings $settings;

	private Assets $assets;

	private bool $booted = false;

	/**
	 * Retrieve the shared instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Build the shared services. No hooks are registered here.
	 */
	private function __construct() {
		$this->settings = new Settings();
		$this->assets   = new Assets( $this->settings );
	}

	public function settings(): Settings {
		return $this->settings;
	}

	public function assets(): Assets {
		return $this->assets;
	}

	/**
	 * Wire up WordPress: load translations, the settings screen, and enabled modules.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// The settings screen is always available to capable users in wp-admin.
		if ( is_admin() ) {
			( new SettingsPage( $this->settings ) )->register();

			// Network-wide brand defaults live in Network Admin on multisite.
			if ( is_multisite() ) {
				( new NetworkSettings( $this->settings ) )->register();
			}
		}

		$config = $this->settings->all();

		foreach ( $this->modules() as $module ) {
			if ( $module->is_enabled( $config ) ) {
				$module->register();
			}
		}
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'wp-custom-admin', false, dirname( WPCA_BASENAME ) . '/languages' );
	}

	/**
	 * The full set of feature modules, in boot order.
	 *
	 * @return Module[]
	 */
	private function modules(): array {
		return array(
			new BrandingModule( $this->settings, $this->assets ),
			new DashboardModule( $this->settings, $this->assets ),
			new LoginModule( $this->settings, $this->assets ),
			new MenuModule( $this->settings, $this->assets ),
			new WhiteLabelModule( $this->settings, $this->assets ),
		);
	}
}
