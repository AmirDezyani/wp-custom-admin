<?php
/**
 * Branding module: applies the admin reskin and brand tokens.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Modules\Branding;

use WPCustomAdmin\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the admin stylesheet and the per-site CSS custom properties, and adds the
 * wpca-admin body class that scopes every rule.
 */
final class BrandingModule extends AbstractModule {

	public function id(): string {
		return 'branding';
	}

	public function is_enabled( array $settings ): bool {
		return ! empty( $settings['branding_enabled'] );
	}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue_admin' ) );
		add_filter( 'admin_body_class', array( $this->assets, 'body_class' ) );
	}
}
