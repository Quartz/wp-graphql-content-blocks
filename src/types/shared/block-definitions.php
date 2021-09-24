<?php
/**
 * WPGraphQL Content Blocks shared block type definitions
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks\Types;

/**
 * Define block types for reuse in both the parser and GraphQL types.
 */
class BlockDefinitions {
	/**
	 * Default block definition, extended by the definitions below (or by the
	 * user via a filter).
	 *
	 * @var array
	 */
	private static $defaults = [
		// Should the block be preserved if it has no content / children?
		'allow_empty' => false,

		'attributes'  => [
			// An array of attributes to allow. If defined, all other attributes are
			// removed.
			'allow'    => null,

			// An array of attributes to remove. All other attributes are allowed.
			// Ignored if `allow` is provided.
			'deny'     => [ 'class' ],

			// A list of required attributes. If `null`, no attributes are required.
			// Processing occurs after allow/deny.
			'required' => null,

			// Require there to be at least one valid attribute of any kind for
			// the block to be valid. All attributes in the required array
			// must also be present.
			'require_one_or_more' => false,
		],

		// An array of regular expressions used to determine whether the node
		// matches (only for embeds and shortcodes).
		'regex' => [],

		// Is permitted to exist at the root level of the tree?
		// If false it will be wrapped in a <p> tag.
		'allow_root' => true,

		// Must the block appear at the root-level of the tree?
		// If true it will be hoisted out of its position in the tree and placed at the root level.
		'root_only' => true,

		// Does the block belong to any of these immediate parent block types?
		// If so, override root_only and permit the block to remain nested
		'allowed_parent_types' => [],

		// Override the type to a different value?
		'type' => null,
	];

	/**
	 * Block definitions (extended by defaults).
	 *
	 * NOTE: There is no server-side registration of Gutenberg blocks, so
	 * unfortunately we will just hardcode a list of the known block types and
	 * try to keep it up to date. Users and plugins will need to filter
	 * definitions to add non-core blocks. This is a HUGE issue with Gutenberg:
	 *
	 * https://github.com/WordPress/gutenberg/issues/2751
	 *
	 * @var array
	 */
	private static $definitions = [
		'embed' => [],
		'gutenberg' => [
			'core/paragraph' => [],
			'core/image' => [],
			'core/heading' => [],
			'core/gallery' => [],
			'core/list' => [],
			'core/quote' => [],
			'core/shortcode' => [],
			'core/archives' => [],
			'core/audio' => [],
			'core/button' => [],
			'core/categories' => [],
			'core/code' => [],
			'core/columns' => [],
			'core/column' => [],
			'core/cover-image' => [],
			'core/embed' => [],
			'core-embed/twitter' => [],
			'core-embed/youtube' => [],
			'core-embed/facebook' => [],
			'core-embed/instagram' => [],
			'core-embed/wordpress' => [],
			'core-embed/soundcloud' => [],
			'core-embed/spotify' => [],
			'core-embed/flickr' => [],
			'core-embed/vimeo' => [],
			'core-embed/animoto' => [],
			'core-embed/cloudup' => [],
			'core-embed/collegehumor' => [],
			'core-embed/dailymotion' => [],
			'core-embed/funnyordie' => [],
			'core-embed/hulu' => [],
			'core-embed/imgur' => [],
			'core-embed/issuu' => [],
			'core-embed/kickstarter' => [],
			'core-embed/meetup-com' => [],
			'core-embed/mixcloud' => [],
			'core-embed/photobucket' => [],
			'core-embed/polldaddy' => [],
			'core-embed/reddit' => [],
			'core-embed/reverbnation' => [],
			'core-embed/screencast' => [],
			'core-embed/scribd' => [],
			'core-embed/slideshare' => [],
			'core-embed/smugmug' => [],
			'core-embed/speaker' => [],
			'core-embed/ted' => [],
			'core-embed/tumblr' => [],
			'core-embed/videopress' => [],
			'core-embed/wordpress-tv' => [],
			'core/file' => [],
			'core/freeform' => [],
			'core/html' => [],
			'core/latest-comments' => [],
			'core/latest-posts' => [],
			'core/more' => [],
			'core/nextpage' => [],
			'core/preformatted' => [],
			'core/pullquote' => [],
			'core/separator' => [],
			'core/block' => [],
			'core/spacer' => [],
			'core/subhead' => [],
			'core/table' => [],
			'core/text-columns' => [],
			'core/verse' => [],
			'core/video' => [],
		],
		'html' => [
			'#comment' => [],
			'blockquote' => [],
			'h1' => [],
			'h2' => [],
			'h3' => [],
			'h4' => [],
			'h5' => [],
			'h6' => [],
			'hr' => [],
			'p' => [
				'root_only' => false,
			],
			'pre' => [],
			'ol' => [
				'root_only' => false,
			],
			'table' => [],
			'a' => [
				'attributes' => [
					'deny' => [ 'target' ],
				],
				'root_only' => false,
				'allow_root' => false,
			],
			'abbr' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'audio' => [
				'allow_empty' => true,
				'root_only' => false,
				'allow_root' => false,
			],
			'b' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'br' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'cite' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'code' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'col' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'colgroup' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'dd' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'del' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'dt' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'em' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'figcaption' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'figure' => [
				'root_only' => false,
			],
			'i' => [
				'root_only' => false,
				'allow_root' => false,
				'allow_empty' => true,
			],
			'ins' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'img' => [
				'allowed_parent_types' => [ 'figure', 'a'],
			],
			'li' => [
				'root_only' => false,
				'allow_root' => false,
			],
			's' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'span' => [
				'attributes' => [
					'allow' => [ 'style' ],
					'require_one_or_more' => true,
				],
				'root_only' => false,
				'allow_root' => false,
			],
			'strong' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'sub' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'sup' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'tbody' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'td' => [
				'root_only' => false,
				'allow_root' => false,
				'allow_empty' => true,
			],
			'th' => [
				'root_only' => false,
				'allow_root' => false,
				'allow_empty' => true,
			],
			'thead' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'time' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'tr' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'u' => [
				'root_only' => false,
				'allow_root' => false,
			],
			'ul' => [
				'root_only' => false,
			]
		],
		'shortcode' => [],
	];

	/**
	 * Set up values used by getter methods.
	 *
	 * @return void
	 */
	public static function setup() {
		self::$definitions['embed']     = self::get_embed_definitions();
		self::$definitions['shortcode'] = self::get_shortcode_definitions();

		// Allow user to extend / modify the block definitions.
		//
		// @param array Definitions array
		// @since 0.1.0
		self::$definitions = apply_filters( 'graphql_blocks_definitions', self::$definitions );

		// Extend each block definition with the defaults.
		foreach ( self::$definitions as $type => $definition ) {
			foreach ( $definition as $name => $block ) {
				self::$definitions[ $type ][ $name ] = array_replace_recursive( self::$defaults, $block );
			}
		}
	}

	/**
	 * Has a block been defined with the block and node type names?
	 *
	 * @param  string $block_type 'embed', 'gutenberg', 'html', or 'shortcode'.
	 * @param  string $node_type  The string representation of the node name (e.g., tag name).
	 * @return array  $definition The block's definition
	 */
	public static function get_block_definition( $block_type, $node_type ) {
		$blocks = self::get_blocks( $block_type );
		if ( $blocks && array_key_exists( $node_type, $blocks ) ) {
			return $blocks[ $node_type ];
		}

		return null;
	}

	/**
	 * Get root block types by block type.
	 *
	 * @param  string $block_type 'embed', 'html', or 'shortcode'.
	 * @return array
	 */
	public static function get_blocks( $block_type ) {
		if ( isset( self::$definitions[ $block_type ] ) ) {
			return self::$definitions[ $block_type ];
		}

		return null;
	}

	/**
	 * Get root block types (for WPGraphQL Enum Type).
	 *
	 * @return array
	 */
	public static function get_root_blocks() {
		$root_blocks = [];

		foreach ( self::$definitions as $type => $definition ) {
			foreach ( $definition as $name => $block ) {
				if ( ! isset( $block['allow_root'] ) || true !== $block['allow_root'] ) {
					continue;
				}

				$root_blocks[] = [
					'name' => $name,
					'type' => $type,
				];
			}
		}

		return $root_blocks;
	}

	/**
	 * Adds a start-of-line anchor (^) to the supplied regex if it does not
	 * already have one.
	 *
	 * @param  string $regex The regex to check and on which to enforce an anchor.
	 * @return string
	 **/
	private static function enforce_regex_anchor( $regex ) {
		if ( '^' !== substr( $regex, 1, 1 ) ) {
			$regex = substr_replace( $regex, '^', 1, 0 );
		}

		return $regex;
	}

	/**
	 * Embed definitions are compiled at run time using the embed handlers that
	 * have been registered.
	 *
	 * @return array
	 */
	private static function get_embed_definitions() {
		global $wp_embed;

		// Array to hold embed definitions.
		$definitions = [];

		// First loop through regular embeds. These are more like shortcodes, but
		// we want to represent them as embeds in content blocks.
		if ( isset( $wp_embed->handlers ) ) {
			foreach ( $wp_embed->handlers as $handlers ) {
				foreach ( $handlers as $tag => $handler ) {
					// Only support regex-based handlers.
					if ( ! isset( $handler['regex'] ) ) {
						continue;
					}

					$anchored_regex = self::enforce_regex_anchor( $handler['regex'] );
					$definitions[ self::get_safe_name( 'embed_' . $tag ) ] = [
						'regex' => [ $anchored_regex ],
					];
				}
			}
		}

		// Get OEmbed providers.
		// @todo Can we do this without using private API?
		$wp_oembed = _wp_oembed_get_object();

		// Loop through OEmbed providers.
		if ( isset( $wp_oembed->providers ) ) {
			foreach ( $wp_oembed->providers as $regex => $provider ) {

				$anchored_regex = self::enforce_regex_anchor( $regex );

				// Only support regex-based handlers.
				if ( true !== $provider[1] ) {
					continue;
				}

				// OEmbed providers aren't really named, so we'll use the endpoint as
				// the key, effectively grouping oembeds with the same enpoint. The
				// user can filter the names if they don't like them (and who would).
				$name = self::get_safe_name( 'embed_' . preg_replace( '#^https?://#', '', $provider[0] ) );

				// If the provider has already been added, just push the regex in.
				if ( isset( $definitions[ $name ]['regex'] ) ) {
					$definitions[ $name ]['regex'][] = $anchored_regex;
					continue;
				}

				$definitions[ $name ] = [
					'regex' => [ $anchored_regex ],
				];
			}
		}

		return $definitions;
	}

	/**
	 * Generate a safe / sanitized name from an enum value.
	 *
	 * @param  string $value Enum value.
	 * @return string
	 */
	public static function get_safe_name( $value ) {
		$safe_name = strtoupper( preg_replace( '#[^A-z0-9]#', '_', $value ) );

		// Enum names must start with a letter. Enum names should not start with an
		// underscores. While technically allowed, WPGraphQL transforms that into
		// two underscores and enums starting with two underscores are reserved for
		// internal types.
		if ( preg_match( '#^[^A-Z]#', $safe_name ) ) {
			$safe_name = preg_replace( '#^[^A-Z]+#', 'SAFE_', $safe_name );
		}

		return $safe_name;
	}

	/**
	 * Shortcode definitions are compiled at run time using the shortcodes
	 * handlers that have been registered.
	 *
	 * @return array
	 */
	private static function get_shortcode_definitions() {
		global $shortcode_tags;

		// Create a new associative array where the keys are the shortcode tags.
		return array_reduce( array_keys( $shortcode_tags ), function ( $result, $tag ) {
			$result[ 'shortcode_' . $tag ] = [
				'allow_empty' => true,
			];

			return $result;
		}, [] );
	}
}
