<?php
/**
 * WPGraphQL Content Blocks block validator
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Parser;

use WPGraphQL\Extensions\ContentBlocks\Types\BlockDefinitions;

/**
 * Retrieves a block's definitions and validates its properties against it.
 */
class Validator {
	/**
	 * Block
	 *
	 * @var Block
	 */
	private $block;

	/**
	 * The block's definition as defined on BlockDefinitions
	 *
	 * @var array
	 */
	private $definition;

	/**
	 * List of self-closing tag names. Used for determining whether a tag is
	 * required to have children or not.
	 * https://developer.mozilla.org/en-US/docs/Glossary/empty_element
	 *
	 * @var array
	 */
	private $self_closing_tags = [
		'area',
		'base',
		'br',
		'col',
		'embed',
		'hr',
		'img',
		'input',
		'link',
		'meta',
		'param',
		'source',
		'track',
		'wbr',
	];

	/**
	 * Constructor.
	 *
	 * @param Block $block Block to validate.
	 */
	public function __construct( $block ) {
		$this->block = $block;

		// Set this block's definition from BlockDefinitions.
		$this->definition = BlockDefinitions::get_block_definition( $this->block->get_block_type(), $this->block->get_type() );
	}

	/**
	 * Does the block have all of its required attributes?
	 *
	 * @return boolean
	 */
	private function has_required_attributes() {
		$attributes = $this->block->get_attributes();
		$rules = $this->definition['attributes'];

		if ( isset( $rules['required'] ) ) {
			// Ensure that all the required attributes exist in the attributes array.
			foreach ( $rules['required'] as $required_attr ) {
				if ( ! in_array( $required_attr, $attributes, true ) ) {
					return false;
				}
			}

			// All requirements met, passing.
			return true;
		}

		// At least one attribute is required.
		if ( $rules['require_one_or_more'] ) {
			return count( $attributes ) > 0;
		}

		// All requirements met, passing.
		return true;
	}

	/**
	 * Does the block have content (or is it allowed to be empty)?
	 *
	 * @return boolean
	 */
	public function has_required_content() {
		if ( $this->definition['allow_empty'] ) {
			return true;
		}

		if ( $this->is_self_closing_html() ) {
			return true;
		}

		return ! $this->block->is_empty();
	}

	/**
	 * Is this the root block?
	 *
	 * @return boolean
	 */
	public function is_root() {
		return 'root' === $this->block->get_type();
	}

	/**
	 * Is the block permitted to be at the root level?
	 *
	 * @return boolean
	 */
	public function is_root_allowed() {
		return (
			isset( $this->definition['allow_root'] ) &&
			true === $this->definition['allow_root']
		);
	}

	/**
	 * Is the block a root-only block? (Does it need to be hoisted to the root of
	 * the tree?)
	 *
	 * @return boolean
	 */
	public function is_root_only() {
		return (
			// The block is defined as root_only...
			isset( $this->definition['root_only'] ) &&
			true === $this->definition['root_only'] &&
			// ...and does not belong to a parent whose type exempts it from being hoisted
			(
				! isset( $this->definition[ 'allowed_parent_types' ] ) ||
				! is_array( $this->definition[ 'allowed_parent_types' ] ) ||
				! in_array( $this->block->get_parent()->get_type(), $this->definition[ 'allowed_parent_types' ], true )
			)
		);
	}

	/**
	 * Checks the block's type against the predefined list of self-closing tag
	 * names.
	 *
	 * @return boolean
	 */
	private function is_self_closing_html() {
		return (
			'html' === $this->block->get_block_type() &&
			in_array( $this->block->get_type(), $this->self_closing_tags, true )
		);
	}

	/**
	 * Checks basic block validity:
	 * - Block definition exists
	 * - Any required attributes are present
	 *
	 * @return boolean
	 */
	public function is_valid() {
		// The root block is valid.
		if ( 'root' === $this->block->get_type() ) {
			return true;
		}

		// Embed and text blocks are always valid.
		if ( in_array( $this->block->get_block_type(), [ 'embed', 'text' ], true ) ) {
			return true;
		}

		// Check that this block is allowed at all.
		if ( ! $this->definition ) {
			return false;
		}

		// Check for required attributes.
		return $this->has_required_attributes();
	}

	/**
	 * Filter the blocks attributes to remove any that are not allowed.
	 *
	 * @param  array $attributes Array of attribute name / value pairs.
	 * @return array
	 */
	public function filter_attributes( $attributes ) {
		// Stringify arrays.
		$attributes = array_map( function( $attribute ) {
			if (is_array( $attribute )) {
				return json_encode( $attribute );
			}

			return $attribute;
		}, $attributes );

		// Attribute values must be strings, numerics (coerced to strings), or null.
		$attributes = array_filter( $attributes, function( $attribute ) {
			return is_string( $attribute ) || is_numeric( $attribute ) || is_null( $attribute );
		} );

		// If there are no attribute rules to work with, give all the attributes back.
		if ( ! isset( $this->definition['attributes'] ) ) {
			return $attributes;
		}

		$allowed = $this->definition['attributes']['allow'];
		$denied = $this->definition['attributes']['deny'];

		// Filter the array for valid attributes.
		return array_filter( $attributes, function( $attribute ) use ( $allowed, $denied ) {
			if ( is_array( $allowed ) ) {
				return in_array( $attribute, $allowed, true );
			}

			return ! is_array( $denied ) || ! in_array( $attribute, $denied, true );
		}, ARRAY_FILTER_USE_KEY );
	}
}
