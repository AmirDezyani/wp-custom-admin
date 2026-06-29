<?php
/**
 * Lightweight PSR-4 autoloader for the WPCustomAdmin namespace.
 *
 * Intentionally hand-written so the shipped plugin carries no Composer runtime
 * dependency (zero-build deployment). Maps WPCustomAdmin\Foo\Bar to src/Foo/Bar.php.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'WPCustomAdmin\\';
		$length = strlen( $prefix );

		if ( 0 !== strncmp( $prefix, $class_name, $length ) ) {
			return;
		}

		$relative = substr( $class_name, $length );
		$path     = WPCA_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_file( $path ) ) {
			require $path;
		}
	}
);
