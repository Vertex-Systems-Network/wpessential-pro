<?php
/**
 * Product Short Description element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The short description of the current product. */
class WooProductExcerpt extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-product-excerpt';
}
