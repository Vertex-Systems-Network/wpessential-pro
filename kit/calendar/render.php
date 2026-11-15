<?php
/**
 * Curated event list and month calendar. Site timezone is authoritative.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Scoped Kit renderer.

$wpe_events   = array();
$wpe_timezone = wp_timezone();
$wpe_now      = new DateTimeImmutable( 'now', $wpe_timezone );
$wpe_items    = is_array( $a['events'] ) ? array_slice( $a['events'], 0, 100 ) : array();
foreach ( $wpe_items as $wpe_event ) {
	if ( ! is_array( $wpe_event ) ) {
		continue;
	}
	$wpe_date_raw = trim( (string) ( $wpe_event['date'] ?? '' ) );
	$wpe_date     = DateTimeImmutable::createFromFormat( '!Y-m-d', $wpe_date_raw, $wpe_timezone );
	if ( ! $wpe_date || $wpe_date->format( 'Y-m-d' ) !== $wpe_date_raw ) {
		continue;
	}
	$wpe_all_day = ! empty( $wpe_event['allDay'] );
	$wpe_time    = trim( (string) ( $wpe_event['time'] ?? '' ) );
	if ( ! $wpe_all_day ) {
		$wpe_datetime = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $wpe_date_raw . ' ' . $wpe_time, $wpe_timezone );
		if ( ! $wpe_datetime || $wpe_datetime->format( 'Y-m-d H:i' ) !== $wpe_date_raw . ' ' . $wpe_time ) {
			continue;
		}
	} else {
		$wpe_datetime = $wpe_date;
	}
	$wpe_url      = esc_url( trim( (string) ( $wpe_event['url'] ?? '' ) ), array( 'http', 'https' ) );
	$wpe_events[] = array(
		'title'       => trim( (string) ( $wpe_event['title'] ?? '' ) ),
		'date'        => $wpe_date_raw,
		'datetime'    => $wpe_datetime,
		'all_day'     => $wpe_all_day,
		'location'    => trim( (string) ( $wpe_event['location'] ?? '' ) ),
		'description' => trim( (string) ( $wpe_event['description'] ?? '' ) ),
		'url'         => $wpe_url,
	);
}
usort(
	$wpe_events,
	static function ( $wpe_a, $wpe_b ) {
		return $wpe_a['datetime']->getTimestamp() <=> $wpe_b['datetime']->getTimestamp();
	}
);
$wpe_month_raw = trim( (string) $a['month'] );
$wpe_month     = preg_match( '/^\d{4}-\d{2}$/', $wpe_month_raw ) ? DateTimeImmutable::createFromFormat( '!Y-m', $wpe_month_raw, $wpe_timezone ) : false;
if ( ! $wpe_month || $wpe_month->format( 'Y-m' ) !== $wpe_month_raw ) {
	$wpe_month = $wpe_now->modify( 'first day of this month' )->setTime( 0, 0 );
}
$wpe_view          = 'month' === $a['view'] ? 'month' : 'list';
$wpe_limit         = min( 50, max( 1, (int) $a['limit'] ) );
$wpe_title         = trim( (string) $a['heading'] );
$wpe_start_weekday = min( 6, max( 0, (int) get_option( 'start_of_week', 0 ) ) );
?>
<section id="<?php echo esc_attr( $id ); ?>" class="wpe-calendar is-<?php echo esc_attr( $wpe_view ); ?>">
	<?php if ( $wpe_title ) : ?>
		<h2 class="wpe-calendar__heading"><?php echo esc_html( $wpe_title ); ?></h2>
	<?php endif; ?>
	<?php if ( 'month' === $wpe_view ) : ?>
		<h3 class="wpe-calendar__month-heading"><?php echo esc_html( wp_date( 'F Y', $wpe_month->getTimestamp(), $wpe_timezone ) ); ?></h3>
		<div class="wpe-calendar__table-wrap" role="region" aria-label="<?php esc_attr_e( 'Monthly events calendar', 'wpessential-pro' ); ?>" tabindex="0">
			<table class="wpe-calendar__table">
				<thead><tr>
					<?php for ( $wpe_day_offset = 0; $wpe_day_offset < 7; $wpe_day_offset++ ) : ?>
						<?php $wpe_day_index = ( $wpe_start_weekday + $wpe_day_offset ) % 7; ?>
						<th scope="col"><?php echo esc_html( wp_date( 'D', ( new DateTimeImmutable( '2023-01-01', $wpe_timezone ) )->modify( '+' . $wpe_day_index . ' days' )->getTimestamp(), $wpe_timezone ) ); ?></th>
					<?php endfor; ?>
				</tr></thead>
				<tbody>
					<?php
					$wpe_first_weekday = (int) $wpe_month->format( 'w' );
					$wpe_lead          = ( $wpe_first_weekday - $wpe_start_weekday + 7 ) % 7;
					$wpe_days          = (int) $wpe_month->format( 't' );
					$wpe_cell_count    = (int) ceil( ( $wpe_lead + $wpe_days ) / 7 ) * 7;
					for ( $wpe_cell = 0; $wpe_cell < $wpe_cell_count; $wpe_cell++ ) :
						if ( 0 === $wpe_cell % 7 ) {
							echo '<tr>';
						}
						$wpe_day_number = $wpe_cell - $wpe_lead + 1;
						if ( $wpe_day_number < 1 || $wpe_day_number > $wpe_days ) {
							echo '<td class="is-empty"></td>';
						} else {
							$wpe_day_date = $wpe_month->modify( '+' . ( $wpe_day_number - 1 ) . ' days' )->format( 'Y-m-d' );
							echo '<td><time datetime="' . esc_attr( $wpe_day_date ) . '" class="wpe-calendar__day">' . esc_html( (string) $wpe_day_number ) . '</time>';
							foreach ( $wpe_events as $wpe_event ) {
								if ( $wpe_event['date'] !== $wpe_day_date ) {
									continue;
								}
								echo '<span class="wpe-calendar__cell-event">';
								if ( $wpe_event['url'] ) {
									echo '<a href="' . esc_url( $wpe_event['url'] ) . '">' . esc_html( $wpe_event['title'] ) . '</a>';
								} else {
									echo esc_html( $wpe_event['title'] );
								}
								echo '</span>';
							}
							echo '</td>';
						}
						if ( 6 === $wpe_cell % 7 ) {
							echo '</tr>';
						}
					endfor;
					?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<?php
		$wpe_visible = array();
		foreach ( $wpe_events as $wpe_event ) {
			if ( $a['hidePast'] && $wpe_event['datetime'] < $wpe_now && ! ( $wpe_event['all_day'] && $wpe_event['date'] === $wpe_now->format( 'Y-m-d' ) ) ) {
				continue;
			}
			$wpe_visible[] = $wpe_event;
			if ( count( $wpe_visible ) >= $wpe_limit ) {
				break;
			}
		}
		?>
		<?php if ( $wpe_visible ) : ?>
			<ol class="wpe-calendar__list">
				<?php foreach ( $wpe_visible as $wpe_event ) : ?>
					<li class="wpe-calendar__event">
						<time class="wpe-calendar__date" datetime="<?php echo esc_attr( $wpe_event['datetime']->format( 'c' ) ); ?>">
							<?php echo esc_html( wp_date( get_option( 'date_format' ), $wpe_event['datetime']->getTimestamp(), $wpe_timezone ) ); ?>
							<?php if ( ! $wpe_event['all_day'] ) : ?>
								· <?php echo esc_html( wp_date( get_option( 'time_format' ), $wpe_event['datetime']->getTimestamp(), $wpe_timezone ) ); ?>
							<?php endif; ?>
						</time>
						<h3 class="wpe-calendar__event-title"><?php echo esc_html( $wpe_event['title'] ); ?></h3>
						<?php if ( $a['showLocation'] && $wpe_event['location'] ) : ?>
							<p class="wpe-calendar__location"><?php echo esc_html( $wpe_event['location'] ); ?></p>
						<?php endif; ?>
						<?php if ( $a['showDescription'] && $wpe_event['description'] ) : ?>
							<p class="wpe-calendar__description"><?php echo esc_html( $wpe_event['description'] ); ?></p>
						<?php endif; ?>
						<?php if ( $wpe_event['url'] && trim( (string) $a['linkLabel'] ) ) : ?>
							<a class="wpe-calendar__link" href="<?php echo esc_url( $wpe_event['url'] ); ?>"><?php echo esc_html( (string) $a['linkLabel'] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php else : ?>
			<p class="wpe-calendar__empty"><?php echo esc_html( (string) $a['emptyMessage'] ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</section>
