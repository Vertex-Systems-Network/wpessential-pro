<?php
/**
 * Interactive tooltip, native disclosure and content switcher.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Included within Kit::template().

$wpe_mode   = in_array( $a['mode'], array( 'tooltip', 'showmore', 'switcher' ), true ) ? $a['mode'] : 'tooltip';
$wpe_style  = in_array( $a['style'], array( 'minimal', 'boxed', 'accent' ), true ) ? $a['style'] : 'minimal';
$wpe_hover  = in_array( $a['hover'], array( 'none', 'lift', 'highlight' ), true ) ? $a['hover'] : 'none';
$wpe_class  = array( 'wpe-tooltip', 'is-' . $wpe_mode, 'is-style-' . $wpe_style, 'is-hover-' . $wpe_hover );
$wpe_tip_id = $id . '-information';
$wpe_tip    = trim( (string) $a['tipText'] );
$wpe_first  = trim( (string) $a['firstTitle'] );
$wpe_second = trim( (string) $a['secondTitle'] );
$wpe_first  = '' !== $wpe_first ? $wpe_first : __( 'First option', 'wpessential-pro' );
$wpe_second = '' !== $wpe_second ? $wpe_second : __( 'Second option', 'wpessential-pro' );
?>
<div id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( implode( ' ', $wpe_class ) ); ?>" data-wpe-interactive="<?php echo esc_attr( $wpe_mode ); ?>" data-initial="<?php echo 'second' === $a['defaultTab'] ? 'second' : 'first'; ?>">
	<?php if ( 'tooltip' === $wpe_mode ) : ?>
		<span class="wpe-tooltip__tip-wrap is-position-<?php echo esc_attr( in_array( $a['position'], array( 'top', 'bottom', 'start', 'end' ), true ) ? $a['position'] : 'top' ); ?>">
			<button type="button" class="wpe-tooltip__trigger"<?php echo '' !== $wpe_tip ? ' aria-describedby="' . esc_attr( $wpe_tip_id ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped accessible reference. ?>><?php echo esc_html( '' !== trim( (string) $a['trigger'] ) ? $a['trigger'] : __( 'More information', 'wpessential-pro' ) ); ?></button>
			<?php if ( '' !== $wpe_tip ) : ?>
				<span id="<?php echo esc_attr( $wpe_tip_id ); ?>" class="wpe-tooltip__bubble" role="tooltip"><?php echo esc_html( $wpe_tip ); ?></span>
			<?php endif; ?>
		</span>
	<?php elseif ( 'showmore' === $wpe_mode ) : ?>
		<div class="wpe-tooltip__body wpe-tooltip__intro"><?php echo wp_kses_post( (string) $a['summary'] ); ?></div>
		<details class="wpe-tooltip__details"<?php echo $a['expanded'] ? ' open' : ''; ?>>
			<summary class="wpe-tooltip__summary">
				<span class="wpe-tooltip__more"><?php echo esc_html( '' !== trim( (string) $a['moreLabel'] ) ? $a['moreLabel'] : __( 'Show more', 'wpessential-pro' ) ); ?></span>
				<span class="wpe-tooltip__less"><?php echo esc_html( '' !== trim( (string) $a['lessLabel'] ) ? $a['lessLabel'] : __( 'Show less', 'wpessential-pro' ) ); ?></span>
			</summary>
			<div class="wpe-tooltip__body wpe-tooltip__expanded"><?php echo wp_kses_post( (string) $a['expandedContent'] ); ?></div>
		</details>
	<?php else : ?>
		<div class="wpe-tooltip__switch" data-switch-buttons="1" data-switch-label="<?php esc_attr_e( 'Content options', 'wpessential-pro' ); ?>">
			<button id="<?php echo esc_attr( $id ); ?>-first-tab" class="wpe-tooltip__switch-button" type="button" data-switch-to="first" aria-controls="<?php echo esc_attr( $id ); ?>-first-panel"><?php echo esc_html( $wpe_first ); ?></button>
			<button id="<?php echo esc_attr( $id ); ?>-second-tab" class="wpe-tooltip__switch-button" type="button" data-switch-to="second" aria-controls="<?php echo esc_attr( $id ); ?>-second-panel"><?php echo esc_html( $wpe_second ); ?></button>
		</div>
		<div id="<?php echo esc_attr( $id ); ?>-first-panel" class="wpe-tooltip__body wpe-tooltip__switch-panel" data-switch-panel="first" aria-labelledby="<?php echo esc_attr( $id ); ?>-first-tab"><?php echo wp_kses_post( (string) $a['firstContent'] ); ?></div>
		<div id="<?php echo esc_attr( $id ); ?>-second-panel" class="wpe-tooltip__body wpe-tooltip__switch-panel" data-switch-panel="second" aria-labelledby="<?php echo esc_attr( $id ); ?>-second-tab"><?php echo wp_kses_post( (string) $a['secondContent'] ); ?></div>
	<?php endif; ?>
</div>
