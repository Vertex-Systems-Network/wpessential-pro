<?php
/**
 * Timezone-aware Countdown renderer.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Included in Kit template scope.

$wpe_raw   = trim( (string) $a['target'] );
$wpe_date  = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $wpe_raw, wp_timezone() );
$wpe_valid = $wpe_date && $wpe_date->format( 'Y-m-d H:i:s' ) === $wpe_raw;
if ( ! $wpe_valid ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		echo '<p class="wpe-countdown__empty">' . esc_html__( 'Enter a valid date in YYYY-MM-DD HH:MM:SS format.', 'wpessential-pro' ) . '</p>';
	}
	return;
}
$wpe_time  = $wpe_date->getTimestamp();
$wpe_style = in_array( $a['style'], array( 'cards', 'minimal', 'inline' ), true ) ? $a['style'] : 'cards';
$wpe_units = array(
	'days'    => __( 'Days', 'wpessential-pro' ),
	'hours'   => __( 'Hours', 'wpessential-pro' ),
	'minutes' => __( 'Minutes', 'wpessential-pro' ),
	'seconds' => __( 'Seconds', 'wpessential-pro' ),
);
$wpe_shown = array(
	'days'    => ! empty( $a['showDays'] ),
	'hours'   => ! empty( $a['showHours'] ),
	'minutes' => ! empty( $a['showMinutes'] ),
	'seconds' => ! empty( $a['showSeconds'] ),
);
// With every unit switched off the timer would be blank, so show them all.
if ( ! in_array( true, $wpe_shown, true ) ) {
	$wpe_shown = array_fill_keys( array_keys( $wpe_shown ), true );
}
?>
<section id="<?php echo esc_attr( $id ); ?>" class="wpe-countdown is-<?php echo esc_attr( $wpe_style ); ?>" data-wpe-countdown="1" data-end="<?php echo esc_attr( (string) ( $wpe_time * 1000 ) ); ?>" aria-label="<?php esc_attr_e( 'Countdown timer', 'wpessential-pro' ); ?>">
	<?php if ( '' !== trim( (string) $a['heading'] ) ) : ?>
		<h3 class="wpe-countdown__heading"><?php echo esc_html( (string) $a['heading'] ); ?></h3>
	<?php endif; ?>
	<div class="wpe-countdown__units" role="timer" aria-live="off">
		<?php foreach ( $wpe_units as $wpe_key => $wpe_label ) : ?>
			<?php if ( $wpe_shown[ $wpe_key ] ) : ?>
				<div class="wpe-countdown__unit" data-unit="<?php echo esc_attr( $wpe_key ); ?>">
					<span class="wpe-countdown__number">--</span>
					<span class="wpe-countdown__label"><?php echo esc_html( $wpe_label ); ?></span>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<p class="wpe-countdown__fallback"><?php esc_html_e( 'Target date:', 'wpessential-pro' ); ?> <time datetime="<?php echo esc_attr( $wpe_date->format( 'c' ) ); ?>"><?php echo esc_html( $wpe_date->format( 'Y-m-d H:i:s T' ) ); ?></time></p>
	<p class="wpe-countdown__completed" role="status" hidden><?php echo esc_html( (string) $a['expiredText'] ); ?></p>
</section>
