<?php
/**
 * Product Title element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The current product name as a heading, optionally linked. */
class WooProductTitle extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-product-title';
}
