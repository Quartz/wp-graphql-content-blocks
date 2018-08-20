<?php
/**
 * WPGraphQL Content Blocks Fields
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Data;

use WPGraphQL;
use WPGraphQL\Extensions\ContentBlocks\Parser\HTMLBlock;
use WPGraphQL\Extensions\ContentBlocks\Types\BlockType;
use \DOMDocument;

/**
 * Content blocks field for WPGraphQL
 */
class Fields {
	/**
	 * Description of blocks field.
	 *
	 * @var string
	 */
	private $description = 'Structured / parsed post content described as a shallow tree of block elements';

	/**
	 * Name of blocks field.
	 *
	 * @var string
	 */
	private $field_name = 'blocks';

	/**
	 * The post_meta key name in which to cache our blocks tree
	 *
	 * @var string
	 **/
	private $post_meta_field = 'wp_graphql_blocks';

	/**
	 * Whether to cache blocks until post is updated.
	 *
	 * @var bool
	 */
	private $enable_cache = true;

	/**
	 * A version number attached to the blocks object so that we can ignore
	 * cached output of earlier versions (or later if user downgrades).
	 *
	 * @var string
	 */
	private $version = '0.1.8';

	/**
	 * Add actions and filters.
	 */
	public function init() {
		add_action( 'do_graphql_request', array( $this, 'add_field_filters' ), 10, 0 );
		add_filter( 'save_post', array( $this, 'clear_cache' ), 10, 1 );

		$this->enable_cache = apply_filters( 'graphql_blocks_enable_cache', $this->enable_cache );
	}

	public function add_field_filters() {
		$post_types = array_filter( WPGraphQL::get_allowed_post_types(), function ( $post_type ) {
			return post_type_supports( $post_type, 'editor' );
		} );

		foreach ( $post_types as $post_type ) {
			$type = get_post_type_object( $post_type )->graphql_single_name;
			add_filter( "graphql_{$type}_fields", array( $this, 'add_fields' ), 10, 1 );
		}
	}

	/**
	 * Add fields for blocks.
	 *
	 * @param  array $fields Array of GraphQL fields.
	 * @return array
	 */
	public function add_fields( $fields ) {
		// If the blocks field has already been defined, don't redefine (or
		// override) it.
		if ( isset( $fields[ $this->field_name ] ) ) {
			return $fields;
		}

		$fields[ $this->field_name ] = array(
			'type'        => BlockType::get_list_type(),
			'description' => $this->description,
			'resolve'     => array( $this, 'resolve' ),
		);

		return $fields;
	}

	/**
	 * Log a parsing error.
	 *
	 * @param  WP_Post $post    Post that encountered a parsing error.
	 * @param  string  $message Optional error message.
	 * @return void
	 */
	private function log_error( $post, $message = null ) {
		if ( ! function_exists( 'qz_send_to_slackbot' ) ) {
			return;
		}

		if ( empty( $message ) ) {
			$message = sprintf( 'Unable to generate blocks for post %d.', $post->ID );
		}

		qz_send_to_slackbot( $message, '#testing1', false, 'qzbot' );
	}

	/**
	 * Clear the post meta cache on save_post. We do this even if caching is
	 * disabled so that there is no stale cache data if caching is reenabled.
	 *
	 * @param  int $post_id WP_Post Id.
	 * @return void
	 */
	public function clear_cache( $post_id ) {
		delete_post_meta( $post_id, $this->post_meta_field );
	}

	/**
	 * Run the necessary WP filters on the HTML, remove newlines, and escape HTML
	 * inside shortcode tags.
	 *
	 * @param  string $html The HTML to be formatted.
	 * @return string $decoded_html The formatted HTML
	 **/
	private function prepare_html( $html ) {
		// Run a subset of the_content filters on the text.
		$html = wpautop( wptexturize( $html ) );
		$decoded_html = trim( apply_filters( 'convert_chars', $html ) );

		// Remove any remaining linebreaks now that we have been converted to HTML
		// (leaving them in will result in the creation of empty text nodes).
		$decoded_html = preg_replace( '/\n/', '', $decoded_html );

		// Escape HTML inside shortcode tags. This prevents DOMDocument from splitting
		// shortcode contents into separate nodes. We can unescape the HTML if we need
		// to parse the shortcode later.
		$shortcode_regex = get_shortcode_regex();
		$pattern = '/' . $shortcode_regex . '/s';
		return preg_replace_callback( $pattern, function( $matches ) {
			return htmlspecialchars( $matches[0] );
		}, $decoded_html );
	}

	/**
	 * Parse the content of a post and return blocks.
	 * This will:
	 * - Prepare the HTML for parsing (see Fields::prepare_html)
	 * - Instantiate a DOMDocument object
	 * - Pass the DOMDocument object into a new HTMLBlock object. This
	 *   will begin the process of recursively parsing the HTML tree
	 * - Return the HTMLBlock object
	 *
	 * @param  WP_Post $post Post to parse.
	 * @return array|null
	 */
	private function parse_post_content( $post ) {
		$html = $this->prepare_html( $post->post_content );
		if ( empty( $html ) ) {
			return null;
		}

		// Create a DOM parser that we can walk with our Block classes.
		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		libxml_use_internal_errors( true );

		// Load the HTML into the DOMDocument. Include the UTF-8 encoding
		// declaration. Suppress errors because DOMDocument doesn't recognize
		// HTML5 tags as valid -_-.
		if ( $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html ) ) {
			return new HTMLBlock( null, $doc, 'root' );
		}

		$this->log_error( $post );
		return null;
	}

	/**
	 * Resolver for content blocks.
	 *
	 * @param  WP_Post $post Post to parse content blocks for.
	 * @return array|null
	 */
	public function resolve( $post ) {
		// First check for cached blocks.
		$cache = get_post_meta( $post->ID, $this->post_meta_field, true );
		if ( $this->enable_cache && isset( $cache['version'] ) && $this->version === $cache['version'] ) {
			return $cache['blocks'];
		}

		// Set a default return value.
		$cache_input = [
			'blocks'  => [],
			'date'    => time(),
			'version' => $this->version,
		];

		// This is where the magic happens.
		$parsed_content = $this->parse_post_content( $post );

		// If the parsing was successful, create a representation of the "blocks."
		// We don't want to cache / preserve the entire (very large) tree.
		if ( null !== $parsed_content ) {
			$cache_input['blocks'] = array_map( function( $block ) {
				return [
					'attributes' => $block->get_attributes(),
					'innerHtml'  => $block->get_inner_html(),
					'type'       => $block->get_type(),
				];
			}, $parsed_content->get_children() );
		}

		// Unset the tree to allow garbage collection.
		unset( $parsed_content );

		// Allow blocks to be filtered in case further transformation is desired.
		$cache_input['blocks'] = apply_filters( 'graphql_blocks_output', $cache_input['blocks'], $post );

		// Cache the result even if parsing was unsuccessful (to prevent repeating
		// any expensive operations).
		if ( $this->enable_cache ) {
			update_post_meta( $post->ID, $this->post_meta_field, $cache_input );
		}

		return $cache_input['blocks'];
	}
}
