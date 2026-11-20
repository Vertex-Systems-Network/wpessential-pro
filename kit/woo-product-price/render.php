<?php
/**
 * Product Price element.
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
?>
<div id="<?php echo esc_attr( $id ); ?>" class="wpe-woo-price"><?php echo wp_kses_post( $wpe_product->get_price_html() ); ?><?php if ( $a['showSaleBadge'] && $wpe_product->is_on_sale() ) : ?> <span class="wpe-woo-price__badge"><?php esc_html_e( 'Sale', 'wpessential-pro' ); ?></span><?php endif; ?></div>
