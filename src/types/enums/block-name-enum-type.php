<?php
/**
 * WPGraphQL Content Blocks Block Name Enum
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Types\Enums;

use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Extensions\ContentBlocks\Types\BlockDefinitions;

/**
 * WPGraphQL Content Blocks Block Name Enum
 */
class BlockNameEnumType {
	/**
	 * Cached type definition.
	 *
	 * @var WPEnumType
	 */
	private static $type;

	/**
	 * Generate a safe / sanitized name from an enum value.
	 *
	 * @param  string $value Enum value.
	 * @return string
	 */
	private static function get_safe_name( $value ) {
		$safe_name = strtoupper( preg_replace( '#[^A-z0-9]#', '_', $value ) );

		// Enum names must start with a letter or underscore.
		if ( ! preg_match( '#^[_a-zA-Z]#', $value ) ) {
			return '_' . $safe_name;
		}

		return $safe_name;
	}

	/**
	 * Create type definition.
	 *
	 * @return WPEnumType
	 */
	public static function get_type() {
		if ( self::$type ) {
			return self::$type;
		}

		$values = [];
		foreach ( BlockDefinitions::get_root_blocks() as $block ) {
			$values[ self::get_safe_name( $block['name'] ) ] = [
				'value' => $block['name'],
			];
		}

		self::$type = new WPEnumType(
			[
				'name'        => 'blockNameEnum',
				'description' => 'Allowed content block names',
				'values'      => $values,
			]
		);

		return self::$type;
	}
}
