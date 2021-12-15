<?php
/**
 * WPGraphQL Content Blocks Block Attribute Type
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Types;

/**
 * Register block attribute type.
 *
 * @return void
 */
function register_block_attribute_type() {
	register_graphql_object_type(
		'Attribute',
		[
			'description' => 'Content block attribute',
			'fields'      => [
				'name' => [
					'type'        => 'String',
					'description' => 'Attribute name',
				],
				'value' => [
					'type'        => 'String',
					'description' => 'Attribute value',
				],
				'json' => [
					'type'        => 'Bool',
					'description' => 'If attribute value is encoded JSON',
				],
			],
		]
	);
}
add_action( 'graphql_register_types', __NAMESPACE__ . '\\register_block_attribute_type', 10, 0 );
