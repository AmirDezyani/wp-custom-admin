<?php
/**
 * Tests for the (non-destructive) lifecycle handlers.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WPCustomAdmin\Activation;

final class ActivationTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_activate_seeds_empty_option_when_absent(): void {
		Functions\when( 'get_option' )->justReturn( false );
		Functions\expect( 'add_option' )->once()->with( 'wpca_settings', array() );
		Functions\expect( 'update_option' )->once()->with( 'wpca_db_version', WPCA_VERSION );

		Activation::activate();
	}

	public function test_activate_preserves_existing_option(): void {
		Functions\when( 'get_option' )->justReturn( array( 'primary_color' => '#000000' ) );
		Functions\expect( 'add_option' )->never();
		Functions\expect( 'update_option' )->once();

		Activation::activate();
	}

	public function test_deactivate_is_non_destructive(): void {
		Functions\expect( 'delete_option' )->never();

		Activation::deactivate();
	}
}
