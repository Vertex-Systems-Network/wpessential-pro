<?php
/**
 * Progressive-enhancement image carousel.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Included in Kit template scope.

$wpe_valid = array();
$wpe_size  = in_array( $a['size'], array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), true ) ? $a['size'] : 'large';
$wpe_items = is_array( $a['items'] ) ? array_slice( $a['items'], 0, 30 ) : array();
foreach ( $wpe_items as $wpe_item ) {
	if ( ! is_array( $wpe_item ) ) {
		continue;
	}
	$wpe_image = isset( $wpe_item['image'] ) && is_array( $wpe_item['image'] ) ? $wpe_item['image'] : array();
	$wpe_id    = ! empty( $wpe_image['id'] ) && wp_attachment_is_image( (int) $wpe_image['id'] ) ? (int) $wpe_image['id'] : 0;
	$wpe_url   = $wpe_id ? (string) wp_get_attachment_image_url( $wpe_id, 'full' ) : esc_url( (string) ( $wpe_image['url'] ?? '' ), array( 'https', 'http' ) );
	if ( '' === $wpe_url ) {
		continue;
	}
	$wpe_alt  = trim( (string) ( $wpe_item['alt'] ?? '' ) );
	$wpe_href = esc_url( trim( (string) ( $wpe_item['link'] ?? '' ) ), array( 'https', 'http' ) );
	$wpe_img  = $wpe_id ? wp_get_attachment_image(
		$wpe_id,
		$wpe_size,
		false,
		array(
			'class'   => 'wpe-image-carousel__img',
			'alt'     => ( '' !== $wpe_alt ? $wpe_alt : (string) get_post_meta( $wpe_id, '_wp_attachment_image_alt', true ) ),
			'loading' => 'lazy',
		)
	) : '<img class="wpe-image-carousel__img" src="' . esc_url( $wpe_url ) . '" alt="' . esc_attr( $wpe_alt ) . '" loading="lazy" decoding="async">';
	if ( ! $wpe_img ) {
		continue;
	}
	$wpe_valid[] = array(
		'image'   => $wpe_img,
		'href'    => $wpe_href,
		'caption' => trim( (string) ( $wpe_item['caption'] ?? '' ) ),
	);
}
if ( ! $wpe_valid ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		echo '<p class="wpe-image-carousel__empty">' . esc_html__( 'Add images to the carousel.', 'wpessential-pro' ) . '</p>';
	}
	return;
}
$wpe_total    = count( $wpe_valid );
$wpe_interval = min( 15000, max( 2000, (int) $a['interval'] ) );
?>
<section id="<?php echo esc_attr( $id ); ?>" class="wpe-image-carousel" data-wpe-carousel="1" data-loop="<?php echo $a['loop'] ? '1' : '0'; ?>" data-autoplay="<?php echo $a['autoplay'] ? '1' : '0'; ?>" data-pause-hover="<?php echo $a['pauseOnHover'] ? '1' : '0'; ?>" data-interval="<?php echo esc_attr( (string) $wpe_interval ); ?>" aria-roledescription="<?php esc_attr_e( 'carousel', 'wpessential-pro' ); ?>" aria-label="<?php esc_attr_e( 'Image carousel', 'wpessential-pro' ); ?>">
	<div class="wpe-image-carousel__viewport" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable image slides', 'wpessential-pro' ); ?>">
		<div class="wpe-image-carousel__track">
			<?php foreach ( $wpe_valid as $wpe_i => $wpe_slide ) : ?>
				<figure class="wpe-image-carousel__card" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: Slide number. 2: Slide count. */ __( 'Slide %1$d of %2$d', 'wpessential-pro' ), $wpe_i + 1, $wpe_total ) ); ?>">
					<?php if ( $wpe_slide['href'] ) : ?>
						<a href="<?php echo esc_url( $wpe_slide['href'] ); ?>"><?php echo $wpe_slide['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized attachment image / escaped URL. ?></a>
					<?php else : ?>
						<?php echo $wpe_slide['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized attachment image / escaped URL. ?>
					<?php endif; ?>
					<?php if ( $a['showCaptions'] && '' !== $wpe_slide['caption'] ) : ?>
						<figcaption class="wpe-image-carousel__caption"><?php echo esc_html( $wpe_slide['caption'] ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
	<?php if ( $wpe_total > 1 && ( $a['arrows'] || $a['dots'] ) ) : ?>
		<nav class="wpe-image-carousel__controls" aria-label="<?php esc_attr_e( 'Carousel navigation', 'wpessential-pro' ); ?>" hidden>
			<?php if ( $a['arrows'] ) : ?>
				<button type="button" class="wpe-image-carousel__prev" data-carousel-prev aria-label="<?php esc_attr_e( 'Previous slide', 'wpessential-pro' ); ?>">&#8249;</button>
			<?php endif; ?>
			<?php if ( $a['dots'] ) : ?>
				<div class="wpe-image-carousel__dots">
					<?php foreach ( $wpe_valid as $wpe_i => $wpe_slide ) : ?>
						<button type="button" class="wpe-image-carousel__dot" data-carousel-dot="<?php echo esc_attr( (string) $wpe_i ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: Slide number. */ __( 'Go to slide %d', 'wpessential-pro' ), $wpe_i + 1 ) ); ?>"<?php echo 0 === $wpe_i ? ' aria-current="true"' : ''; ?>></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( $a['arrows'] ) : ?>
				<button type="button" class="wpe-image-carousel__next" data-carousel-next aria-label="<?php esc_attr_e( 'Next slide', 'wpessential-pro' ); ?>">&#8250;</button>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</section>
