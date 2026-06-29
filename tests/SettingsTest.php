<?php
/**
 * Tests for settings resolution and sparse storage (the key-by-key precedence).
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WPCustomAdmin\Support\Settings;

final class SettingsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// apply_filters returns the value (2nd arg, index 1) unchanged.
		Functions\when( 'apply_filters' )->returnArg( 1 );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'get_site_option' )->justReturn( array() );
		Functions\when( 'get_bloginfo' )->justReturn( 'Demo Site' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_sparse_drops_keys_equal_to_baseline(): void {
		$settings = new Settings();

		$this->assertSame( array(), $settings->sparse_for_storage( Settings::defaults() ) );
	}

	public function test_sparse_keeps_divergent_keys(): void {
		$settings = new Settings();
		$full     = Settings::defaults();

		$full['primary_color'] = '#000000';
		$full['menu_enabled']  = true;

		$sparse = $settings->sparse_for_storage( $full );

		$this->assertCount( 2, $sparse );
		$this->assertSame( '#000000', $sparse['primary_color'] );
		$this->assertTrue( $sparse['menu_enabled'] );
	}

	public function test_site_option_overrides_only_its_keys(): void {
		Functions\when( 'get_option' )->justReturn( array( 'primary_color' => '#abcdef' ) );

		$settings = new Settings();

		$this->assertSame( '#abcdef', $settings->get( 'primary_color' ) );
		$this->assertSame( Settings::defaults()['accent_color'], $settings->get( 'accent_color' ) );
	}

	public function test_multisite_precedence_default_network_site(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_site_option' )->justReturn( array( 'menu_bg_color' => '#111111' ) );
		Functions\when( 'get_option' )->justReturn( array( 'primary_color' => '#222222' ) );

		$settings = new Settings();

		$this->assertSame( '#222222', $settings->get( 'primary_color' ), 'per-site wins' );
		$this->assertSame( '#111111', $settings->get( 'menu_bg_color' ), 'network fills untouched keys' );
		$this->assertSame( Settings::defaults()['accent_color'], $settings->get( 'accent_color' ), 'default for the rest' );
	}

	public function test_product_name_falls_back_to_site_name(): void {
		$settings = new Settings();

		$this->assertSame( 'Demo Site', $settings->product_name() );
	}
}
