<?php
/**
 * Add to Cart element.
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
<div id="<?php echo esc_attr( $id ); ?>" class="wpe-woo-cart<?php echo $a['showQuantity'] ? '' : ' is-no-qty'; ?><?php echo $a['fullWidth'] ? ' is-full' : ''; ?>">
<?php
echo WooContext::with( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce template output.
	$wpe_product,
	static function (): void {
		woocommerce_template_single_add_to_cart();
	}
);
?>
</div>
