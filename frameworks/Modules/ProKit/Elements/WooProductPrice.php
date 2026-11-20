<?php
/**
 * Product Price element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Price of the current product, with sale price and range for variable products. */
class WooProductPrice extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-product-price';
}
