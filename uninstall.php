<?php
/**
 * Uninstall handler.
 *
 * Runs in isolation: the plugin's own code is NOT loaded here, so everything is
 * inlined and guarded. Data is removed only when the user opted in via the
 * "Remove all data on uninstall" setting.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

// Exit unless WordPress is performing an uninstall.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$wpca_settings = get_option( 'wpca_settings' );

if ( ! is_array( $wpca_settings ) || empty( $wpca_settings['delete_on_uninstall'] ) ) {
	return;
}

if ( is_multisite() ) {
	$wpca_site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $wpca_site_ids as $wpca_site_id ) {
		switch_to_blog( (int) $wpca_site_id );
		delete_option( 'wpca_settings' );
		delete_option( 'wpca_db_version' );
		restore_current_blog();
	}

	delete_site_option( 'wpca_network_settings' );
} else {
	delete_option( 'wpca_settings' );
	delete_option( 'wpca_db_version' );
}
