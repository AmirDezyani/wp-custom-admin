<?php
/**
 * PHPUnit bootstrap. Defines the plugin constants and loads the autoloaders so
 * the (namespaced) classes can be unit-tested with Brain Monkey stubbing the
 * WordPress functions they call. No WordPress install is required.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCA_VERSION', '0.6.0' );
define( 'WPCA_PATH', dirname( __DIR__ ) . '/' );
define( 'WPCA_URL', 'http://example.test/wp-content/plugins/wp-custom-admin/' );
define( 'WPCA_BASENAME', 'wp-custom-admin/wp-custom-admin.php' );

require dirname( __DIR__ ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/src/autoload.php';
