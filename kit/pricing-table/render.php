<?php
/**
 * Pricing plan presentation; all prices are display-only.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Render template local scope.

$wpe_classes  = array(
	'wpe-pricing-table',
	'is-' . ( in_array( $a['appearance'], array( 'boxed', 'minimal', 'accent' ), true ) ? $a['appearance'] : 'boxed' ),
	! empty( $a['featured'] ) ? 'is-featured' : '',
);
$wpe_href     = esc_url( trim( (string) $a['buttonUrl'] ), array( 'http', 'https' ) );
$wpe_features = is_array( $a['features'] ) ? array_slice( $a['features'], 0, 40 ) : array();
$wpe_plan     = trim( (string) $a['plan'] );
?>
<section id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( implode( ' ', array_filter( $wpe_classes ) ) ); ?>"<?php echo '' !== $wpe_plan ? ' aria-label="' . esc_attr( $wpe_plan ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped accessible plan name. ?>>
	<?php if ( ! empty( $a['featured'] ) && '' !== trim( (string) $a['ribbon'] ) ) : ?>
		<p class="wpe-pricing-table__ribbon"><?php echo esc_html( (string) $a['ribbon'] ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $wpe_plan ) : ?>
		<h3 class="wpe-pricing-table__heading"><?php echo esc_html( $wpe_plan ); ?></h3>
	<?php endif; ?>
	<?php if ( '' !== trim( (string) $a['subtitle'] ) ) : ?>
		<p class="wpe-pricing-table__subtitle"><?php echo esc_html( (string) $a['subtitle'] ); ?></p>
	<?php endif; ?>
	<div class="wpe-pricing-table__price">
		<?php if ( '' !== trim( (string) $a['oldPrice'] ) ) : ?>
			<del class="wpe-pricing-table__old"><?php echo esc_html( (string) $a['oldPrice'] ); ?></del>
		<?php endif; ?>
		<span class="wpe-pricing-table__amount"><?php echo esc_html( (string) $a['currency'] . (string) $a['price'] ); ?></span>
		<?php if ( '' !== trim( (string) $a['period'] ) ) : ?>
			<span class="wpe-pricing-table__period"><?php echo esc_html( (string) $a['period'] ); ?></span>
		<?php endif; ?>
	</div>
	<?php if ( $wpe_features ) : ?>
		<ul class="wpe-pricing-table__features">
			<?php foreach ( $wpe_features as $wpe_feature ) : ?>
				<?php
				$wpe_label    = is_array( $wpe_feature ) ? trim( (string) ( $wpe_feature['text'] ?? '' ) ) : '';
				$wpe_included = is_array( $wpe_feature ) && ! empty( $wpe_feature['included'] );
				if ( '' === $wpe_label ) {
					continue;
				}
				?>
				<li class="<?php echo $wpe_included ? 'is-included' : 'is-excluded'; ?>">
					<span aria-hidden="true" class="wpe-pricing-table__marker"><?php echo $wpe_included ? '&#10003;' : '&#8211;'; ?></span>
					<span><?php echo esc_html( $wpe_label ); ?>
						<?php if ( ! $wpe_included ) : ?>
							<span class="screen-reader-text"><?php esc_html_e( '(not included)', 'wpessential-pro' ); ?></span>
						<?php endif; ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( '' !== $wpe_href && '' !== trim( (string) $a['buttonText'] ) ) : ?>
		<a class="wpe-pricing-table__button" href="<?php echo esc_url( $wpe_href ); ?>"<?php echo $a['newTab'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( (string) $a['buttonText'] ); ?></a>
	<?php endif; ?>
	<?php if ( '' !== trim( (string) $a['footer'] ) ) : ?>
		<p class="wpe-pricing-table__footer"><?php echo esc_html( (string) $a['footer'] ); ?></p>
	<?php endif; ?>
</section>
