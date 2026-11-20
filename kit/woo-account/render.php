<?php
/**
 * My Account element.
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
	echo '<div class="wpe-woo-page"><p>' . esc_html__( 'My Account appears here on the live page.', 'wpessential-pro' ) . '</p></div>';
	return;
}
echo '<div id="' . esc_attr( $id ) . '" class="wpe-woo-page wpe-woo-account">' . do_shortcode( '[woocommerce_my_account]' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce output.
