<?php
/**
 * Elementor WooCommerce Products widget.
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Product grid, loaded safely without WooCommerce. */
class Products extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'products';
}
