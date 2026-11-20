<?php
/**
 * Product Rating element.
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
if ( (float) $wpe_product->get_average_rating() <= 0 ) {
	return;
}
?>
<div id="<?php echo esc_attr( $id ); ?>" class="wpe-woo-rating"><?php echo wp_kses_post( wc_get_rating_html( (float) $wpe_product->get_average_rating(), $wpe_product->get_rating_count() ) ); ?><?php if ( $a['showCount'] ) : ?><a class="wpe-woo-rating__count" href="<?php echo esc_url( get_permalink( $wpe_product->get_id() ) . '#reviews' ); ?>"><?php echo esc_html( sprintf( /* translators: %d: reviews. */ _n( '%d review', '%d reviews', $wpe_product->get_review_count(), 'wpessential-pro' ), $wpe_product->get_review_count() ) ); ?></a><?php endif; ?></div>
