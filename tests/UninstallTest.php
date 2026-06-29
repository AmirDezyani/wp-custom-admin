<?php
/**
 * Tests for the guarded, opt-in uninstall cleanup.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

final class UninstallTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_does_nothing_without_optin(): void {
		Functions\when( 'get_option' )->justReturn( array( 'delete_on_uninstall' => false ) );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\expect( 'delete_option' )->never();

		include WPCA_PATH . 'uninstall.php';
	}

	public function test_does_nothing_when_option_missing(): void {
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\expect( 'delete_option' )->never();

		include WPCA_PATH . 'uninstall.php';
	}

	public function test_deletes_when_optin_on_single_site(): void {
		Functions\when( 'get_option' )->justReturn( array( 'delete_on_uninstall' => true ) );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\expect( 'delete_option' )->twice();

		include WPCA_PATH . 'uninstall.php';
	}
}
