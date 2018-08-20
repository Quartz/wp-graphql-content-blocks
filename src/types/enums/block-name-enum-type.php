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
			$values[ BlockDefinitions::get_safe_name( $block['name'] ) ] = [
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
