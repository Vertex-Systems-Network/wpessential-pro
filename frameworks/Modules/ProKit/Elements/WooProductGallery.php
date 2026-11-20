<?php
/**
 * Product Gallery element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Main product image with thumbnails; switches images on click and keyboard, opens full size. */
class WooProductGallery extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-product-gallery';
}
