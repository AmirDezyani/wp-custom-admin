<?php
/**
 * Shared base for feature modules.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Modules;

use WPCustomAdmin\Contracts\Module;
use WPCustomAdmin\Support\Assets;
use WPCustomAdmin\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the injected services. Adds NO hooks (constructors are side-effect free).
 */
abstract class AbstractModule implements Module {

	protected Settings $settings;
	protected Assets $assets;

	public function __construct( Settings $settings, Assets $assets ) {
		$this->settings = $settings;
		$this->assets   = $assets;
	}
}
