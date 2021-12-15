<?php
/**
 * WPGraphQL Content Blocks block base class
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Parser;

use GraphQLRelay\Relay;
use WPGraphQL\Extensions\ContentBlocks\Types\BlockDefinitions;

/**
 * Base block (should be extended and not used directly)
 */
class Block {
	/**
	 * Attributes from HTML element or shortcode
	 *
	 * @var array
	 */
	private $attributes;

	/**
	 * Raw attributes (can be parsed by the plugin user)
	 *
	 * @var array
	 */
	private $attributes_raw;

	/**
	 * Stringified inner HTML of the block
	 *
	 * @var string
	 */
	private $content;

	/**
	 * The Block's node or shortcode name. Must be a valid type in order for this
	 * block to be added to the tree. (See BlockDefinitions.)
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The Block's tag name, in case it differs from the block name (or to give
	 * guidance to the Block implementor).
	 *
	 * @var string
	 */
	private $tag_name;

	/**
	 * Block object in which this Block appears in the children array
	 *
	 * @var Block
	 */
	private $parent;

	/**
	 * List of valid children
	 *
	 * @var array
	 */
	private $children = [];

	/**
	 * The block validator
	 *
	 * @var Validator
	 */
	public $validator;

	/**
	 * Whether this block has any children/content. Empty blocks should not be
	 * added to the tree.
	 *
	 * @var bool
	 */
	private $empty = true;

	/**
	 * Parent block ID.
	 *
	 * @var null|int
	 */
	private $parent_block_index = null;

	/**
	 * Rendered content.
	 *
	 * @var string
	 */
	private $rendered_content = '';

	/**
	 * Constructor.
	 *
	 * @param DOMDocument|DOMElement $parent Parent DOMDocument or DOMElement.
	 * @param string $type Node type.
	 */
	public function __construct( $parent, $type ) {
		$this->set_parent( $parent );
		$this->set_type( $type );

		// Create a validator which will be used to filter attributes and to
		// determine whether the block should be removed, stay where it is, or be
		// hoisted higher in the tree.
		$this->validator = new Validator( $this );
	}

	/**
	 * Factory method for instantiating a child block based on the block_class.
	 *
	 * @param string $block_class The class to instantiate.
	 * @param array $args Arguments with which to instantiate the class.
	 */
	public function create_block( $block_class, $args ) {
		$parent = $args['parent'];

		switch ( $block_class ) {
			case 'EmbedBlock':
				$content = $args['content'];

				return new EmbedBlock( $parent, $args['type'], $args['attributes'], $args['raw'] );

			case 'TextBlock':
				$content = $args['content'];

				return new TextBlock( $parent, $content );

			case 'HTMLBlock':
				$node = $args['node'];

				return new HTMLBlock( $parent, $node );

			case 'ShortcodeBlock':
				$raw = $args['raw'];
				$type = $args['type'];
				$content = $args['content'];
				$attributes = $args['attributes'];

				return new ShortcodeBlock( $parent, $type, $content, $attributes, $raw );
		}
	}

	/**
	 * Get the block's attributes, or an empty array if null. Must always
	 * return an array to satisfy the GraphQL field type for attributes.
	 *
	 * @return array
	 **/
	public function get_attributes() {
		return is_array( $this->attributes ) ? $this->attributes : [];
	}

	/**
	 * Get the block's raw attributes. This is useful in post-validation
	 * filtering.
	 *
	 * @return array
	 **/
	public function get_raw_attributes() {
		return is_array( $this->attributes_raw ) ? $this->attributes_raw : [];
	}

	/**
	 * Get the block's content.
	 *
	 * @return string
	 */
	public function get_content() {
		return $this->content;
	}

	/**
	 * Get the block's children.
	 *
	 * @return array
	 */
	public function get_children() {
		return $this->children;
	}

	/**
	 * Get a child at the given index. If offset is non-negative, the item will
	 * be returned from that offset in the children array. If index is negative,
	 * the item will be returned that far from the end of the children array.
	 *
	 * @param int $index The index of the child to return.
	 *
	 * @return Block
	 */
	public function get_child( $index ) {
		$children = $this->get_children();
		$array_index = $index >= 0 ? $index : count( $children ) + $index;

		if ( array_key_exists( $array_index, $children ) ) {
			return $children[ $array_index ];
		}

		return null;
	}

	/**
	 * Get the block's parent.
	 *
	 * @return DOMDocument|DOMElement
	 */
	public function get_parent() {
		return $this->parent;
	}

	/**
	 * Get the block's tag name.
	 *
	 * @return string
	 */
	public function get_tag_name() {
		if ( 'html' === $this->get_block_type() ) {
			return $this->type;
		}

		return $this->tag_name;
	}

	/**
	 * Recursively stringify this block's children.
	 *
	 * @return string
	 */
	public function get_inner_html() {
		global $wp_embed;

		$document = new \DOMDocument;
		$html = array_reduce( $this->children, function ( $carry, $item ) use ( $document ) {
			return $carry . $document->saveHTML( $item->to_dom_node( $document ) );
		}, '' );

		// Unescape the content of shortcodes that were escaped during DOM parsing.
		add_filter( 'pre_do_shortcode_tag', array( $this, 'unescape_shortcode_content' ), 10, 4 );

		// Parse embeds, then shortcodes.
		$inner_html = do_shortcode( $wp_embed->run_shortcode( $html ) );

		// Remove shortcode filter.
		remove_filter( 'pre_do_shortcode_tag', array( $this, 'unescape_shortcode_content' ), 10 );

		// Texturize and decode entities.
		return html_entity_decode( wptexturize( $inner_html ), ENT_QUOTES );
	}

	/**
	 * Set the block's attributes. Enforce an array.
	 *
	 * @param string|array $attributes An array of attributes (or possible an empty string).
	 */
	public function set_attributes( $attributes ) {
		if ( empty( $attributes ) ) {
			$this->attributes = $this->attributes_raw = [];

			return;
		}

		$this->attributes = $this->validator->filter_attributes( $attributes );
		$this->attributes_raw = $attributes;
	}

	/**
	 * Set the block's content.
	 *
	 * @param string $content Block content.
	 */
	public function set_content( $content ) {
		$this->content = $content;
	}

	/**
	 * Set the block's parent.
	 *
	 * @param DOMDocument|DOMElement $parent Block parent.
	 */
	public function set_parent( $parent ) {
		$this->parent = $parent;
	}

	/**
	 * Set the block's tag name.
	 *
	 * @param string $type Block node type.
	 */
	public function set_tag_name( $tag_name ) {
		$this->tag_name = $tag_name;
	}

	/**
	 * Set the block's type.
	 *
	 * @param string $type Block node type.
	 */
	public function set_type( $type ) {
		$this->type = $type;
	}

	/**
	 * Set the block's is_empty status.
	 *
	 * @param boolean $empty Whether the block should be considered empty.
	 */
	public function set_is_empty( $empty ) {
		$this->empty = $empty;
	}

	/**
	 * Get the block's type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Whether the block should be considered empty.
	 *
	 * @return boolean
	 */
	public function is_empty() {
		return $this->empty;
	}

	/**
	 * Check if the text block to add will be adjacent to another text block. If
	 * so we can merge the two. Returns a merged child block or false on failure.
	 *
	 * @param Block $child_block The child text block to add.
	 *
	 * @return false|Block
	 */
	private function append_text_block( $child_block ) {
		$last_child = $this->get_child( - 1 );

		if ( $last_child && 'text' === $last_child->get_type() ) {
			return $last_child->merge_block( $child_block );
		}

		// Save the child into our child array.
		return $this->append_block( $child_block );
	}

	/**
	 * Push the child block into the this->children array. Returns the appended
	 * block or false on failure.
	 *
	 * @param Block $child_block The child block to add.
	 *
	 * @return false|Block
	 **/
	private function append_block( $child_block ) {
		// Update the child's parent ref as it may have been hoisted up the tree.
		$child_block->set_parent( $this );
		$this->children[] = $child_block;

		// This block is no longer empty.
		$this->set_is_empty( false );

		return $child_block;
	}

	/**
	 * Add a child block to this block's children. Returns the added block or
	 * false on failure.
	 *
	 * @param Block $child_block The child block to add.
	 *
	 * @return false|Block
	 */
	public function add_child( $child_block ) {
		// If the block to add is invalid or empty, do nothing.
		if ( ! $child_block->validator->is_valid() || ! $child_block->validator->has_required_content() ) {
			return false;
		}

		if ( $this->validator->is_valid() ) {
			// Are we trying to add a non-root block to the root? If so, we must
			// wrap it in a p block first.
			if ( 'root' === $this->get_type() && ! $child_block->validator->is_root_allowed() ) {
				return $this->append_orphaned_block( $child_block );
			}

			// HTML comments are an allowed block type, but the only one worth
			// preserving is the "read more" comment. For consistency, transform it
			// into the Gutenberg "core/more" block.
			if ( '#comment' === $child_block->get_type() ) {
				if ( 'more' === trim( $child_block->get_inner_html() ) ) {
					return $this->append_block(
						new GutenbergBlock(
							[
								'attrs' => [],
								'blockName' => 'core/more',
								'innerHTML' => '',
							]
						)
					);
				}

				// Not a "read more" comment? Redact for privacy.
				return false;
			}

			// Is the child to add a text block? If so we will pass it to
			// append_text_block to perform some additional checks on it.
			if ( 'text' === $child_block->get_type() ) {
				return $this->append_text_block( $child_block );
			}

			// The block is ok to add without further changes.
			return $this->append_block( $child_block );
		}

		// The block to add is valid but this one isn't, it's just a conduit.
		// Hoist the block to this block's parent to deal with.
		return $this->hoist( $child_block );
	}

	/**
	 * Wrap orphaned blocks in an HTMLBlock ('p') so it can be added to the root.
	 *
	 * @param TextBlock $child_block The orphaned text block to wrap.
	 *
	 * @return HTMLBlock
	 */
	public function append_orphaned_block( $child_block ) {
		$wrapper = $this->create_block( 'HTMLBlock', [
			'parent' => $this,
			'node' => new \DOMElement( 'p' ),
		] );
		$wrapper->add_child( $child_block );

		return $this->append_block( $wrapper );
	}

	/**
	 * Send the child to be added up the tree to this block's parent. Return the
	 * hoisted block or false on failure.
	 *
	 * @param Block $child_block The child block to add.
	 *
	 * @return false|Block
	 */
	public function hoist( $child_block ) {
		if ( $this->get_parent() ) {
			return $this->get_parent()->add_child( $child_block );
		}

		return false;
	}

	/**
	 * Send the child to the root block to be added. Returns the hoisted block or
	 * false on failure.
	 *
	 * @param Block $child_block The child block to add.
	 *
	 * @return false|Block
	 **/
	public function hoist_to_root( $child_block ) {
		// If the child is already at the root, don't try to hoist.
		if ( empty( $this->parent ) ) {
			return $child_block;
		}

		$parent = $this->parent;

		while ( $parent->parent ) {
			$parent = $parent->parent;
		}

		return $parent->add_child( $child_block );
	}

	/**
	 * Callback for pre_do_shortcode_tag. We need to unescape any HTML that was
	 * escaped by ShortcodeBlock::to_dom_node.
	 *
	 * @param string $return Initial return value.
	 * @param string $tag Shortcode tag.
	 * @param array $attr Attribute array.
	 * @param array $m Matches array.
	 *
	 * @return string
	 */
	public function unescape_shortcode_content( $return, $tag, $attr, $m ) {
		global $shortcode_tags;

		// Get shortcode content; copied from core
		// (https://github.com/WordPress/WordPress/blob/master/wp-includes/shortcodes.php#L323).
		$content = isset( $m[5] ) ? $m[5] : null;

		// Unescape content because it was escaped in ShortcodeBlock::to_dom_node.
		$content = html_entity_decode( $content, ENT_QUOTES );

		// Also copied from core
		// (https://github.com/WordPress/WordPress/blob/master/wp-includes/shortcodes.php#L325).
		return $m[1] . call_user_func( $shortcode_tags[ $tag ], $attr, $content, $tag ) . $m[6];
	}

	/**
	 * Sets parent_id based on parent block passed in.
	 *
	 * @param int|null $parent_block Parent block.
	 *
	 * @return self
	 */
	public function set_parent_block_index( $parent_block_index ) {
		$this->parent_block_index = $parent_block_index;

		return $this;
	}

	/**
	 * Returns parent_id.
	 *
	 * @param string $post_relay_id Post Relay ID.
	 *
	 * @return int|null
	 */
	public function get_parent_id( $post_relay_id ) {
		if ( ! is_null( $this->parent_block_index ) ) {
			return Relay::toGlobalId( 'block', "{$post_relay_id}|{$this->parent_block_index}" );
		} else {
			return null;
		}
	}

	/**
	 * Sets rendered content.
	 *
	 * @param string $rendered_content Rendered content.
	 *
	 * @return $this
	 */
	public function set_rendered_content( $rendered_content ) {
		$this->rendered_content = $rendered_content;

		return $this;
	}

	/**
	 * Returns rendered content.
	 *
	 * @return string
	 */
	public function get_rendered_content() {
		return $this->rendered_content;
	}
}
