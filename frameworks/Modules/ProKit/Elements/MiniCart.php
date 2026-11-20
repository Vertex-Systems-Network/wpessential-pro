<?php
/**
 * Mini Cart element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Cart icon with item count and total; opens a panel with the cart, updates live when products are added. */
class MiniCart extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'mini-cart';
}
