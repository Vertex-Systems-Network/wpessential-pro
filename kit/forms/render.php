<?php
/**
 * WPForms Lite or Contact Form 7 embed; the form plugin owns validation, nonces and submissions.
 *
 * @package WPEssential
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Scoped render template.

$wpe_raw_id = trim( (string) $a['formId'] );
$wpe_id     = ctype_digit( $wpe_raw_id ) ? absint( $wpe_raw_id ) : 0;
$wpe_post   = $wpe_id ? get_post( $wpe_id ) : null;
// WPForms or Contact Form 7: each plugin keeps its own validation, nonces and submissions.
$wpe_wpforms = $wpe_post && 'wpforms' === $wpe_post->post_type && shortcode_exists( 'wpforms' );
$wpe_cf7     = $wpe_post && 'wpcf7_contact_form' === $wpe_post->post_type && shortcode_exists( 'contact-form-7' );
if ( ! $wpe_post || 'publish' !== $wpe_post->post_status || ( ! $wpe_wpforms && ! $wpe_cf7 ) ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		echo '<p id="' . esc_attr( $id ) . '" class="wpe-forms__empty">' . esc_html__( 'Enter the ID of a published WPForms or Contact Form 7 form. The form plugin must be active.', 'wpessential-pro' ) . '</p>';
	}
	return;
}
$wpe_shortcode = $wpe_cf7
	? sprintf( '[contact-form-7 id="%d"]', $wpe_id )
	: sprintf(
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
		<?php echo $wpe_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the form plugin's shortcode outputs its own trusted form markup. ?>
	</div>
</section>
