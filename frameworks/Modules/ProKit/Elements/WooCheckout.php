<?php
/**
 * Checkout element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Checkout: billing, shipping, payment and order review; also the order-received screen. */
class WooCheckout extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-checkout';
}
