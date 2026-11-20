<?php
/**
 * Product Tabs element.
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
$wpe_tabs = array();
if ( $a['showDescription'] && '' !== trim( (string) $wpe_product->get_description() ) ) {
	$wpe_tabs['description'] = array( __( 'Description', 'wpessential-pro' ), static fn() => woocommerce_product_description_tab() );
}
if ( $a['showInfo'] && ( $wpe_product->has_attributes() || $wpe_product->has_dimensions() || $wpe_product->has_weight() ) ) {
	$wpe_tabs['info'] = array( __( 'Additional information', 'wpessential-pro' ), static fn() => woocommerce_product_additional_information_tab() );
}
if ( $a['showReviews'] && comments_open( $wpe_product->get_id() ) ) {
	$wpe_tabs['reviews'] = array( sprintf( /* translators: %d: reviews. */ __( 'Reviews (%d)', 'wpessential-pro' ), $wpe_product->get_review_count() ), static fn() => comments_template() );
}
if ( ! $wpe_tabs ) {
	return;
}
$wpe_first = true;
?>
<div id="<?php echo esc_attr( $id ); ?>" class="wpe-woo-tabs" data-wpe-woo-tabs>
	<div class="wpe-woo-tabs__list" role="tablist">
		<?php foreach ( $wpe_tabs as $wpe_key => $wpe_tab ) : ?>
			<button type="button" role="tab" class="wpe-woo-tabs__tab" id="<?php echo esc_attr( $id . '-' . $wpe_key ); ?>-tab" aria-controls="<?php echo esc_attr( $id . '-' . $wpe_key ); ?>" aria-selected="<?php echo $wpe_first ? 'true' : 'false'; ?>"<?php echo $wpe_first ? '' : ' tabindex="-1"'; ?>><?php echo esc_html( $wpe_tab[0] ); ?></button>
			<?php $wpe_first = false; ?>
		<?php endforeach; ?>
	</div>
	<?php $wpe_first = true; foreach ( $wpe_tabs as $wpe_key => $wpe_tab ) : ?>
		<div class="wpe-woo-tabs__panel" role="tabpanel" id="<?php echo esc_attr( $id . '-' . $wpe_key ); ?>" aria-labelledby="<?php echo esc_attr( $id . '-' . $wpe_key ); ?>-tab"<?php echo $wpe_first ? '' : ' hidden'; ?>><?php echo WooContext::with( $wpe_product, $wpe_tab[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce template output. ?></div>
		<?php $wpe_first = false; ?>
	<?php endforeach; ?>
</div>
