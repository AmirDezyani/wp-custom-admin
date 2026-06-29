<?php
/**
 * Activation / deactivation lifecycle.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin;

use WPCustomAdmin\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle handlers. Deactivation is intentionally non-destructive — branding
 * data is only ever removed by uninstall.php, behind an explicit opt-in.
 */
final class Activation {

	/**
	 * Seed defaults on first activation and record the settings version.
	 */
	public static function activate(): void {
		if ( false === get_option( 'wpca_settings' ) ) {
			add_option( 'wpca_settings', Settings::defaults() );
		}

		update_option( 'wpca_db_version', WPCA_VERSION );
	}

	/**
	 * Non-destructive teardown. Clears only transient/cron state (none yet),
	 * never the stored branding configuration.
	 */
	public static function deactivate(): void {
		// Placeholder for clearing plugin cron events / transients if introduced later.
		// Stored options are deliberately preserved across deactivation.
	}
}
