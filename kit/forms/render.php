<?php
/**
 * WPForms Lite embed; WPForms owns validation, nonces and submissions.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Scoped render template.

$wpe_raw_id = trim( (string) $a['formId'] );
$wpe_id     = ctype_digit( $wpe_raw_id ) ? absint( $wpe_raw_id ) : 0;
$wpe_post   = $wpe_id ? get_post( $wpe_id ) : null;
$wpe_valid  = $wpe_post && 'wpforms' === $wpe_post->post_type && 'publish' === $wpe_post->post_status && shortcode_exists( 'wpforms' );
if ( ! $wpe_valid ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		echo '<p id="' . esc_attr( $id ) . '" class="wpe-forms__empty">' . esc_html__( 'Select an existing published WPForms form. WPForms Lite must be active.', 'wpessential-pro' ) . '</p>';
	}
	return;
}
$wpe_shortcode = sprintf(
	'[wpforms id="%d" title="%s" description="%s"]',
	$wpe_id,
	! empty( $a['showTitle'] ) ? 'true' : 'false',
	! empty( $a['showDescription'] ) ? 'true' : 'false'
);
$wpe_form      = do_shortcode( $wpe_shortcode );
?>
<section id="<?php echo esc_attr( $id ); ?>" class="wpe-forms is-cols-<?php echo '2' === $a['columns'] ? '2' : '1'; ?>">
	<?php if ( '' !== trim( (string) $a['heading'] ) ) : ?>
		<h2 class="wpe-forms__heading"><?php echo esc_html( (string) $a['heading'] ); ?></h2>
	<?php endif; ?>
	<div class="wpe-forms__body">
		<?php echo $wpe_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WPForms shortcode outputs its own trusted form markup. ?>
	</div>
</section>
