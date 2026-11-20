<?php
/**
 * Cart element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The cart page: items, quantities, coupons, totals and checkout button. */
class WooCart extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-cart';
}
