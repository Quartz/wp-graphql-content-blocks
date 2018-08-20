<?php
/**
 * Plugin Name: WPGraphQL Content Blocks
 * Plugin URI: https://github.com/Quartz/wp-graphql-content-blocks
 * Description: Parses, validates, and simplifies into blocks in WPGraphQL.
 * Author: James Shakespeare, Chris Zarate, Quartz
 * Version: 0.1.8
 * Author URI: https://qz.com/
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks;

require_once( dirname( __FILE__ ) . '/src/data/fields.php' );
require_once( dirname( __FILE__ ) . '/src/parser/block.php' );
require_once( dirname( __FILE__ ) . '/src/parser/embed-block.php' );
require_once( dirname( __FILE__ ) . '/src/parser/html-block.php' );
require_once( dirname( __FILE__ ) . '/src/parser/shortcode-block.php' );
require_once( dirname( __FILE__ ) . '/src/parser/text-block.php' );
require_once( dirname( __FILE__ ) . '/src/parser/validator.php' );
require_once( dirname( __FILE__ ) . '/src/types/enums/block-name-enum-type.php' );
require_once( dirname( __FILE__ ) . '/src/types/shared/block-definitions.php' );
require_once( dirname( __FILE__ ) . '/src/types/block-attribute-type.php' );
require_once( dirname( __FILE__ ) . '/src/types/block-type.php' );

use WPGraphQL\Extensions\ContentBlocks\Data\Fields;
use WPGraphQL\Extensions\ContentBlocks\Types\BlockDefinitions;

add_action( 'graphql_init', array( new Fields(), 'init' ), 10, 0 );
add_action( 'graphql_get_schema', array( new BlockDefinitions(), 'setup' ), 10, 0 );
