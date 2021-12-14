<?php
/**
 * WPGraphQL Content Blocks shortcode class
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Parser;

/**
 * Shortcode Block
 */
class ShortcodeBlock extends Block {
	/**
	 * The complete verbatim shortcode captured by the regex, including tags
	 *
	 * @var string
	 */
	private $raw;

	/**
	 * Constructor
	 *
	 * @param Block  $parent     The Block to which this block belongs.
	 * @param string $type       Shortcode tag name.
	 * @param string $content    Shortcode inner HTML.
	 * @param array  $attributes Assoc. array of attribute key/values.
	 * @param string $raw        Raw shortcode content.
	 */
	public function __construct( $parent, $type, $content, $attributes, $raw ) {
		parent::__construct( $parent, $type );

		$this->set_attributes( $attributes );
		$this->raw = $raw;

		if ( ! empty( $content ) ) {
			$this->set_content( $content );
			$this->add_children();
		}
	}

	/**
	 * Adds a single text child based on this->content.
	 */
	private function add_children() {
		if ( ! $this->get_children() ) {
			$block = $this->create_block( 'TextBlock', [
				'parent'  => $this,
				'content' => $this->get_content(),
			] );
			$this->add_child( $block );
		}
	}

	/**
	 * Provide the block's type.
	 *
	 * @return string
	 */
	public function get_block_type() {
		return 'shortcode';
	}

	/**
	 * Return a DOMTextNode containing the raw shortcode it will be parsed with
	 * do_shortcode by the block on which get_inner_html was called.
	 *
	 * @param  DOMDocument $document DOMDocument object.
	 * @return DOMTextNode
	 */
	public function to_dom_node( $document ) {
		return $document->createTextNode( $this->raw );
	}
}
