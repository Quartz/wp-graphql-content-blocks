<?php
/**
 * WPGraphQL Content Blocks Block Type
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Types;

/**
 * Register block type.
 *
 * @return void
 */
function register_block_type() {
	register_graphql_object_type(
		'Block',
		[
			'description' => 'Content block',
			'fields'      => [
				'attributes'    => [
					'type'        => [ 'list_of' => 'Attribute' ],
					'description' => 'Content block attributes',
					'resolve'     => function( $block ) {
						return array_map(
							function( $key ) use ( $block ) {
								return array(
									'name'  => $key,
									'value' => $block['attributes'][ $key ],
									'json'  => is_json( $block['attributes'][ $key ] )
								);
							},
							array_keys( $block['attributes'] )
						);
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
				// The PostObjectUnion contains every GraphQL type that we're
				// interested in, so we'll use that. We need to find a better home
				// for this.
				'connections' => [
					'type'        => [ 'list_of' => 'PostObjectUnion' ],
					'description' => 'Objects connected to this block',
				],
				'id' => [
					'type'        => 'ID',
					'description' => 'Relay ID of the block, encoding parent post ID and index',
				],
				'parent_id' => [
					'type'        => 'ID',
					'description' => 'Relay ID of the parent block',
				],
				'innerHtml' => [
					'type'        => 'String',
					'description' => 'Content block inner HTML',
				],
				'renderedHtml' => [
					'type'        => 'String',
					'description' => 'Rendered content of Gutenberg block'
				],
				'tagName' => [
					'type'        => 'String',
					'description' => 'Content block tag name (suggested)',
				],
				'type' => [
					'type'        => 'BlockNameEnum',
					'description' => 'Content block name',
				],
			],
		],
	);
}
add_action( 'graphql_register_types', __NAMESPACE__ . '\\register_block_type', 10, 0 );

/**
 * Check whether string is valid json.
 *
 * @param string $string String to be checked.
 *
 * @return bool
 */
function is_json( $string ) {
	@json_decode( $string );
	return json_last_error() === JSON_ERROR_NONE;
}
