<?php
/**
 * Module contract.
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * A self-contained, independently toggleable feature of the plugin.
 *
 * Implementations MUST NOT register hooks in their constructor — only in
 * register(), which the container calls solely when is_enabled() is true.
 */
interface Module {

	/**
	 * Stable machine id for the module (matches its settings flag).
	 */
	public function id(): string;

	/**
	 * Whether this module should boot, based on the resolved settings array.
	 *
	 * @param array<string,mixed> $settings Resolved settings (defaults merged with stored values).
	 */
	public function is_enabled( array $settings ): bool;

	/**
	 * Register the module's WordPress hooks. Called once, only when enabled.
	 */
	public function register(): void;
}
