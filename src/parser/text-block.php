<?php
/**
 * WPGraphQL Content Blocks text block class
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Parser;

/**
 * Text block
 */
class TextBlock extends Block {
	/**
	 * Constructor
	 *
	 * @param object $parent The Block object to which this block belongs.
	 * @param string $content Inner HTML.
	 */
	public function __construct( $parent, $content ) {
		parent::__construct( $parent, 'text' );
		$this->set_content( $content );
	}

	/**
	 * Provide the block's type.
	 *
	 * @return string
	 */
	public function get_block_type() {
		return 'text';
	}

	/**
	 * Return a DOMTextNode containing this block's content.
	 *
	 * @param  DOMDocument $document DOMDocument object.
	 * @return DOMTextNode
	 */
	public function to_dom_node( $document ) {
		return $document->createTextNode( $this->get_content() );
	}

	/**
	 * Override is_empty because we will determnine emptiness from the content
	 * of this block.
	 *
	 * @return boolean
	 */
	public function is_empty() {
		return empty( $this->get_content() );
	}

	/**
	 * Takes the content of supplied text block argument and merges its contents
	 * into this block's contents.
	 *
	 * @param  Block $block The text block to merge into this.
	 * @return Block
	 **/
	public function merge_block( $block ) {
		$this->set_content( $this->get_content() . $block->get_content() );
		return $this;
	}
}
