<?php
/**
 * Product Rating element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Star rating and review count of the current product. */
class WooProductRating extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-product-rating';
}
