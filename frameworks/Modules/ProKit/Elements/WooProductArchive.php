<?php
/**
 * Product Archive element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Product loop of the current shop, category, tag or search page with result count, sorting and pagination. */
class WooProductArchive extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-product-archive';
}
