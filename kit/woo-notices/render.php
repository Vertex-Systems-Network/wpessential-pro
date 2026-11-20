<?php
/**
 * Store Notices element.
 *
 * @package WPEssential Pro
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Scoped Kit render template.

use WPEssential\Modules\ProKit\WooContext;

if ( ! WooContext::active() ) {
	echo WooContext::missing(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
	return;
}
if ( WooContext::preview() ) {
	echo '<div class="wpe-woo-notices"><div class="woocommerce-message">' . esc_html__( 'Store notices appear here.', 'wpessential-pro' ) . '</div></div>';
	return;
}
if ( ! function_exists( 'wc_print_notices' ) || ! WC()->session ) {
	return;
}
echo '<div id="' . esc_attr( $id ) . '" class="wpe-woo-notices">';
wc_print_notices();
echo '</div>';
