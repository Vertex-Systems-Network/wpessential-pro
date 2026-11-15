<?php
/**
 * Accessible charts using validated values; no scripts or charting libraries.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Local template variables.
$wpe_type  = in_array( $a['type'], array( 'bars', 'columns', 'donut' ), true ) ? $a['type'] : 'bars';
$wpe_limit = min( 20, max( 1, absint( $a['limit'] ) ) );
$wpe_rows  = array();
$wpe_sum   = 0.0;
$wpe_max   = 0.0;
foreach ( array_slice( (array) $a['items'], 0, $wpe_limit ) as $wpe_item ) {
	if ( ! is_array( $wpe_item ) || ! is_numeric( $wpe_item['value'] ?? null ) ) {
		continue;
	}
	$wpe_value = (float) $wpe_item['value'];
	if ( ! is_finite( $wpe_value ) || $wpe_value < 0 || $wpe_value > 1000000000 ) {
		continue;
	}
	$wpe_label = trim( wp_strip_all_tags( (string) ( $wpe_item['label'] ?? '' ), true ) );
	if ( ! $wpe_label ) {
		continue;
	}
	$wpe_color  = (string) ( $wpe_item['color'] ?? 'primary' );
	$wpe_color  = in_array( $wpe_color, array( 'primary', 'secondary', 'accent', 'tertiary', 'contrast' ), true ) ? $wpe_color : 'primary';
	$wpe_rows[] = array(
		'label' => $wpe_label,
		'value' => $wpe_value,
		'color' => $wpe_color,
	);
	$wpe_sum   += $wpe_value;
	$wpe_max    = max( $wpe_max, $wpe_value );
}
$wpe_title = trim( (string) $a['title'] );
?>
<figure id="<?php echo esc_attr( $id ); ?>" class="wpe-chart is-<?php echo esc_attr( $wpe_type ); ?>">
	<?php if ( $wpe_title ) : ?>
			<figcaption class="wpe-chart__title"><?php echo esc_html( $wpe_title ); ?></figcaption>
			<?php endif; ?>
	<?php if ( $wpe_rows ) : ?>
		<?php if ( 'donut' === $wpe_type ) : ?>
			<div class="wpe-chart__donut">
				<svg viewBox="0 0 100 100" aria-hidden="true" focusable="false">
					<?php $wpe_offset = 0.0; ?>
					<?php foreach ( $wpe_rows as $wpe_row ) : ?>
						<?php $wpe_share = $wpe_sum > 0 ? 100 * $wpe_row['value'] / $wpe_sum : 0; ?>
						<circle cx="50" cy="50" r="40" fill="none" stroke="var(--wp--preset--color--<?php echo esc_attr( $wpe_row['color'] ); ?>,currentColor)" stroke-width="16" pathLength="100" stroke-dasharray="<?php echo esc_attr( round( $wpe_share, 5 ) ); ?> <?php echo esc_attr( round( 100 - $wpe_share, 5 ) ); ?>" stroke-dashoffset="<?php echo esc_attr( round( -$wpe_offset, 5 ) ); ?>" transform="rotate(-90 50 50)"/>
						<?php $wpe_offset += $wpe_share; ?>
					<?php endforeach; ?>
				</svg>
				<?php if ( ! empty( $a['showValues'] ) ) : ?>
			<strong class="wpe-chart__total"><?php echo esc_html( number_format_i18n( $wpe_sum, 0 ) ); ?></strong>
			<?php endif; ?>
			</div>
		<?php endif; ?>
		<ul class="wpe-chart__series">
			<?php foreach ( $wpe_rows as $wpe_row ) : ?>
				<?php $wpe_percent = $wpe_max > 0 ? round( 100 * $wpe_row['value'] / $wpe_max, 5 ) : 0; ?>
				<li class="wpe-chart__item" style="--wpek-chart-pct:<?php echo esc_attr( $wpe_percent ); ?>%;--wpek-chart-color:var(--wp--preset--color--<?php echo esc_attr( $wpe_row['color'] ); ?>,currentColor)">
					<div class="wpe-chart__meta"><span class="wpe-chart__label"><?php echo esc_html( $wpe_row['label'] ); ?></span><?php if ( ! empty( $a['showValues'] ) ) : ?>
						<span class="wpe-chart__value"><?php echo esc_html( number_format_i18n( $wpe_row['value'], 2 ) ); ?></span>
						<?php endif; ?></div>
					<?php if ( 'donut' !== $wpe_type ) : ?>
			<div class="wpe-chart__track" aria-hidden="true"><span class="wpe-chart__bar"></span></div>
			<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( ! empty( $a['showTable'] ) ) : ?>
			<table class="wpe-chart__data screen-reader-text">
				<caption><?php echo esc_html( $wpe_title ? $wpe_title : __( 'Chart values', 'wpessential-pro' ) ); ?></caption>
				<thead><tr><th scope="col"><?php esc_html_e( 'Category', 'wpessential-pro' ); ?></th><th scope="col"><?php esc_html_e( 'Value', 'wpessential-pro' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $wpe_rows as $wpe_row ) : ?>
						<tr><th scope="row"><?php echo esc_html( $wpe_row['label'] ); ?></th><td><?php echo esc_html( number_format_i18n( $wpe_row['value'], 2 ) ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Add numeric categories to display a chart.', 'wpessential-pro' ); ?></p>
	<?php endif; ?>
</figure>
