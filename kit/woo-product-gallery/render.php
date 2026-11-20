<?php
/**
 * Product Gallery element.
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
$wpe_ids = array_values( array_filter( array_merge( array( (int) $wpe_product->get_image_id() ), array_map( 'intval', $wpe_product->get_gallery_image_ids() ) ) ) );
if ( ! $wpe_ids ) {
	echo wp_kses_post( wc_placeholder_img( 'woocommerce_single' ) );
	return;
}
?>
<div id="<?php echo esc_attr( $id ); ?>" class="wpe-woo-gallery is-thumbs-<?php echo esc_attr( sanitize_key( $a['thumbs'] ) ); ?>" data-wpe-woo-gallery>
	<div class="wpe-woo-gallery__main">
		<?php foreach ( $wpe_ids as $wpe_i => $wpe_img ) : ?>
			<a href="<?php echo esc_url( (string) wp_get_attachment_image_url( $wpe_img, 'full' ) ); ?>" class="wpe-woo-gallery__slide"<?php echo $wpe_i ? ' hidden' : ''; ?>><?php echo wp_get_attachment_image( $wpe_img, 'woocommerce_single', false, array( 'loading' => $wpe_i ? 'lazy' : 'eager' ) ); ?></a>
		<?php endforeach; ?>
	</div>
	<?php if ( 'none' !== $a['thumbs'] && count( $wpe_ids ) > 1 ) : ?>
		<div class="wpe-woo-gallery__thumbs" role="tablist" aria-label="<?php esc_attr_e( 'Product images', 'wpessential-pro' ); ?>">
			<?php foreach ( $wpe_ids as $wpe_i => $wpe_img ) : ?>
				<button type="button" role="tab" aria-selected="<?php echo $wpe_i ? 'false' : 'true'; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: image number. */ __( 'Image %d', 'wpessential-pro' ), $wpe_i + 1 ) ); ?>"><?php echo wp_get_attachment_image( $wpe_img, 'woocommerce_gallery_thumbnail' ); ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
