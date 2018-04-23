<?php
/**
 * WPGraphQL Content Blocks Block Type
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Types;

use WPGraphQL\Types;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Extensions\ContentBlocks\Types\BlockAttributeType;
use WPGraphQL\Extensions\ContentBlocks\Types\Enums\BlockNameEnumType;

/**
 * WPGraphQL Content Blocks Block Type.
 */
class BlockType {
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
				'name'        => 'block',
				'description' => 'Content block',
				'fields'      => function() {
					$fields = [
						'attributes' => [
							'type'        => BlockAttributeType::get_list_type(),
							'description' => 'Content block attributes',
							'resolve'     => function( $block ) {
								return array_map( function( $key ) use ( $block ) {
									return array(
										'name'  => $key,
										'value' => $block['attributes'][ $key ],
									);
								}, array_keys( $block['attributes'] ) );
							},
						],
						'innerHtml' => [
							'type'        => Types::string(),
							'description' => 'Content block inner HTML',
						],
						'type' => [
							'type'        => BlockNameEnumType::get_type(),
							'description' => 'Content block name',
						],
					];

					return WPObjectType::prepare_fields( $fields, 'block' );
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
