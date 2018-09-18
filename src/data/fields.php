<?php
/**
 * WPGraphQL Content Blocks Fields
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Data;

use WPGraphQL;
use WPGraphQL\Extensions\ContentBlocks\Parser\GutenbergBlock;
use WPGraphQL\Extensions\ContentBlocks\Types\BlockType;
use function WPGraphQL\Extensions\ContentBlocks\Parser\create_root_block;

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
	private $version = '0.4.0';

	/**
	 * Add actions and filters.
	 */
	public function init() {
		add_action( 'do_graphql_request', array( $this, 'add_field_filters' ), 10, 0 );
		add_action( 'do_graphql_request', array( $this, 'update_settings' ), 10, 0 );
		add_filter( 'save_post', array( $this, 'clear_cache' ), 10, 1 );
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

	public function update_settings() {
		// Whether to enable caching in post meta.
		//
		// @param bool Whether cache should be enabled.
		// @since 0.1.0
		$this->enable_cache = boolval( apply_filters( 'graphql_blocks_enable_cache', $this->enable_cache ) );

		// Get a filtered plugin "user" version. We will combine our version and
		// the user's into a semver with patch (though we don't validate it in any
		// way, it's just a string). This lets both the plugin and the user bust
		// cache independently.
		//
		// @param string Version string
		// @since 0.2.0
		$user_version = strval( apply_filters( 'graphql_blocks_version', '1' ) );
		$this->version = "{$this->version}-{$user_version}";
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
		// Strip HTML comments.
		$html = preg_replace( '/(?=<!--)([\s\S]*?)-->/is', '', $html );

		// Run a subset of the_content filters on the text.
		$html = trim( apply_filters( 'convert_chars', wpautop( $html ) ) );

		// Remove any remaining linebreaks now that we have been converted to HTML
		// (leaving them in will result in the creation of empty text nodes).
		$html = preg_replace( '/\n/', '', $html );

		// Escape HTML inside shortcode tags. This prevents DOMDocument from splitting
		// shortcode contents into separate nodes. We can unescape the HTML if we need
		// to parse the shortcode later.
		$shortcode_regex = get_shortcode_regex();
		$pattern = '/' . $shortcode_regex . '/s';
		return preg_replace_callback( $pattern, function( $matches ) {
			return htmlspecialchars( $matches[0] );
		}, $html );
	}

	/**
	 * Parse the content of a post and return blocks.
	 *
	 * @param  WP_Post $post Post to parse.
	 * @return array|null
	 */
	private function parse_post_content( $post ) {
		// Prepare the HTML for parsing.
		$html = $this->prepare_html( $post->post_content );
		if ( empty( $html ) ) {
			return null;
		}

		$root_block = create_root_block( $html );
		if ( $root_block ) {
			return $root_block->get_children();
		}

		$this->log_error( $post );
		return null;
	}

	/**
	 * Parse the Gutenberg blocks of a post and return our flavor of blocks.
	 *
	 * @param  WP_Post $post Post to parse.
	 * @return array|null
	 */
	private function parse_post_gutenberg_blocks( $post ) {
		// Check for Gutenberg functions (these could change before release).
		if ( ! function_exists( 'has_blocks' ) || ! function_exists( 'gutenberg_parse_blocks' ) ) {
			return null;
		}

		// Check if the post has Gutenberg blocks. This is just a regex on
		// post_content under the hood. Fun, right?
		if ( ! has_blocks( $post ) ) {
			return null;
		}

		// Use Gutenberg's function to parse the blocks. The function often returns
		// empty "spacer" blocks because ... well, I don't know why. Remove them.
		$blocks = array_filter( gutenberg_parse_blocks( $post->post_content ), function ( $block ) {
			return ! empty( $block['blockName'] );
		} );

		// Pass to our class that will extract internals we want.
		$blocks = array_map( function ( $block ) {
			return new GutenbergBlock( $block );
		}, $blocks );

		// Because this isn't HTML, we don't have a single block at the apex that
		// performs validation of its children. Check validity manually.
		return array_filter( $blocks, function ( $block ) {
			return $block->validator->is_valid();
		} );
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

		// First, check to see if the post has Gutenberg blocks.
		$blocks = $this->parse_post_gutenberg_blocks( $post );

		// If that failed (post does not support Gutenberg, Gutenberg is not
		// installed, etc.), then fire up the DOMDocument chainsaw.
		if ( ! is_array( $blocks ) ) {
			$blocks = $this->parse_post_content( $post );
		}

		// If the parsing was successful, create a representation of the "blocks."
		// We don't want to cache / preserve the entire (very large) tree.
		if ( is_array( $blocks ) ) {
			$cache_input['blocks'] = array_map( function( $block ) {
				return [
					'attributes'     => $block->get_attributes(),
					'attributes_raw' => $block->get_raw_attributes(), // This will not be represented in GraphQL output.
					'connections'    => array(), // User must filter and implement this themselves.
					'innerHtml'      => $block->get_inner_html(),
					'tagName'        => $block->get_tag_name(),
					'type'           => $block->get_type(),
				];
			}, $blocks );
		}

		// Unset the tree to allow garbage collection.
		unset( $blocks );

		// Allow blocks to be filtered in case further transformation is desired.
		//
		// @param array   Array of blocks
		// @param WP_Post WP post object
		// @since 0.1.8
		$cache_input['blocks'] = apply_filters( 'graphql_blocks_output', $cache_input['blocks'], $post );

		// Cache the result even if parsing was unsuccessful (to prevent repeating
		// any expensive operations).
		if ( $this->enable_cache ) {
			update_post_meta( $post->ID, $this->post_meta_field, $cache_input );
		}

		return $cache_input['blocks'];
	}
}
