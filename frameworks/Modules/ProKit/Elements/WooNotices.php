<?php
/**
 * Store Notices element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** WooCommerce messages (added to cart, errors, info) where you place it. */
class WooNotices extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-notices';
}
