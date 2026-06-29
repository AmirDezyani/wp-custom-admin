<?php
/**
 * Activation / deactivation lifecycle.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin;

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle handlers. Deactivation is intentionally non-destructive — branding
 * data is only ever removed by uninstall.php, behind an explicit opt-in.
 */
final class Activation {

	/**
	 * Record the settings version on first activation.
	 *
	 * The option is seeded EMPTY (not with full defaults): Settings::all() merges
	 * defaults at read time, so the plugin is branded immediately, and an empty
	 * per-site option means a fresh site inherits any network/default values
	 * key-by-key instead of pinning a full snapshot.
	 */
	public static function activate(): void {
		if ( false === get_option( 'wpca_settings' ) ) {
			add_option( 'wpca_settings', array() );
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
