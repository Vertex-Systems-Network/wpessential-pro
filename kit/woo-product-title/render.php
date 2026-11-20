<?php
/**
 * Product Title element.
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
$wpe_product = WooContext::product( $a );
if ( ! $wpe_product ) {
	return;
}
$wpe_tag = in_array( $a['tag'], array( 'h1', 'h2', 'h3', 'p' ), true ) ? $a['tag'] : 'h1';
$wpe_name = esc_html( $wpe_product->get_name() );
if ( $a['link'] ) {
	$wpe_name = '<a href="' . esc_url( (string) get_permalink( $wpe_product->get_id() ) ) . '">' . $wpe_name . '</a>';
}
printf( '<%1$s id="%2$s" class="wpe-woo-title">%3$s</%1$s>', esc_html( $wpe_tag ), esc_attr( $id ), $wpe_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
