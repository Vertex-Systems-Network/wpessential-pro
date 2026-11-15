<?php
/**
 * Posts grid renderer. Included by Kit::template() with $a and $id.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-local variables.
$wpe_caller_post = $GLOBALS['post'] ?? null;
$wpe_type   = sanitize_key( (string) $a['postType'] );
$wpe_object = get_post_type_object( $wpe_type );
if ( ! $wpe_object || ! is_post_type_viewable( $wpe_object ) ) {
	$wpe_type = 'post';
}
$wpe_per_page   = min( 24, max( 1, (int) $a['postsPerPage'] ) );
$wpe_tax        = sanitize_key( (string) $a['taxonomy'] );
$wpe_tax_object = $wpe_tax ? get_taxonomy( $wpe_tax ) : false;
$wpe_paged_key  = 'wpe_pg_' . substr( md5( wp_json_encode( array( $a['postType'], $a['taxonomy'], $a['terms'], $a['orderBy'], $a['order'], $a['postsPerPage'], $a['offset'], $a['cssClass'] ?? '', $a['excludeCurrent'], $a['excludeCurrent'] ? get_the_ID() : 0, $id ?? '' ) ) ), 0, 8 );
$wpe_page       = ! empty( $_GET[ $wpe_paged_key ] ) ? max( 1, absint( wp_unslash( $_GET[ $wpe_paged_key ] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
$wpe_query_args = array(
	'post_type'           => $wpe_type,
	'post_status'         => 'publish',
	'has_password'        => false,
	'posts_per_page'      => $wpe_per_page,
	'offset'              => min( 100, max( 0, (int) $a['offset'] ) ) + ( $wpe_page - 1 ) * $wpe_per_page,
	'orderby'             => in_array( $a['orderBy'], array( 'date', 'modified', 'title', 'menu_order', 'comment_count' ), true ) ? $a['orderBy'] : 'date',
	'order'               => 'ASC' === $a['order'] ? 'ASC' : 'DESC',
	'ignore_sticky_posts' => true,
	'no_found_rows'       => ! $a['showPagination'],
);
if ( $a['excludeCurrent'] && get_the_ID() && get_post_type( get_the_ID() ) === $wpe_type ) {
	$wpe_query_args['post__not_in'] = array( get_the_ID() );
}
if ( $wpe_tax_object && in_array( $wpe_type, (array) $wpe_tax_object->object_type, true ) && trim( (string) $a['terms'] ) ) {
	$wpe_terms = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', (string) $a['terms'] ) ) ) );
	if ( $wpe_terms ) {
		$wpe_query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Explicit taxonomy filter.
			array(
				'taxonomy' => $wpe_tax,
				'field'    => 'slug',
				'terms'    => $wpe_terms,
			),
		); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Explicit taxonomy filter.
	}
}
$wpe_posts = new WP_Query( $wpe_query_args );
$wpe_tag   = in_array( $a['titleTag'], array( 'h2', 'h3', 'h4', 'p' ), true ) ? $a['titleTag'] : 'h3';
$wpe_class = 'wpe-posts-grid is-' . sanitize_key( $a['cardStyle'] ) . ' is-hover-' . sanitize_key( $a['hover'] ) . ' is-image-hover-' . sanitize_key( $a['imageHover'] );
$wpe_size  = in_array( $a['imageSize'], array( 'medium', 'medium_large', 'large', 'full' ), true ) ? $a['imageSize'] : 'large';
?>
<div id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $wpe_class ); ?>">
	<?php if ( $wpe_posts->have_posts() ) : ?>
		<div class="wpe-posts-grid__items">
			<?php while ( $wpe_posts->have_posts() ) : ?>
				<?php $wpe_posts->the_post(); ?>
				<article <?php post_class( 'wpe-posts-grid__card' ); ?>>
					<?php if ( $a['showImage'] && has_post_thumbnail() ) : ?>
						<a class="wpe-posts-grid__image" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Post title. */ __( 'Read %s', 'wpessential-pro' ), get_the_title() ) ); ?>">
							<?php echo get_the_post_thumbnail( get_the_ID(), $wpe_size, array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress image markup. ?>
						</a>
					<?php endif; ?>
					<div class="wpe-posts-grid__body">
						<?php
						if ( $a['showTaxonomy'] && $wpe_tax_object && in_array( $wpe_type, (array) $wpe_tax_object->object_type, true ) ) {
							$wpe_post_terms = get_the_terms( get_the_ID(), $wpe_tax );
							if ( ! is_wp_error( $wpe_post_terms ) && $wpe_post_terms ) {
								echo '<span class="wpe-posts-grid__term">' . esc_html( $wpe_post_terms[0]->name ) . '</span>';
							}
						}
						?>
						<?php if ( $a['showDate'] || $a['showAuthor'] ) : ?>
							<div class="wpe-posts-grid__meta">
								<?php if ( $a['showDate'] ) : ?>
									<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
								<?php endif; ?>
								<?php if ( $a['showAuthor'] ) : ?>
									<span class="wpe-posts-grid__author"><?php echo esc_html( get_the_author() ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<<?php echo esc_html( $wpe_tag ); ?> class="wpe-posts-grid__title"><a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a></<?php echo esc_html( $wpe_tag ); ?>>
						<?php if ( $a['showExcerpt'] ) : ?>
							<p class="wpe-posts-grid__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), min( 80, max( 5, (int) $a['excerptWords'] ) ) ) ); ?></p>
						<?php endif; ?>
						<?php if ( $a['showReadMore'] ) : ?>
							<a class="wpe-posts-grid__read-more" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Post title. */ __( 'Read more about %s', 'wpessential-pro' ), get_the_title() ) ); ?>"><?php echo esc_html( (string) $a['readMoreText'] ); ?><span aria-hidden="true"> →</span></a>
						<?php endif; ?>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php
		if ( $a['showPagination'] ) {
			$wpe_total = max( 0, (int) $wpe_posts->found_posts - min( 100, max( 0, (int) $a['offset'] ) ) );
			$wpe_max   = (int) ceil( $wpe_total / $wpe_per_page );
			if ( $wpe_max > 1 ) {
				echo '<nav class="wpe-posts-grid__pagination" aria-label="' . esc_attr__( 'Posts pagination', 'wpessential-pro' ) . '">';
				for ( $wpe_i = 1, $wpe_limit = min( $wpe_max, 100 ); $wpe_i <= $wpe_limit; $wpe_i++ ) {
					$wpe_url = add_query_arg( $wpe_paged_key, $wpe_i );
					echo '<a href="' . esc_url( $wpe_url ) . '"' . ( $wpe_i === $wpe_page ? ' aria-current="page"' : '' ) . '>' . esc_html( (string) $wpe_i ) . '</a>';
				}
				echo '</nav>';
			}
		}
		?>
	<?php else : ?>
		<p class="wpe-posts-grid__empty"><?php echo esc_html( (string) $a['emptyText'] ); ?></p>
	<?php endif; ?>
</div>
<?php
$GLOBALS['post'] = $wpe_caller_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the enclosing loop's post.
if ( $wpe_caller_post instanceof WP_Post ) {
	setup_postdata( $wpe_caller_post );
}
