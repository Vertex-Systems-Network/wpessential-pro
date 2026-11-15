<?php
/**
 * Validated opening-hours presentation in configured site timezone.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-local scope.

$wpe_days     = array(
	'monday'    => __( 'Monday', 'wpessential-pro' ),
	'tuesday'   => __( 'Tuesday', 'wpessential-pro' ),
	'wednesday' => __( 'Wednesday', 'wpessential-pro' ),
	'thursday'  => __( 'Thursday', 'wpessential-pro' ),
	'friday'    => __( 'Friday', 'wpessential-pro' ),
	'saturday'  => __( 'Saturday', 'wpessential-pro' ),
	'sunday'    => __( 'Sunday', 'wpessential-pro' ),
);
$wpe_today    = strtolower( wp_date( 'l', time(), wp_timezone() ) );
$wpe_rows     = is_array( $a['rows'] ) ? array_slice( $a['rows'], 0, 30 ) : array();
$wpe_schedule = array();
foreach ( $wpe_rows as $wpe_row ) {
	if ( ! is_array( $wpe_row ) ) {
		continue;
	}
	$wpe_day = sanitize_key( (string) ( $wpe_row['day'] ?? '' ) );
	if ( ! isset( $wpe_days[ $wpe_day ] ) || isset( $wpe_schedule[ $wpe_day ] ) ) {
		continue;
	}
	$wpe_closed = ! empty( $wpe_row['closed'] );
	$wpe_open   = trim( (string) ( $wpe_row['open'] ?? '' ) );
	$wpe_close  = trim( (string) ( $wpe_row['close'] ?? '' ) );
	if ( ! $wpe_closed ) {
		if ( ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $wpe_open ) || ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $wpe_close ) ) {
			continue;
		}
	}
	$wpe_schedule[ $wpe_day ] = array(
		'closed' => $wpe_closed,
		'open'   => $wpe_open,
		'close'  => $wpe_close,
	);
}
$wpe_mode  = 'table' === $a['layout'] ? 'table' : 'list';
$wpe_title = trim( (string) $a['title'] );
?>
<section id="<?php echo esc_attr( $id ); ?>" class="wpe-business-hours is-<?php echo esc_attr( $wpe_mode ); ?>">
	<?php if ( $wpe_title ) : ?>
		<h2 class="wpe-business-hours__title"><?php echo esc_html( $wpe_title ); ?></h2>
	<?php endif; ?>
	<?php if ( 'table' === $wpe_mode ) : ?>
		<table class="wpe-business-hours__table">
			<caption class="screen-reader-text"><?php echo esc_html( '' !== $wpe_title ? $wpe_title : __( 'Business hours', 'wpessential-pro' ) ); ?></caption>
			<tbody>
	<?php else : ?>
		<dl class="wpe-business-hours__list">
	<?php endif; ?>
		<?php foreach ( $wpe_days as $wpe_day => $wpe_label ) : ?>
			<?php if ( ! isset( $wpe_schedule[ $wpe_day ] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<?php
			$wpe_entry   = $wpe_schedule[ $wpe_day ];
			$wpe_current = $a['showToday'] && $wpe_today === $wpe_day;
			$wpe_hours   = $wpe_entry['closed'] ? (string) $a['closedText'] : $wpe_entry['open'] . ' – ' . $wpe_entry['close'];
			?>
			<?php if ( 'table' === $wpe_mode ) : ?>
				<tr<?php echo $wpe_current ? ' class="is-today"' : ''; ?>>
					<th scope="row" class="wpe-business-hours__day"><?php echo esc_html( $wpe_label ); ?>
					<?php if ( $wpe_current ) : ?>
						<span class="screen-reader-text"><?php esc_html_e( '(today)', 'wpessential-pro' ); ?></span>
					<?php endif; ?></th>
					<td class="wpe-business-hours__time"><?php echo esc_html( $wpe_hours ); ?></td>
				</tr>
			<?php else : ?>
				<div class="wpe-business-hours__row<?php echo $wpe_current ? ' is-today' : ''; ?>">
					<dt class="wpe-business-hours__day"><?php echo esc_html( $wpe_label ); ?>
						<?php if ( $wpe_current ) : ?>
							<span class="screen-reader-text"><?php esc_html_e( '(today)', 'wpessential-pro' ); ?></span>
						<?php endif; ?>
					</dt>
					<dd class="wpe-business-hours__time"><?php echo esc_html( $wpe_hours ); ?></dd>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php if ( 'table' === $wpe_mode ) : ?>
			</tbody>
		</table>
	<?php else : ?>
		</dl>
	<?php endif; ?>
	<?php if ( $a['showTimezone'] ) : ?>
		<p class="wpe-business-hours__timezone"><?php echo esc_html( wp_timezone_string() ); ?></p>
	<?php endif; ?>
</section>
