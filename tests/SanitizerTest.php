<?php
/**
 * Tests for the single settings sanitizer.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WPCustomAdmin\Support\Sanitizer;
use WPCustomAdmin\Support\Settings;

final class SanitizerTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_text_field' )->alias( static fn( $v ) => is_string( $v ) ? trim( strip_tags( $v ) ) : '' );
		Functions\when( 'sanitize_key' )->alias( static fn( $v ) => strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $v ) ) );
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		Functions\when( 'esc_url_raw' )->alias( static fn( $v ) => (string) $v );
		Functions\when( 'wp_kses' )->alias( static fn( $v, $allowed = array() ) => (string) $v );
		Functions\when( 'sanitize_hex_color' )->alias(
			static fn( $c ) => is_string( $c ) && preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $c ) ? $c : ''
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_only_known_keys_are_returned(): void {
		$out = Sanitizer::sanitize(
			array(
				'product_name' => 'Acme',
				'evil_key'     => '<script>',
			)
		);

		$this->assertArrayNotHasKey( 'evil_key', $out );
		$this->assertSame( 'Acme', $out['product_name'] );

		foreach ( array_keys( Settings::defaults() ) as $key ) {
			$this->assertArrayHasKey( $key, $out );
		}
	}

	public function test_absent_booleans_become_false(): void {
		$out = Sanitizer::sanitize( array() );

		$this->assertFalse( $out['branding_enabled'] );
		$this->assertFalse( $out['delete_on_uninstall'] );
	}

	public function test_checked_boolean_is_true(): void {
		$out = Sanitizer::sanitize( array( 'branding_enabled' => '1' ) );

		$this->assertTrue( $out['branding_enabled'] );
	}

	public function test_valid_color_kept_invalid_falls_back(): void {
		$defaults = Settings::defaults();
		$out      = Sanitizer::sanitize(
			array(
				'primary_color' => '#123abc',
				'accent_color'  => 'not-a-color',
			)
		);

		$this->assertSame( '#123abc', $out['primary_color'] );
		$this->assertSame( $defaults['accent_color'], $out['accent_color'] );
	}

	public function test_logo_id_is_absint(): void {
		$this->assertSame( 42, Sanitizer::sanitize( array( 'logo_id' => '42abc' ) )['logo_id'] );
	}

	public function test_menu_hidden_from_array_and_string(): void {
		$from_array = Sanitizer::sanitize( array( 'menu_hidden' => array( 'edit.php', 'edit.php', 'tools.php' ) ) );
		$this->assertSame( array( 'edit.php', 'tools.php' ), $from_array['menu_hidden'] );

		$from_string = Sanitizer::sanitize( array( 'menu_hidden' => "edit.php\ntools.php" ) );
		$this->assertSame( array( 'edit.php', 'tools.php' ), $from_string['menu_hidden'] );
	}

	public function test_font_family_allowlist(): void {
		$this->assertSame( 'inter', Sanitizer::sanitize( array( 'font_family' => 'inter' ) )['font_family'] );
		$this->assertSame( 'system', Sanitizer::sanitize( array( 'font_family' => 'comic-sans' ) )['font_family'] );
	}
}
