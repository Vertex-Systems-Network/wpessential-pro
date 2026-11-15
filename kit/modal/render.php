<?php
/**
 * Opt-in dialog with native details fallback when JavaScript is not available.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Scoped render template.

$wpe_title      = trim( (string) $a['title'] );
$wpe_title      = '' !== $wpe_title ? $wpe_title : __( 'Information', 'wpessential-pro' );
$wpe_trigger    = trim( (string) $a['trigger'] );
$wpe_trigger    = '' !== $wpe_trigger ? $wpe_trigger : __( 'Open details', 'wpessential-pro' );
$wpe_close      = trim( (string) $a['closeLabel'] );
$wpe_close      = '' !== $wpe_close ? $wpe_close : __( 'Close', 'wpessential-pro' );
$wpe_html       = wp_kses_post( (string) $a['content'] );
$wpe_appearance = 'minimal' === $a['appearance'] ? 'minimal' : 'boxed';
?>
<div id="<?php echo esc_attr( $id ); ?>" class="wpe-modal is-<?php echo esc_attr( $wpe_appearance ); ?>" data-wpe-modal="1" data-close-backdrop="<?php echo $a['closeBackdrop'] ? '1' : '0'; ?>">
	<details class="wpe-modal__fallback">
		<summary class="wpe-modal__fallback-trigger"><?php echo esc_html( $wpe_trigger ); ?></summary>
		<div class="wpe-modal__fallback-content">
			<h3><?php echo esc_html( $wpe_title ); ?></h3>
			<div><?php echo $wpe_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized through wp_kses_post. ?></div>
			<?php if ( '' !== trim( (string) $a['footerText'] ) ) : ?>
				<p><?php echo esc_html( (string) $a['footerText'] ); ?></p>
			<?php endif; ?>
		</div>
	</details>
	<button class="wpe-modal__open" type="button" hidden><?php echo esc_html( $wpe_trigger ); ?></button>
	<dialog class="wpe-modal__dialog" aria-labelledby="<?php echo esc_attr( $id ); ?>-title">
		<div class="wpe-modal__header">
			<h3 id="<?php echo esc_attr( $id ); ?>-title" class="wpe-modal__title"><?php echo esc_html( $wpe_title ); ?></h3>
			<button type="button" class="wpe-modal__close" aria-label="<?php echo esc_attr( $wpe_close ); ?>">&times;</button>
		</div>
		<div class="wpe-modal__content"><?php echo $wpe_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized through wp_kses_post. ?></div>
		<?php if ( '' !== trim( (string) $a['footerText'] ) ) : ?>
			<p class="wpe-modal__footer"><?php echo esc_html( (string) $a['footerText'] ); ?></p>
		<?php endif; ?>
		<button type="button" class="wpe-modal__close-bottom"><?php echo esc_html( $wpe_close ); ?></button>
	</dialog>
</div>
