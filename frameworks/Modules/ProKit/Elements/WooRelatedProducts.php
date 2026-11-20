<?php
/**
 * Related Products element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Related products, up-sells or cross-sells of the current product (or cart) as a card grid. */
class WooRelatedProducts extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-related-products';
}
