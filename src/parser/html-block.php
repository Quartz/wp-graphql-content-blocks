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
	 * Tests the given string against all shortcode regexes. Returns the name of the first
	 * matching embed.
	 *
	 * @param  string $content The string to check against the embed regexes
	 * @return string|bool $name|false The embed type that matched against the string, or false if not matches
	 **/
	public function find_embed_match( $content ) {
		foreach ( BlockDefinitions::get_blocks( 'embed' ) as $name => $embed ) {
			foreach ( $embed['regex'] as $regex ) {
				if ( preg_match( $regex, $content ) ) {
					return $name;
				}
			}
		}
		return false;
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
			$embed_name = self::find_embed_match( $trimmed_content );
			if ( $embed_name ) {
				return [
					[
						'attributes'  => [
							 'url'       => $trimmed_content,
						],
						'block_class' => 'EmbedBlock',
						'parent'      => $this,
						'type'        => $embed_name,
						'raw'         => $trimmed_content,
					],
				];
			}
		}

		// Stealing some core.
		// https://core.trac.wordpress.org/browser/tags/4.9/src/wp-includes/shortcodes.php#L228
		$shortcode_regex =
			'\\['								// Opening bracket
			. '(\\[?)'						// 1: Optional second opening bracket for escaping shortcodes: [[tag]]
			. '([\\w-]+)'					// 2: Shortcode name
			. '(?![\\w-])'					// Not followed by word character or hyphen
			. '('								// 3: Unroll the loop: Inside the opening shortcode tag
			.	'[^\\]\\/]*'				// Not a closing bracket or forward slash
			.	'(?:'
			.		'\\/(?!\\])'			// A forward slash not followed by a closing bracket
			.		'[^\\]\\/]*'			// Not a closing bracket or forward slash
			.	')*?'
			. ')'
			. '(?:'
			.	'(\\/)'						// 4: Self closing tag ...
			.	'\\]'						// ... and closing bracket
			. '|'
			.	'\\]'						// Closing bracket
			.	'(?:'
			.		'('						// 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
			.			'[^\\[]*+'			// Not an opening bracket
			.			'(?:'
			.				'\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
			.				'[^\\[]*+'		// Not an opening bracket
			.			')*+'
			.		')'
			.		'\\[\\/\\2\\]'			// Closing shortcode tag
			.	')?'
			. ')'
			. '(\\]?)';						// 6: Optional second closing brocket for escaping shortcodes: [[tag]]

		$return		= [];
		$pattern		= '/' . $shortcode_regex . '/s';
		$has_shortcodes  = preg_match_all( $pattern, $html, $matches );
		$shortcode_definitions = BlockDefinitions::get_blocks( 'shortcode' );

		if ( $has_shortcodes ) {

			// Create an array of the text between shortcodes.
			$remaining_text = preg_split( $pattern, $html );

			// Create our return array by zipping remaining text and shortcodes.
			foreach ( $remaining_text as $index => $text ) {
				$has_match = $index < count( $matches[0] );
				$raw_text = $has_match ? $matches[0][ $index ] : '';
				$shortcode_name = $has_match ? 'shortcode_' . $matches[2][ $index ] : false;
				$is_valid_shortcode = isset( $shortcode_definitions[ $shortcode_name ] );

				// If it's not a registered shortcode, append it as text to the
				// previous text block. This preserves regular [bracketed] text.
				$leading_text = $is_valid_shortcode ? $text : $text . $raw_text;

				// For each iteration push the leading text to the return array.
				// Prevent escaped entities (&lt;code&gt;) from being unescaped.
				$text_block = [
					'block_class' => 'TextBlock',
					'content'     => htmlspecialchars( $leading_text, ENT_NOQUOTES ),
					'parent'      => $this,
				];

				// Only add the text block if the leading text is non-empty.
				if ( trim( $leading_text ) ) {
					$return[] = $text_block;
				}

				// Then push a shortcode from our $matches array.
				if ( $has_match && $is_valid_shortcode ) {
					// These indices are based on Wordpreß' shortcode regex groups.
					$block = [
						'attributes'  => shortcode_parse_atts( $matches[3][ $index ] ),
						'block_class' => 'ShortcodeBlock',
						'content'     => $matches[5][ $index ],
						'parent'      => $this,
						'type'        => $shortcode_name,
						'raw'         => $raw_text,
					];

					// Embed shortcode is a special kind of embed.
					if ( 'shortcode_embed' === $block['type'] ) {
						$embed_name = self::find_embed_match( $block['content'] );
						if ( $embed_name ) {
							$block = [
								'attributes'  => [
									'url'       => $block['content'],
								],
								'block_class' => 'EmbedBlock',
								'parent'      => $this,
								'type'        => $embed_name,
								'raw'         => $block['content'],
							];
						}
						unset( $block['content'] );
					}

					$return[] = $block;
				}
			}

			return $return;
		}

		// There are no shortcodes, just return a TextBlock for the entire text.
		// Prevent escaped entities (&lt;code&gt;) from being unescaped.
		return [
			[
				'block_class' => 'TextBlock',
				'content'     => htmlspecialchars( $html, ENT_NOQUOTES ),
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

/**
 * Parse an HTML string an create a root-level HTMLBlock.
 * - Instantiate a DOMDocument object
 * - Pass the DOMDocument object into a new HTMLBlock object. This
 *   will begin the process of recursively parsing the HTML tree
 * - Return the HTMLBlock object
 *
 * @param  string $html HTML string
 * @return HTMLBlock
 */
function create_root_block( $html ) {
	// Create a DOM parser that we can walk with our Block classes.
	$doc = new \DOMDocument;
	$doc->preserveWhiteSpace = false;
	\libxml_use_internal_errors( true );

	// Load the HTML into the DOMDocument. Include the UTF-8 encoding
	// declaration. Suppress errors because DOMDocument doesn't recognize
	// HTML5 tags as valid -_-.
	if ( $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html ) ) {
		return new HTMLBlock( null, $doc, 'root' );
	}

	return null;
}
