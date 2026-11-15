<?php
/**
 * Flip/hover box as native disclosure: keyboard, pointer and touch all work.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Scoped Kit template.
$wpe_axis  = 'vertical' === $a['direction'] ? 'vertical' : 'horizontal';
$wpe_url   = esc_url( trim( (string) $a['url'] ), array( 'http', 'https' ) );
$wpe_front = trim( (string) $a['frontTitle'] );
$wpe_back  = trim( (string) $a['backTitle'] );
$wpe_glyph = \WPEssential\Platform\Kit\Kit::icon_html( $a['icon'], 'wpe-flip__icon' );
?>
<div id="<?php echo esc_attr( $id ); ?>" class="wpe-flip is-<?php echo esc_attr( $wpe_axis ); ?>">
	<details class="wpe-flip__details"<?php echo ! empty( $a['opened'] ) ? ' open' : ''; ?>>
		<summary class="wpe-flip__front">
			<?php if ( $wpe_glyph ) : ?>
			<span class="wpe-flip__media"><?php echo $wpe_glyph; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared icon helper. ?></span>
			<?php endif; ?>
			<?php if ( '' !== $wpe_front ) : ?>
			<span class="wpe-flip__title"><?php echo esc_html( $wpe_front ); ?></span>
			<?php endif; ?>
			<span class="wpe-flip__text"><?php echo wp_kses_post( (string) $a['frontContent'] ); ?></span>
			<span class="wpe-flip__toggle"><span class="wpe-flip__open-label"><?php echo esc_html( (string) $a['toggleLabel'] ); ?></span><span class="wpe-flip__close-label"><?php esc_html_e( 'Show front', 'wpessential-pro' ); ?></span></span>
		</summary>
		<div class="wpe-flip__back">
			<?php if ( '' !== $wpe_back ) : ?>
			<h3 class="wpe-flip__title"><?php echo esc_html( $wpe_back ); ?></h3>
			<?php endif; ?>
			<div class="wpe-flip__text"><?php echo wp_kses_post( (string) $a['backContent'] ); ?></div>
			<?php if ( $wpe_url && '' !== trim( (string) $a['button'] ) ) : ?>
				<a class="wpe-flip__button" href="<?php echo esc_url( $wpe_url ); ?>"<?php echo ! empty( $a['newTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( (string) $a['button'] ); ?></a>
			<?php endif; ?>
		</div>
	</details>
</div>
