<?php
/**
 * WPGraphQL Content Blocks Block Attribute Type
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Types;

use WPGraphQL\Types;
use WPGraphQL\Type\WPObjectType;

/**
 * WPGraphQL Content Blocks Block Attribute Type.
 */
class BlockAttributeType {
	/**
	 * Cached type definition.
	 *
	 * @var WPObjectType
	 */
	private static $type;

	/**
	 * Create type definition.
	 *
	 * @return WPObjectType
	 */
	public static function get_type() {
		if ( self::$type ) {
			return self::$type;
		}

		self::$type = new WPObjectType(
			[
				'name'        => 'attribute',
				'description' => 'Content block attribute',
				'fields'      => function() {
					$fields = [
						'name' => [
							'type'        => Types::string(),
							'description' => 'Attribute name',
						],
						'value' => [
							'type'        => Types::string(),
							'description' => 'Attribute value',
						],
					];

					return WPObjectType::prepare_fields( $fields, 'attribute' );
				},
			]
		);

		return self::$type;
	}

	/**
	 * Type definition for a list of blocks.
	 *
	 * @return ListOfType
	 */
	public static function get_list_type() {
		return Types::list_of( self::get_type() );
	}
}
