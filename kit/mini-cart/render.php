<?php
/**
 * Mini Cart element.
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
$wpe_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
?>
<div id="<?php echo esc_attr( $id ); ?>" class="wpe-mini-cart" data-wpe-mini-cart>
	<button type="button" class="wpe-mini-cart__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>-panel" aria-label="<?php echo esc_attr( $a['label'] ); ?>">
		<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/><circle cx="10" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/></svg>
		<span class="wpe-mini-cart__count"><?php echo esc_html( (string) $wpe_count ); ?></span>
		<?php if ( $a['showTotal'] && WC()->cart ) : ?><span class="wpe-mini-cart__total"><?php echo wp_kses_post( WC()->cart->get_cart_subtotal() ); ?></span><?php endif; ?>
	</button>
	<div class="wpe-mini-cart__panel" id="<?php echo esc_attr( $id ); ?>-panel" hidden>
		<div class="widget_shopping_cart_content"><?php if ( WC()->cart && ! WooContext::preview() ) { woocommerce_mini_cart(); } ?></div>
	</div>
</div>
