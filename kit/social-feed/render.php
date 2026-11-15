<?php
/**
 * Curated social posts or public WordPress post cards.
 * Does not claim to sync unapproved third-party social APIs.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Kit renderer scope.

$wpe_rows         = array();
$wpe_source       = 'wp-posts' === $a['source'] ? 'wp-posts' : 'curated';
$wpe_platforms    = array( 'instagram', 'facebook', 'linkedin', 'tiktok', 'youtube', 'pinterest', 'x', 'other' );
$wpe_date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
if ( 'wp-posts' === $wpe_source ) {
	$wpe_type        = sanitize_key( (string) $a['postType'] );
	$wpe_type_object = get_post_type_object( $wpe_type );
	if ( ! $wpe_type_object || ! $wpe_type_object->public ) {
		$wpe_type = 'post';
	}
	$wpe_query = new WP_Query(
		array(
			'post_type'           => $wpe_type,
			'post_status'         => 'publish',
			'posts_per_page'      => min( 18, max( 1, (int) $a['postCount'] ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	while ( $wpe_query->have_posts() ) {
		$wpe_query->the_post();
		$wpe_rows[] = array(
			'platform' => 'WordPress',
			'caption'  => get_the_excerpt(),
			'username' => get_the_author(),
			'date'     => get_the_date( 'Y-m-d' ),
			'url'      => get_permalink(),
			'image'    => get_the_post_thumbnail(
				get_the_ID(),
				'medium_large',
				array(
					'class'   => 'wpe-social-feed__image',
					'loading' => 'lazy',
				)
			),
			'title'    => get_the_title(),
		);
	}
	wp_reset_postdata();
} else {
	$wpe_items = is_array( $a['items'] ) ? array_slice( $a['items'], 0, 30 ) : array();
	foreach ( $wpe_items as $wpe_item ) {
		if ( ! is_array( $wpe_item ) ) {
			continue;
		}
		$wpe_platform = in_array( (string) ( $wpe_item['platform'] ?? '' ), $wpe_platforms, true ) ? $wpe_item['platform'] : 'other';
		$wpe_href     = esc_url( trim( (string) ( $wpe_item['url'] ?? '' ) ), array( 'https' ) );
		if ( '' !== $wpe_href && ( 'https' !== wp_parse_url( $wpe_href, PHP_URL_SCHEME ) || ! wp_parse_url( $wpe_href, PHP_URL_HOST ) ) ) {
			$wpe_href = '';
		}
		$wpe_date     = trim( (string) ( $wpe_item['date'] ?? '' ) );
		$wpe_date_obj = preg_match( $wpe_date_pattern, $wpe_date ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $wpe_date, wp_timezone() ) : false;
		$wpe_date     = $wpe_date_obj && $wpe_date_obj->format( 'Y-m-d' ) === $wpe_date ? $wpe_date : '';
		$wpe_image    = is_array( $wpe_item['image'] ?? null ) ? $wpe_item['image'] : array();
		$wpe_alt      = trim( (string) ( $wpe_item['caption'] ?? '' ) );
		$wpe_img      = '';
		if ( ! empty( $wpe_image['id'] ) && wp_attachment_is_image( (int) $wpe_image['id'] ) ) {
			$wpe_img = wp_get_attachment_image(
				(int) $wpe_image['id'],
				'medium_large',
				false,
				array(
					'class'   => 'wpe-social-feed__image',
					'alt'     => wp_trim_words( $wpe_alt, 12, '' ),
					'loading' => 'lazy',
				)
			);
		} elseif ( ! empty( $wpe_image['url'] ) ) {
			$wpe_url = esc_url( (string) $wpe_image['url'], array( 'http', 'https' ) );
			if ( $wpe_url ) {
				$wpe_img = '<img class="wpe-social-feed__image" src="' . esc_url( $wpe_url ) . '" alt="' . esc_attr( wp_trim_words( $wpe_alt, 12, '' ) ) . '" loading="lazy" decoding="async">';
			}
		}
		if ( ! $wpe_img && ! trim( $wpe_alt ) ) {
			continue;
		}
		$wpe_rows[] = array(
			'platform' => $wpe_platform,
			'caption'  => $wpe_alt,
			'username' => trim( (string) ( $wpe_item['username'] ?? '' ) ),
			'date'     => $wpe_date,
			'url'      => $wpe_href,
			'image'    => $wpe_img,
			'title'    => '',
		);
	}
}
$wpe_style   = 'minimal' === $a['style'] ? 'minimal' : 'boxed';
$wpe_visible = min( 18, max( 1, (int) $a['initialVisible'] ) );
?>
<section id="<?php echo esc_attr( $id ); ?>" class="wpe-social-feed is-<?php echo esc_attr( $wpe_style ); ?>" data-wpe-feed="1" data-initial="<?php echo esc_attr( (string) $wpe_visible ); ?>">
	<?php if ( '' !== trim( (string) $a['title'] ) ) : ?>
		<h2 class="wpe-social-feed__heading"><?php echo esc_html( (string) $a['title'] ); ?></h2>
	<?php endif; ?>
	<?php if ( $wpe_rows ) : ?>
		<div class="wpe-social-feed__grid">
			<?php foreach ( $wpe_rows as $wpe_row ) : ?>
				<article class="wpe-social-feed__card" data-wpe-feed-item>
					<?php if ( $a['showImage'] && $wpe_row['image'] ) : ?>
						<div class="wpe-social-feed__media"><?php echo wp_kses_post( $wpe_row['image'] ); ?></div>
					<?php endif; ?>
					<div class="wpe-social-feed__body">
						<p class="wpe-social-feed__platform"><?php echo esc_html( ucfirst( $wpe_row['platform'] ) ); ?></p>
						<?php if ( $wpe_row['title'] ) : ?>
							<h3 class="wpe-social-feed__title"><?php echo esc_html( $wpe_row['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( $a['showAccount'] && $wpe_row['username'] ) : ?>
							<p class="wpe-social-feed__account"><?php echo esc_html( $wpe_row['username'] ); ?></p>
						<?php endif; ?>
						<?php if ( $a['showDate'] && $wpe_row['date'] ) : ?>
							<time datetime="<?php echo esc_attr( $wpe_row['date'] ); ?>"><?php echo esc_html( $wpe_row['date'] ); ?></time>
						<?php endif; ?>
						<?php if ( $a['showCaption'] && $wpe_row['caption'] ) : ?>
							<p class="wpe-social-feed__caption"><?php echo esc_html( $wpe_row['caption'] ); ?></p>
						<?php endif; ?>
						<?php if ( $wpe_row['url'] && trim( (string) $a['buttonText'] ) ) : ?>
							<a class="wpe-social-feed__link" href="<?php echo esc_url( $wpe_row['url'] ); ?>"<?php echo 'curated' === $wpe_source ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( (string) $a['buttonText'] ); ?></a>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<?php if ( count( $wpe_rows ) > $wpe_visible ) : ?>
			<button type="button" class="wpe-social-feed__more" hidden><?php echo esc_html( (string) $a['loadMoreText'] ); ?></button>
		<?php endif; ?>
	<?php else : ?>
		<p class="wpe-social-feed__empty"><?php esc_html_e( 'No updates yet.', 'wpessential-pro' ); ?></p>
	<?php endif; ?>
</section>
