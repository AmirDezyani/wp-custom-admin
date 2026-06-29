<?php
/**
 * Login module: brands the wp-login.php screen.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Modules\Login;

use WPCustomAdmin\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the login stylesheet/tokens and repoints the logo link and text from
 * wordpress.org to the client site.
 */
final class LoginModule extends AbstractModule {

	public function id(): string {
		return 'login';
	}

	public function is_enabled( array $settings ): bool {
		return ! empty( $settings['login_enabled'] );
	}

	public function register(): void {
		add_action( 'login_enqueue_scripts', array( $this->assets, 'enqueue_login' ) );
		add_filter( 'login_headerurl', array( $this, 'header_url' ) );
		add_filter( 'login_headertext', array( $this, 'header_text' ) );
	}

	/**
	 * Destination for the login logo link (defaults to the site home).
	 */
	public function header_url(): string {
		$url = trim( (string) $this->settings->get( 'login_header_url', '' ) );

		return '' !== $url ? $url : home_url( '/' );
	}

	/**
	 * Accessible text/title for the login logo.
	 */
	public function header_text(): string {
		return $this->settings->product_name();
	}
}
