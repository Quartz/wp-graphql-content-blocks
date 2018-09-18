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
						// The "connections" field provides an array of objects that are
						// connected to a block: e.g., posts (for a block that points to
						// another post), attachments (images uploaded in BlockControls),
						// terms ... really anything that the plugin user would want to
						// implement as a pointer from a block to another WP object.
						//
						// The really bad spot we're in here is that we have no idea how
						// to resolve these connections on the plugin user's behalf.
						// This is especially apparent for Gutenberg blocks: we're
						// looking for pointers in the block's attributes, but there is
						// no common convention or structure for encoding, say, a post
						// ID or an attachment ID. It's an implementation detail that,
						// likely, everyone will tackle differently. This is the same
						// problem we've had with post meta for years and years. Bummer,
						// because Gutenberg was an opportunity to provide structure for
						// this super-common use case.
						//
						// The only thing we can do is to provide an empty array and
						// leave it up to the implementor to filter the blocks (via
						// graphql_blocks_output) and make the connections themselves.
						// They will get the blocks and the post to which it belongs and
						// can sift through the attributes to determine if anything
						// needs to be connected.
						//
						// The MenuItemObjectUnion contains every GraphQL type that
						// we're interested in, so we'll use that. This tells me that we
						// should probably rename it upstream since it's useful for more
						// than just menu items (it's really a union of all core WP data
						// types—post types, taxonomies, users).
						'connections' => [
							'type'        => Types::list_of( Types::menu_item_object_union() ),
							'description' => 'Objects connected to this block',
						],
						'innerHtml' => [
							'type'        => Types::string(),
							'description' => 'Content block inner HTML',
						],
						'tagName' => [
							'type'        => Types::string(),
							'description' => 'Content block tag name (suggested)',
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
