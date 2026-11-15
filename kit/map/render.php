<?php
/**
 * Privacy-first OpenStreetMap location.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Render template scope.

$wpe_lat = filter_var( trim( (string) $a['latitude'] ), FILTER_VALIDATE_FLOAT );
$wpe_lng = filter_var( trim( (string) $a['longitude'] ), FILTER_VALIDATE_FLOAT );
if ( false === $wpe_lat || false === $wpe_lng || $wpe_lat < -90 || $wpe_lat > 90 || $wpe_lng < -180 || $wpe_lng > 180 ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		echo '<p class="wpe-map__empty">' . esc_html__( 'Enter valid map coordinates.', 'wpessential-pro' ) . '</p>';
	}
	return;
}
$wpe_lat    = (float) $wpe_lat;
$wpe_lng    = (float) $wpe_lng;
$wpe_zoom   = min( 18, max( 2, (int) $a['zoom'] ) );
$wpe_delta  = 0.03 * pow( 2, 12 - $wpe_zoom );
$wpe_bbox   = implode(
	',',
	array(
		(float) max( -180, $wpe_lng - $wpe_delta ),
		(float) max( -90, $wpe_lat - $wpe_delta ),
		(float) min( 180, $wpe_lng + $wpe_delta ),
		(float) min( 90, $wpe_lat + $wpe_delta ),
	)
);
$wpe_coords = number_format( $wpe_lat, 6, '.', '' ) . ',' . number_format( $wpe_lng, 6, '.', '' );
$wpe_href   = 'https://www.openstreetmap.org/?mlat=' . rawurlencode( (string) $wpe_lat ) . '&mlon=' . rawurlencode( (string) $wpe_lng ) . '#map=' . $wpe_zoom . '/' . $wpe_lat . '/' . $wpe_lng;
$wpe_embed  = 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode( $wpe_bbox ) . '&layer=mapnik&marker=' . rawurlencode( $wpe_coords );
$wpe_title  = '' !== trim( (string) $a['title'] ) ? trim( (string) $a['title'] ) : __( 'Map location', 'wpessential-pro' );
?>
<section id="<?php echo esc_attr( $id ); ?>" class="wpe-map" data-wpe-map="1">
	<h3 class="wpe-map__title"><?php echo esc_html( $wpe_title ); ?></h3>
	<div class="wpe-map__frame">
		<div class="wpe-map__placeholder">
			<p class="wpe-map__coordinates"><?php echo esc_html( $wpe_coords ); ?></p>
			<?php if ( '' !== trim( (string) $a['address'] ) ) : ?>
				<address class="wpe-map__address"><?php echo esc_html( (string) $a['address'] ); ?></address>
			<?php endif; ?>
			<?php if ( $a['embed'] ) : ?>
				<p class="wpe-map__privacy"><?php echo esc_html( (string) $a['privacyNote'] ); ?></p>
				<button class="wpe-map__load" type="button" data-map-src="<?php echo esc_url( $wpe_embed ); ?>" data-map-title="<?php echo esc_attr( $wpe_title ); ?>"><?php echo esc_html( (string) $a['consent'] ); ?></button>
			<?php endif; ?>
			<p class="wpe-map__link-wrap"><a class="wpe-map__link" href="<?php echo esc_url( $wpe_href ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $a['linkLabel'] ); ?></a></p>
		</div>
	</div>
</section>
