<?php
/**
 * WPGraphQL Content Blocks Gutengberg class
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Parser;

/**
 * Gutenberg Block
 */
class GutenbergBlock extends Block {
	/**
	 * Constructor
	 *
	 * @param array $block A gutenberg block (from gutenberg_parse_blocks).
	 * @param null|int $parent_block_index Parent block index.
	 */
	public function __construct( $block, $parent_block_index = null ) {
		parent::__construct( null, $block['blockName'] );

		$this->set_content( trim( $block['innerHTML'] ) );
		$this->add_children();
		$this->set_attributes( $block['attrs'] );
		$this->set_parent_block_index( $parent_block_index );

		$rendered_content = apply_filters( 'the_content', render_block( $block ) );
		$this->set_rendered_content( $rendered_content );
	}

	/**
	 * Adds a single child based on this->content.
	 */
	private function add_children() {
		if ( empty( $this->get_content() ) ) {
			return;
		}

		// Parse the "inner HTML" of the block.
		$child = create_root_block( $this->get_content() );
		if ( ! $child || 0 === count( $child->get_children() ) ) {
			return;
		}

		// Get the root node.
		$child = $child->get_children()[0];

		// Save the outer tag name, then remove it.
		$this->set_tag_name( $child->get_type() );
		foreach ( $child->get_children() as $node ) {
			$this->add_child( $node );
		}
	}

	/**
	 * Provide the block's type.
	 *
	 * @return string
	 */
	public function get_block_type() {
		return 'gutenberg';
	}
}
