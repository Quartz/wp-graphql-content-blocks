<?php
/**
 * WPGraphQL Content Blocks embed class
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Parser;

/**
 * Embed block
 */
class EmbedBlock extends Block {
	/**
	 * The complete verbatim URL captured by the regex, including tags
	 *
	 * @var string
	 */
	private $raw;

	/**
	 * Constructor
	 *
	 * @param  Block  $parent     The Block to which this block belongs.
	 * @param  string $type       Embed type.
	 * @param  array  $attributes Attribute key/value pairs.
	 * @param  string $raw        Raw embed content.
	 * @return void
	 */
	public function __construct( $parent, $type, $attributes, $raw ) {
		parent::__construct( $parent, $type );

		$this->set_attributes( $attributes );
		$this->raw = $raw;
	}

	/**
	 * Provide the block's type.
	 *
	 * @return string
	 */
	public function get_block_type() {
		return 'embed';
	}

	/**
	 * Embed blocks should never be considered empty.
	 *
	 * @return boolean
	 */
	public function is_empty() {
		return false;
	}

	/**
	 * Returns the embed as a shortcode ([embed]) so that it can be parsed by the
	 * block on which get_inner_html was called. We will never need this in a
	 * GraphQL context because embeds will always be at the root, but it could be
	 * useful for testing.
	 *
	 * @param  DOMDocument $document DOMDocument object.
	 * @return DOMTextNode
	 */
	public function to_dom_node( $document ) {
		$shortcode = sprintf( '[embed]%s[/embed]', $this->raw );
		return $document->createTextNode( $shortcode );
	}
}
