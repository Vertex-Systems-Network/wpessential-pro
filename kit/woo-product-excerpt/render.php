<?php
/**
 * Product Short Description element.
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
$wpe_text = $wpe_product->get_short_description();
if ( '' === trim( $wpe_text ) ) {
	return;
}
?>
<div id="<?php echo esc_attr( $id ); ?>" class="wpe-woo-excerpt"><?php echo wp_kses_post( wpautop( $wpe_text ) ); ?></div>
