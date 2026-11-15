<?php
/**
 * Accessible static timeline. All entries available without scripting.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Included in Kit::template().

$wpe_items  = is_array( $a['items'] ) ? array_slice( $a['items'], 0, 40 ) : array();
$wpe_layout = 'horizontal' === $a['layout'] ? 'horizontal' : 'vertical';
$wpe_side   = 'alternate' === $a['side'] ? 'alternate' : 'left';
?>
<section id="<?php echo esc_attr( $id ); ?>" class="wpe-timeline is-<?php echo esc_attr( $wpe_layout ); ?> is-<?php echo esc_attr( $wpe_side ); ?>">
	<?php if ( '' !== trim( (string) $a['title'] ) ) : ?>
		<h2 class="wpe-timeline__heading"><?php echo esc_html( (string) $a['title'] ); ?></h2>
	<?php endif; ?>
	<?php if ( $wpe_items ) : ?>
		<ol class="wpe-timeline__list">
			<?php foreach ( $wpe_items as $wpe_item ) : ?>
				<?php
				if ( ! is_array( $wpe_item ) ) {
					continue;
				}
				$wpe_image = is_array( $wpe_item['image'] ?? null ) ? $wpe_item['image'] : array();
				$wpe_title = trim( (string) ( $wpe_item['title'] ?? '' ) );
				$wpe_url   = esc_url( trim( (string) ( $wpe_item['link'] ?? '' ) ), array( 'http', 'https' ) );
				$wpe_img   = '';
				if ( $a['showImages'] && ! empty( $wpe_image['id'] ) && wp_attachment_is_image( (int) $wpe_image['id'] ) ) {
					$wpe_img = wp_get_attachment_image(
						(int) $wpe_image['id'],
						'medium_large',
						false,
						array(
							'class'   => 'wpe-timeline__image',
							'alt'     => $wpe_title,
							'loading' => 'lazy',
						)
					);
				} elseif ( $a['showImages'] && ! empty( $wpe_image['url'] ) ) {
					$wpe_src = esc_url( (string) $wpe_image['url'], array( 'http', 'https' ) );
					if ( $wpe_src ) {
						$wpe_img = '<img class="wpe-timeline__image" src="' . esc_url( $wpe_src ) . '" alt="' . esc_attr( $wpe_title ) . '" loading="lazy" decoding="async">';
					}
				}
				?>
				<li class="wpe-timeline__item">
					<?php if ( $a['showDates'] && '' !== trim( (string) ( $wpe_item['date'] ?? '' ) ) ) : ?>
						<p class="wpe-timeline__date"><?php echo esc_html( (string) $wpe_item['date'] ); ?></p>
					<?php endif; ?>
					<?php if ( $wpe_img ) : ?>
						<div class="wpe-timeline__media"><?php echo $wpe_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted WP image or escaped URL. ?></div>
					<?php endif; ?>
					<?php if ( '' !== $wpe_title ) : ?>
						<h3 class="wpe-timeline__title"><?php echo esc_html( $wpe_title ); ?></h3>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) ( $wpe_item['content'] ?? '' ) ) ) : ?>
						<div class="wpe-timeline__content"><?php echo wp_kses_post( (string) $wpe_item['content'] ); ?></div>
					<?php endif; ?>
					<?php if ( $wpe_url && '' !== trim( (string) ( $wpe_item['linkLabel'] ?? '' ) ) ) : ?>
						<a class="wpe-timeline__link" href="<?php echo esc_url( $wpe_url ); ?>"><?php echo esc_html( (string) $wpe_item['linkLabel'] ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php else : ?>
		<p class="wpe-timeline__empty"><?php esc_html_e( 'Add milestones to the timeline.', 'wpessential-pro' ); ?></p>
	<?php endif; ?>
</section>
