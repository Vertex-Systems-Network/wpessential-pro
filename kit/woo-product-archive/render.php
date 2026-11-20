<?php
/**
 * Product Archive element.
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
$wpe_main = ( is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) && ! WooContext::preview();
if ( $wpe_main ) {
	global $wp_query;
	$wpe_query = $wp_query;
} else {
	$wpe_query = new WP_Query( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => min( 48, max( 1, (int) $a['perPage'] ) ), 'paged' => max( 1, (int) get_query_var( 'paged' ) ) ) );
}
?>
<div id="<?php echo esc_attr( $id ); ?>" class="wpe-woo-archive">
	<?php if ( $wpe_query->have_posts() ) : ?>
		<?php if ( $a['showCount'] || ( $a['showOrdering'] && $wpe_main ) ) : ?>
			<div class="wpe-woo-archive__bar">
				<?php if ( $a['showCount'] ) : ?><p class="wpe-woo-archive__count"><?php echo esc_html( sprintf( /* translators: %d: products. */ _n( '%d product', '%d products', (int) $wpe_query->found_posts, 'wpessential-pro' ), (int) $wpe_query->found_posts ) ); ?></p><?php endif; ?>
				<?php if ( $a['showOrdering'] && $wpe_main ) { woocommerce_catalog_ordering(); } ?>
			</div>
		<?php endif; ?>
		<div class="wpe-woo-grid">
			<?php foreach ( $wpe_query->posts as $wpe_post ) { $wpe_item = wc_get_product( $wpe_post ); if ( $wpe_item && $wpe_item->is_visible() ) { echo WooContext::card( $wpe_item, $a ); } } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
		</div>
		<?php if ( $a['showPagination'] && $wpe_query->max_num_pages > 1 ) : ?>
			<nav class="wpe-woo-archive__pages" aria-label="<?php esc_attr_e( 'Products pages', 'wpessential-pro' ); ?>"><?php echo wp_kses_post( (string) paginate_links( array( 'total' => (int) $wpe_query->max_num_pages, 'current' => max( 1, (int) get_query_var( 'paged' ) ) ) ) ); ?></nav>
		<?php endif; ?>
	<?php else : ?>
		<p class="wpe-woo-archive__empty"><?php echo esc_html( $a['emptyMessage'] ); ?></p>
	<?php endif; ?>
</div>
