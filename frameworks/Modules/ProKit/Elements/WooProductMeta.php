<?php
/**
 * Product Meta element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** SKU, categories and tags of the current product. */
class WooProductMeta extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-product-meta';
}
