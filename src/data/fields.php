<?php
/**
 * WPGraphQL Content Blocks Fields
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Data;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL;
use WPGraphQL\AppContext;
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
	private $enable_cache = false;

	/**
	 * A version number attached to the blocks object so that we can ignore
	 * cached output of earlier versions (or later if user downgrades).
	 *
	 * Note that this is not the same as the plugin version, since not every
	 * plugin change requires the post meta cache to be invalidated.
	 *
	 * @var string
	 */
	private $version = '0.7.0';

	/**
	 * Add actions and filters.
	 */
	public function init() {
		add_action( 'graphql_register_types', array( $this, 'register_fields' ), 10, 0 );
		add_filter( 'save_post', array( $this, 'clear_cache' ), 10, 1 );

		$this->update_settings();
	}

	/**
	 * Register fields for blocks.
	 *
	 * @return void
	 */
	public function register_fields() {
		$post_types = array_filter( WPGraphQL::get_allowed_post_types(), function ( $post_type ) {
			return post_type_supports( $post_type, 'editor' );
		} );

		foreach ( $post_types as $post_type ) {
			register_graphql_field(
				get_post_type_object( $post_type )->graphql_single_name,
				$this->field_name,
				[
					'type' => [ 'list_of' => 'Block' ],
					'description' => $this->description,
					'resolve' => [ $this, 'resolve' ],
				]
			);
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
	 * Log a parsing error.
	 *
	 * @param WP_Post $post Post that encountered a parsing error.
	 * @param string $message Optional error message.
	 *
	 * @return void
	 */
	private function log_error( $post, $message = null ) {
		if ( ! function_exists( 'qz_send_to_slackbot' ) ) {
			return;
		}

		if ( empty( $message ) ) {
			$message = sprintf( 'Unable to generate blocks for post %d.', $post->ID );
		}

		do_action( 'log_to_slack', $message, '#testing1', false, 'qzbot' );
	}

	/**
	 * Clear the post meta cache on save_post. We do this even if caching is
	 * disabled so that there is no stale cache data if caching is reenabled.
	 *
	 * @param int $post_id WP_Post Id.
	 *
	 * @return void
	 */
	public function clear_cache( $post_id ) {
		delete_post_meta( $post_id, $this->post_meta_field );
	}

	/**
	 * Run the necessary WP filters on the HTML, remove newlines, and escape HTML
	 * inside shortcode tags.
	 *
	 * @param string $html The HTML to be formatted.
	 *
	 * @return string $decoded_html The formatted HTML
	 **/
	private function prepare_html( $html ) {
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

		return preg_replace_callback( $pattern, function ( $matches ) {
			return htmlspecialchars( $matches[0] );
		}, $html );
	}

	/**
	 * Parse the content of a post and return blocks.
	 *
	 * @param WP_Post $post Post to parse.
	 *
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
	 * @param WP_Post $post Post to parse.
	 *
	 * @return array|null
	 */
	private function parse_post_gutenberg_blocks( $post ) {
		// Check for Gutenberg functionality.
		if ( ! function_exists( 'has_blocks' ) ) {
			return null;
		}

		// Check if the post has Gutenberg blocks. This is just a regex on
		// post_content under the hood. Fun, right?
		if ( ! has_blocks( $post ) ) {
			return null;
		}

		// Use Gutenberg's function to parse the blocks. The function often returns
		// empty "spacer" blocks because ... well, I don't know why. Remove them.
		$blocks = array_filter( $this->gutenberg_parse_blocks( $post->post_content ), function ( $block ) {
			return ! empty( $block['blockName'] );
		} );

		// Pass to our class that will extract internals we want.
		$gutenberg_blocks = [];
		foreach ( $blocks as $block_index => $block ) {
			$gutenberg_blocks[] = new GutenbergBlock( $block );
			$gutenberg_blocks = $this->handle_inner_blocks( $gutenberg_blocks, $block, $block_index );
		}

		// Because this isn't HTML, we don't have a single block at the apex that
		// performs validation of its children. Check validity manually.
		return array_filter( $gutenberg_blocks, function ( $gutenberg_block ) {
			return $gutenberg_block->validator->is_valid();
		} );
	}

	/**
	 * Parses blocks out of a content string.
	 *
	 * During pre-core-Gutenberg times, this was an access function available to
	 * everyone. It was removed for some reason in core. Copying from:
	 * https://github.com/WordPress/gutenberg/blob/master/lib/blocks.php
	 *
	 * @param string $content Post content.
	 *
	 * @return array  Array of parsed block objects.
	 */
	private function gutenberg_parse_blocks( $content ) {
		/**
		 * Filter to allow plugins to replace the server-side block parser
		 *
		 * @param string $parser_class Name of block parser class
		 */
		$parser_class = apply_filters( 'block_parser_class', 'WP_Block_Parser' );

		if ( ! class_exists( $parser_class ) ) {
			return array();
		}

		$parser = new $parser_class();

		return $parser->parse( $content );
	}

	/**
	 * Resolve any connections (post IDs) that have been added to a block. We do
	 * this after all filters have been applied and after loading from post meta
	 * cache to avoid serializing post data / classes.
	 *
	 * @param array $block Block data.
	 *
	 * @return array
	 */
	public function get_block_connections( $block ) {
		if ( is_array( $block['connections'] ) ) {
			$block['connections'] = array_filter(
				array_map( function ( $post_id ) {
					$post = get_post( $post_id );

					// No post? Return null so it will be removed by array_filter.
					if ( empty( $post ) ) {
						return null;
					}

					// Support new model layer, if present.
					if ( class_exists( '\\WPGraphQL\\Model\\Post' ) ) {
						return new \WPGraphQL\Model\Post( $post );
					}

					return $post;
				}, $block['connections'] )
			);
		}

		return $block;
	}

	/**
	 * Get content blocks for a post.
	 *
	 * @param \WP_Post $post Post to parse content blocks for.
	 *
	 * @return array|null
	 */
	public function get_blocks_for_post( \WP_Post $post ) {
		// First check for cached blocks.
		$cache = get_post_meta( $post->ID, $this->post_meta_field, true );
		if ( $this->enable_cache && isset( $cache['version'] ) && $this->version === $cache['version'] ) {
			return $cache;
		}

		// Set a default return value.
		$cache_input = [
			'blocks' => [],
			'date' => time(),
			'version' => $this->version,
		];

		// Compute the post's relay ID. We'll use it to construct the block's relay ID.
		$post_relay_id = Relay::toGlobalId( get_post_type_object( $post->post_type )->graphql_single_name, $post->ID );

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
			$cache_input['blocks'] = array_map( function ( $block, $block_index ) use ( $post_relay_id ) {
				return [
					'attributes' => $block->get_attributes(),
					'attributes_raw' => $block->get_raw_attributes(), // This will not be represented in GraphQL output.
					'connections' => array(), // User must filter and implement this themselves.
					'id' => Relay::toGlobalId( 'block', "{$post_relay_id}|{$block_index}" ),
					'innerHtml' => $block->get_inner_html(),
					'renderedHtml' => $block->get_rendered_content(),
					'tagName' => $block->get_tag_name(),
					'type' => $block->get_type(),
					'parent_id' => $block->get_parent_id( $post_relay_id )
				];
			}, $blocks, array_keys( $blocks ) );
		}

		// Unset the tree to allow garbage collection.
		unset( $blocks );

		// Allow blocks to be filtered in case further transformation is desired.
		// Results of this operation will be cached.
		//
		// @param array   Array of blocks
		// @param WP_Post WP post object
		// @since 0.1.8
		$cache_input['blocks'] = apply_filters( 'graphql_blocks_output', $cache_input['blocks'], $post );

		// Cache the result even if parsing was unsuccessful (to prevent repeating
		// any expensive operations). Don't cache unpublished posts.
		if ( $this->enable_cache && 'publish' === $post->post_status ) {
			update_post_meta( $post->ID, $this->post_meta_field, $cache_input );
		}

		return $cache_input;
	}

	/**
	 * Resolver for content blocks.
	 *
	 * @param mixed $post Post to parse content blocks for. Can be
	 *                              WP_Post or WPGraphQL\Model\Post.
	 * @param array $args Array of query args.
	 * @param AppContext $context Request context.
	 * @param ResolveInfo $info Information about field resolution.
	 *
	 * @return array|null
	 */
	public function resolve( $post, $args, AppContext $context, ResolveInfo $info ) {
		// WPGraphQL introduced a model layer that attempts to respect WordPress
		// caps and restricts access to some fields. One of those fields is (raw)
		// post_content. This next line looks crazy, but it allows us to get the
		// post ID from the model (WPGraphQL\Model\Post) and gain access to the
		// actual post (WP_Post). In older versions this will be a bit redundant but
		// won't hurt anything.
		$post = get_post( $post->ID );

		// Filter the post that is passed as input to our block parser. This allows
		// us to modify the post data if we want.
		//
		// @param WP_Post     WP post object
		// @param array       Array of query args
		// @param AppContext  Request context
		// @param ResolveInfo Information about field resolution
		// @since 0.6.4
		$post = apply_filters( 'graphql_blocks_get_post', $post, $args, $context, $info );

		$blocks = $this->get_blocks_for_post( $post );

		// Establish connections with blocks and remove all data except the blocks
		// themselves.
		$blocks = array_map( [ $this, 'get_block_connections' ], $blocks['blocks'] );

		// Allow cached blocks to be filtered to allow runtime decisions.
		//
		// WARNING: This filter will run every time the blocks field is
		// resolved and should only be used when the operation is not cacheable
		// (e.g., an authorization check). Otherwise, use graphql_blocks_output
		// to take advantage of caching.
		//
		// @param array       Array of blocks
		// @param WP_Post     WP post object
		// @param array       Array of query args
		// @param AppContext  Request context
		// @param ResolveInfo Information about field resolution
		// @since 0.5.0
		return apply_filters( 'graphql_blocks_cached_output', $blocks, $post, $args, $context, $info );
	}

	/**
	 * Resolves innerBlocks recursively.
	 *
	 * @param array $blocks Blocks to be saved within.
	 * @param array $block Block to be checked for inner ones.
	 * @param int|null $parent_block_index Parent block index.
	 *
	 * @return array
	 */
	private function handle_inner_blocks( $blocks, $block, $parent_block_index = null ) {
		if ( ! empty( $block ) && ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$next_block_index = count( $blocks );
				$blocks[$next_block_index] = new GutenbergBlock( $inner_block, $parent_block_index );
				$blocks = $this->handle_inner_blocks( $blocks, $inner_block, $next_block_index );
			}
		}

		return $blocks;
	}
}
