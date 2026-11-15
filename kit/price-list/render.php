<?php
/**
 * Price List renderer shared by Gutenberg and Elementor.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template variables live inside Kit::template().

$wpe_items   = is_array( $a['items'] ) ? array_slice( $a['items'], 0, 50 ) : array();
$wpe_layout  = in_array( $a['layout'], array( 'rows', 'cards', 'minimal' ), true ) ? $a['layout'] : 'rows';
$wpe_tag     = in_array( $a['titleTag'], array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ), true ) ? $a['titleTag'] : 'h2';
$wpe_heading = trim( wp_strip_all_tags( (string) $a['title'], true ) );
$wpe_cur     = wp_strip_all_tags( (string) $a['currency'], true );
$wpe_suffix  = wp_strip_all_tags( (string) $a['priceSuffix'], true );
$wpe_placed  = array();

foreach ( $wpe_items as $wpe_item ) {
	if ( ! is_array( $wpe_item ) ) {
		continue;
	}
	$wpe_name = trim( wp_strip_all_tags( (string) ( $wpe_item['title'] ?? '' ), true ) );
	$wpe_desc = trim( wp_strip_all_tags( (string) ( $wpe_item['description'] ?? '' ), true ) );
	$wpe_cost = trim( wp_strip_all_tags( (string) ( $wpe_item['price'] ?? '' ), true ) );
	if ( '' === $wpe_name && '' === $wpe_desc && '' === $wpe_cost ) {
		continue;
	}
	$wpe_placed[] = $wpe_item;
}
?>
<section id="<?php echo esc_attr( $id ); ?>" class="wpe-price-list is-<?php echo esc_attr( $wpe_layout ); ?> is-price-<?php echo 'bottom' === $a['priceAlign'] ? 'bottom' : 'end'; ?>">
	<?php if ( '' !== $wpe_heading ) : ?>
		<<?php echo esc_html( $wpe_tag ); ?> class="wpe-price-list__heading"><?php echo esc_html( $wpe_heading ); ?></<?php echo esc_html( $wpe_tag ); ?>>
	<?php endif; ?>

	<?php if ( $wpe_placed ) : ?>
		<ul class="wpe-price-list__items">
			<?php foreach ( $wpe_placed as $wpe_item ) : ?>
				<?php
				$wpe_name  = trim( wp_strip_all_tags( (string) ( $wpe_item['title'] ?? '' ), true ) );
				$wpe_desc  = trim( wp_strip_all_tags( (string) ( $wpe_item['description'] ?? '' ), true ) );
				$wpe_cost  = trim( wp_strip_all_tags( (string) ( $wpe_item['price'] ?? '' ), true ) );
				$wpe_old   = trim( wp_strip_all_tags( (string) ( $wpe_item['oldPrice'] ?? '' ), true ) );
				$wpe_badge = trim( wp_strip_all_tags( (string) ( $wpe_item['badge'] ?? '' ), true ) );
				$wpe_link  = esc_url( trim( (string) ( $wpe_item['link'] ?? '' ) ), array( 'http', 'https' ) );
				$wpe_image = is_array( $wpe_item['image'] ?? null ) ? $wpe_item['image'] : array();
				$wpe_alt   = trim( wp_strip_all_tags( (string) ( $wpe_item['imageAlt'] ?? '' ), true ) );
				$wpe_img   = '';

				if ( ! empty( $a['showImages'] ) ) {
					if ( ! empty( $wpe_image['id'] ) && wp_attachment_is_image( (int) $wpe_image['id'] ) ) {
						$wpe_img = wp_get_attachment_image(
							(int) $wpe_image['id'],
							'medium',
							false,
							array(
								'class'    => 'wpe-price-list__image',
								'alt'      => $wpe_alt,
								'loading'  => 'lazy',
								'decoding' => 'async',
							)
						);
					} elseif ( ! empty( $wpe_image['url'] ) ) {
						$wpe_image_url = esc_url( (string) $wpe_image['url'], array( 'https' ) );
						if ( $wpe_image_url ) {
							$wpe_img = '<img class="wpe-price-list__image" src="' . esc_url( $wpe_image_url ) . '" alt="' . esc_attr( $wpe_alt ) . '" loading="lazy" decoding="async">';
						}
					}
				}
				?>
				<li class="wpe-price-list__item">
					<?php if ( $wpe_img ) : ?>
						<div class="wpe-price-list__media"><?php echo $wpe_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated from WordPress attachment or escaped HTTPS URL. ?></div>
					<?php endif; ?>
					<div class="wpe-price-list__body">
						<div class="wpe-price-list__line">
							<div class="wpe-price-list__details">
								<?php if ( '' !== $wpe_name ) : ?>
									<h3 class="wpe-price-list__name">
										<?php if ( $wpe_link ) : ?>
											<a href="<?php echo esc_url( $wpe_link ); ?>"<?php echo ! empty( $wpe_item['newTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $wpe_name ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $wpe_name ); ?>
										<?php endif; ?>
									</h3>
								<?php endif; ?>
								<?php if ( ! empty( $a['showBadges'] ) && '' !== $wpe_badge ) : ?>
									<span class="wpe-price-list__badge"><?php echo esc_html( $wpe_badge ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( '' !== $wpe_cost ) : ?>
								<div class="wpe-price-list__price">
									<?php if ( '' !== $wpe_old ) : ?>
										<del class="wpe-price-list__old"><?php echo esc_html( $wpe_cur . $wpe_old ); ?></del>
									<?php endif; ?>
									<strong class="wpe-price-list__current"><?php echo esc_html( $wpe_cur . $wpe_cost . $wpe_suffix ); ?></strong>
								</div>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $a['showDescriptions'] ) && '' !== $wpe_desc ) : ?>
							<p class="wpe-price-list__description"><?php echo esc_html( $wpe_desc ); ?></p>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p class="wpe-price-list__empty"><?php echo esc_html( (string) $a['emptyText'] ); ?></p>
	<?php endif; ?>
</section>
