<?php
/**
 * Add to Cart element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Quantity, variations and the add-to-cart button for the current product (simple, variable, grouped, external). */
class WooAddToCart extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-add-to-cart';
}
