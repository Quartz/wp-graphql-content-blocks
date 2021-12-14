<?php
/**
 * WPGraphQL Content Blocks
 *
 * @package WPGraphQL Content Blocks
 */

namespace WPGraphQL\Extensions\ContentBlocks;

use WPGraphQL\Extensions\ContentBlocks\Data\Fields;
use WPGraphQL\Extensions\ContentBlocks\Types\BlockDefinitions;

/**
 * Include required classes and add plugin hooks.
 */
class Plugin {
	/**
	 * Hook into WPGraphQL actions and filters.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function init() {
		BlockDefinitions::setup();

		$fields = new Fields();
		$fields->init();
	}
}
