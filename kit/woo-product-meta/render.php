<?php
/**
 * Product Meta element.
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
$wpe_rows = array();
if ( $a['showSku'] && $wpe_product->get_sku() ) {
	$wpe_rows[ __( 'SKU', 'wpessential-pro' ) ] = esc_html( $wpe_product->get_sku() );
}
if ( $a['showCategories'] ) {
	$wpe_rows[ __( 'Category', 'wpessential-pro' ) ] = (string) wc_get_product_category_list( $wpe_product->get_id(), ', ' );
}
if ( $a['showTags'] ) {
	$wpe_rows[ __( 'Tags', 'wpessential-pro' ) ] = (string) wc_get_product_tag_list( $wpe_product->get_id(), ', ' );
}
$wpe_rows = array_filter( $wpe_rows );
if ( ! $wpe_rows ) {
	return;
}
?>
<dl id="<?php echo esc_attr( $id ); ?>" class="wpe-woo-meta is-<?php echo esc_attr( 'inline' === $a['layout'] ? 'inline' : 'stack' ); ?>">
	<?php foreach ( $wpe_rows as $wpe_label => $wpe_value ) : ?>
		<div><dt class="wpe-woo-meta__label"><?php echo esc_html( $wpe_label ); ?></dt> <dd class="wpe-woo-meta__value"><?php echo wp_kses_post( $wpe_value ); ?></dd></div>
	<?php endforeach; ?>
</dl>
