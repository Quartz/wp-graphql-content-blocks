<?php
/**
 * WPGraphQL Content Blocks Block Name Enum
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Types\Enums;

use WPGraphQL\Extensions\ContentBlocks\Types\BlockDefinitions;

/**
 * Register block name enum type.
 *
 * @return void
 */
function register_block_name_enum_type() {
	BlockDefinitions::setup();

	$values = [];
	foreach ( BlockDefinitions::get_root_blocks() as $block ) {
		$values[ BlockDefinitions::get_safe_name( $block['name'] ) ] = [
			'value' => $block['name'],
		];
	}

	register_graphql_enum_type(
		'BlockNameEnum',
		[
			'description' => 'Allowed content block names',
			'values'      => $values,
		]
	);
}
add_action( 'graphql_register_types', __NAMESPACE__ . '\\register_block_name_enum_type', 10, 0 );
