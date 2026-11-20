<?php
/**
 * Related Products element.
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
$wpe_limit = min( 12, max( 1, (int) $a['count'] ) );
$wpe_ids = array();
if ( 'crosssells' === $a['source'] ) {
	$wpe_ids = WC()->cart ? WC()->cart->get_cross_sells() : array();
} else {
	$wpe_product = WooContext::product( $a );
	if ( ! $wpe_product ) {
		return;
	}
	$wpe_ids = 'upsells' === $a['source'] ? $wpe_product->get_upsell_ids() : wc_get_related_products( $wpe_product->get_id(), $wpe_limit );
}
$wpe_items = array_filter( array_map( 'wc_get_product', array_slice( array_map( 'intval', $wpe_ids ), 0, $wpe_limit ) ), static fn( $p ) => $p instanceof WC_Product && $p->is_visible() );
if ( ! $wpe_items ) {
	return;
}
?>
<section id="<?php echo esc_attr( $id ); ?>" class="wpe-woo-related">
	<?php if ( '' !== trim( (string) $a['heading'] ) ) : ?><h2 class="wpe-woo-related__heading"><?php echo esc_html( $a['heading'] ); ?></h2><?php endif; ?>
	<div class="wpe-woo-grid"><?php foreach ( $wpe_items as $wpe_item ) { echo WooContext::card( $wpe_item, $a ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?></div>
</section>
