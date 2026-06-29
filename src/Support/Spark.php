<?php
/**
 * Inline-SVG sparkline renderer (zero JS, zero CDN).
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a small trend line as a self-contained <svg>. The line uses
 * currentColor so the card sets its color; values are integer counts.
 */
final class Spark {

	/**
	 * Build a sparkline SVG from a series of values.
	 *
	 * @param int[] $values Series (oldest → newest).
	 */
	public static function svg( array $values ): string {
		$values = array_values( array_map( 'intval', $values ) );
		$count  = count( $values );

		if ( $count < 2 ) {
			return '';
		}

		$width  = 100;
		$height = 28;
		$pad    = 3;
		$min    = min( $values );
		$max    = max( $values );
		$range  = ( $max - $min ) > 0 ? ( $max - $min ) : 1;

		$points = array();
		foreach ( $values as $index => $value ) {
			$x        = round( ( $index / ( $count - 1 ) ) * $width, 2 );
			$y        = round( $height - $pad - ( ( $value - $min ) / $range ) * ( $height - 2 * $pad ), 2 );
			$points[] = $x . ',' . $y;
		}

		$line = implode( ' ', $points );
		$area = '0,' . $height . ' ' . $line . ' ' . $width . ',' . $height;

		return sprintf(
			'<svg class="wpca-spark" viewBox="0 0 %1$d %2$d" preserveAspectRatio="none" aria-hidden="true"><polygon class="wpca-spark-fill" points="%3$s"/><polyline points="%4$s" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			$width,
			$height,
			esc_attr( $area ),
			esc_attr( $line )
		);
	}
}
