<?php
/**
 * WPGraphQL Content Blocks HTML block class
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Parser;

use WPGraphQL\Extensions\ContentBlocks\Types\BlockDefinitions;

/**
 * HTML block
 */
class HTMLBlock extends Block {
	/**
	 * The DOMDocument or DOMElement object for this block
	 *
	 * @var DOMDocument|DOMElement
	 */
	private $node;

	/**
	 * Constructor
	 *
	 * @param Block                  $parent The block to which this block belongs.
	 * @param DOMDocument|DOMElement $node   DOMDocument or DOMNode.
	 * @param string                 $type   Node type, if known.
	 */
	public function __construct( $parent = null, $node = null, $type = null ) {
		$this->node = $node;

		// If there is a node and type has not been provided, use the node's tag name.
		if ( null === $type && $node ) {
			$type = $node->nodeName;
		}

		parent::__construct( $parent, $type );
		$this->add_children();
	}

	/**
	 * Provide the block's type.
	 *
	 * @return string
	 */
	public function get_block_type() {
		return 'html';
	}

	/**
	 * Does this block have any siblings?
	 *
	 * @return bool
	 **/
	public function has_siblings() {
		return ! $this->validator->is_root() && count( $this->get_parent()->get_children() ) > 0;
	}

	/**
	 * Split this node's text content into shortcodes and text blocks.
	 *
	 * @param  string $html  This node's text content.
	 * @return array $return Array of arrays that will be used to instantiate child blocks.
	 **/
	private function parse_text_content( $html ) {
		$trimmed_content = trim( $html );

		// First look for embeds. We expect them to be the only child of a p block
		// (https://codex.wordpress.org/Embeds).
		if ( 'p' === $this->get_parent()->get_type() && ! $this->has_siblings() ) {
			foreach ( BlockDefinitions::get_blocks( 'embed' ) as $name => $embed ) {
				foreach ( $embed['regex'] as $regex ) {
					if ( preg_match( $regex, $trimmed_content ) ) {
						return [
							[
								'attributes'  => [
									'url'      => $trimmed_content,
								],
								'block_class' => 'EmbedBlock',
								'parent'      => $this,
								'type'        => $name,
								'raw'         => $trimmed_content,
							],
						];
					}
				}
			}
		}

		$return          = [];
		$shortcode_regex = get_shortcode_regex();
		$pattern         = '/' . $shortcode_regex . '/s';
		$has_shortcodes  = preg_match_all( $pattern, $html, $matches );

		if ( $has_shortcodes ) {
			// Create an array of the text between shortcodes.
			$remaining_text = preg_split( $pattern, $html );

			// Create our return array by zipping remaining text and shortcodes.
			foreach ( $remaining_text as $index => $text ) {
				$trimmed = trim( $text );

				// For each iteration push a piece of surrounding text to the return array.
				if ( $trimmed ) {
					$return[] = [
						'block_class' => 'TextBlock',
						'content'     => $text,
						'parent'      => $this,
					];
				}

				// Then push a shortcode from our $matches array.
				if ( $index < count( $matches[0] ) ) {
					// These indices are based on Wordpreß' shortcode regex groups.
					$block = [
						'attributes'  => shortcode_parse_atts( $matches[3][ $index ] ),
						'block_class' => 'ShortcodeBlock',
						'content'     => $matches[5][ $index ],
						'parent'      => $this,
						'type'        => 'shortcode_' . $matches[2][ $index ],
						'raw'         => $matches[0][ $index ],
					];

					// Embed shortcode is a special kind of embed.
					if ( 'embed' === $block['type'] ) {
						$block['attributes']['url'] = $block['content'];
						$block['block_class'] = 'EmbedBlock';
						$block['type'] = 'embed';
						unset( $block['content'] );
					}

					$return[] = $block;
				}
			}

			return $return;
		}

		// There are no shortcodes, just return a TextBlock for the entire text.
		return [
			[
				'block_class' => 'TextBlock',
				'content'     => $html,
				'parent'      => $this,
			],
		];
	}

	/**
	 * Parse the supplied array of HTML nodes and return an array of arrays that
	 * contain the arguments we will use to instantiate an Block.
	 *
	 * @param  array $nodes    Array of child nodes.
	 * @return array $children Arrays of arguments for creating child blocks
	 **/
	private function parse_html_nodes( $nodes ) {
		$children = [];
		foreach ( $nodes as $child_node ) {
			// Skip the encoding declaration node.
			if ( 'xml' === $child_node->nodeName ) {
				continue;
			}

			$children[] = array(
				'block_class' => 'HTMLBlock',
				'node'        => $child_node,
				'parent'      => $this,
			);
		}

		return $children;
	}

	/**
	 * Parse the contents of this block's HTML and instantiate child blocks.
	 *
	 * @return array Child blocks
	 **/
	private function add_children() {
		if ( ! $this->get_children() ) {
			$child_args = [];

			if ( ! $this->node ) {
				return;
			}

			if ( ! $this->node->hasChildNodes() ) {
				if ( $this->node->textContent ) {
					$child_args = $this->parse_text_content( $this->node->textContent );
				}
			} else {
				$child_args = $this->parse_html_nodes( $this->node->childNodes );
			}

			foreach ( $child_args as $child ) {
				$child_block = $this->create_block( $child['block_class'], $child );

				// Hoist root-only blocks to the top.
				if ( $child_block->validator->is_root_only() ) {
					$this->hoist_to_root( $child_block );
					continue;
				}

				// Otherwise attempt to add it to this block's children.
				$this->add_child( $child_block );
			}
		}

		// Set the attributes once on parse.
		$this->set_attributes( $this->get_attributes_from_node() );

		return $this->get_children();
	}

	/**
	 * Create an array of name / value pairs representing the node's attributes.
	 *
	 * @return array
	 */
	private function get_attributes_from_node() {
		$all_attributes = [];

		if ( $this->node->hasAttributes() ) {
			foreach ( $this->node->attributes as $attr ) {
				$all_attributes[ $attr->nodeName ] = $attr->nodeValue;
			}
		}

		return $all_attributes;
	}

	/**
	 * Create a DOMElement that represents this block and its children
	 * (recursively) and attributes.
	 *
	 * @param  DOMDocument $document Ancestor DOMDocument.
	 * @return DOMElement
	 */
	public function to_dom_node( $document ) {
		$node = $document->createElement( $this->get_type() );

		foreach ( $this->get_attributes() as $name => $value ) {
			$node->setAttribute( $name, $value );
		}

		foreach ( $this->get_children() as $child ) {
			$node->appendChild( $child->to_dom_node( $document ) );
		}

		return $node;
	}
}
