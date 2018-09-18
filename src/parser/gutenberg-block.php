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
	 */
	public function __construct( $block ) {
		parent::__construct( null, $block['blockName'] );

		$this->set_content( trim( $block['innerHTML'] ) );
		$this->add_children();
		$this->set_attributes( $block['attrs'] );
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
