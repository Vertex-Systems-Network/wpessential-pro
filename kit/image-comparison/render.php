<?php
/**
 * Before/after image comparison. Static split is visible without JS.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Scoped template renderer.

$wpe_images = array();
foreach ( array( 'before', 'after' ) as $wpe_slot ) {
	$wpe_data = is_array( $a[ $wpe_slot ] ) ? $a[ $wpe_slot ] : array();
	$wpe_alt  = trim( (string) $a[ $wpe_slot . 'Alt' ] );
	$wpe_tag  = '';
	if ( ! empty( $wpe_data['id'] ) && wp_attachment_is_image( (int) $wpe_data['id'] ) ) {
		$wpe_tag = wp_get_attachment_image(
			(int) $wpe_data['id'],
			'large',
			false,
			array(
				'class'   => 'wpe-image-comparison__image',
				'alt'     => $wpe_alt,
				'loading' => 'lazy',
			)
		);
	} elseif ( ! empty( $wpe_data['url'] ) ) {
		$wpe_src = esc_url( (string) $wpe_data['url'], array( 'https', 'http' ) );
		if ( $wpe_src ) {
			$wpe_tag = '<img class="wpe-image-comparison__image" src="' . esc_url( $wpe_src ) . '" alt="' . esc_attr( $wpe_alt ) . '" loading="lazy" decoding="async">';
		}
	}
	$wpe_images[ $wpe_slot ] = $wpe_tag;
}
if ( ! $wpe_images['before'] || ! $wpe_images['after'] ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		echo '<p class="wpe-image-comparison__empty">' . esc_html__( 'Select both images to compare.', 'wpessential-pro' ) . '</p>';
	}
	return;
}
$wpe_start = min( 95, max( 5, (int) $a['start'] ) );
?>
<figure id="<?php echo esc_attr( $id ); ?>" class="wpe-image-comparison" data-wpe-comparison="1" style="--wpek-compare-progress:<?php echo esc_attr( (string) $wpe_start ); ?>%">
	<div class="wpe-image-comparison__frame">
		<div class="wpe-image-comparison__after">
			<?php echo $wpe_images['after']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted WP attachment or safely built escaped image tag. ?>
		</div>
		<div class="wpe-image-comparison__before">
			<?php echo $wpe_images['before']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted WP attachment or safely built escaped image tag. ?>
		</div>
		<?php if ( $a['overlayLabels'] ) : ?>
			<span class="wpe-image-comparison__label is-before" aria-hidden="true"><?php echo esc_html( (string) $a['beforeLabel'] ); ?></span>
			<span class="wpe-image-comparison__label is-after" aria-hidden="true"><?php echo esc_html( (string) $a['afterLabel'] ); ?></span>
		<?php endif; ?>
		<span class="wpe-image-comparison__divider" aria-hidden="true"></span>
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-slider"><?php echo esc_html( (string) $a['beforeLabel'] . ' / ' . (string) $a['afterLabel'] ); ?></label>
		<input id="<?php echo esc_attr( $id ); ?>-slider" class="wpe-image-comparison__slider" type="range" min="0" max="100" step="1" value="<?php echo esc_attr( (string) $wpe_start ); ?>" aria-valuetext="<?php echo esc_attr( (string) $wpe_start . '% ' . (string) $a['beforeLabel'] ); ?>" hidden>
	</div>
	<?php if ( '' !== trim( (string) $a['caption'] ) ) : ?>
		<figcaption class="wpe-image-comparison__caption"><?php echo esc_html( (string) $a['caption'] ); ?></figcaption>
	<?php endif; ?>
</figure>
