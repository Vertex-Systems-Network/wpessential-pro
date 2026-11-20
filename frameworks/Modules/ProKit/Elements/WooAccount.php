<?php
/**
 * My Account element (Gutenberg block and Elementor widget).
 *
 * @package WPEssential Pro
 */

namespace WPEssential\Modules\ProKit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Customer account: login/register, dashboard, orders, downloads, addresses and details. */
class WooAccount extends \WPEssential\Platform\Kit\Widget {
	const KIT = 'woo-account';
}
