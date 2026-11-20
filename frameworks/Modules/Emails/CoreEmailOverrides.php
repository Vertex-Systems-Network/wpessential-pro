<?php

declare(strict_types=1);

namespace WPEssential\Modules\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;
use Throwable;
use WPEssential\Contracts\AbilityHandlerInterface;
use WPEssential\Contracts\DefinitionRepositoryInterface;
use WPEssential\Platform\Abilities\AbilityRegistry;
use WPEssential\Platform\Auth\ExecutionChannel;
use WPEssential\Platform\Auth\ExecutionContext;
use WPEssential\Platform\Definitions\Definition;
use WPEssential\Platform\Definitions\DefinitionStatus;
use WPEssential\Platform\WordPress\Abilities\WordPressExecutionContextFactory;

/**
 * Edit, restyle or turn off the emails WordPress core sends.
 *
 * Every override goes through the core filter for that email, so nothing replaces wp_mail().
 * Security emails (password reset, email change) can be reworded but never turned off, and the
 * password reset text must keep its reset link.
 */
final class CoreEmailOverrides implements AbilityHandlerInterface {

	public const DEFINITION_TYPE = 'email-core-override';
	public const ABILITY         = 'wpessential/emails/core-override';
	private const PAGE_SLUG      = 'wpessential-module-emails';
	private const ACTION         = 'wpessential_core_emails';

	/** Email key => [label, can be turned off]. */
	public const EMAILS = [
		'new_user'           => [ 'New account (to the user)', true ],
		'new_user_admin'     => [ 'New account (to the admin)', true ],
		'password_reset'     => [ 'Password reset link', false ],
		'password_change'    => [ 'Password changed', true ],
		'email_change'       => [ 'Email address changed', false ],
		'comment_author'     => [ 'New comment (to the post author)', true ],
		'comment_moderation' => [ 'Comment awaiting moderation', true ],
	];

	/** @var array<string,array<string,mixed>>|null */
	private ?array $cache = null;

	public function __construct(
		private readonly DefinitionRepositoryInterface $definitions,
		private ?AbilityRegistry $abilities = null,
		private ?WordPressExecutionContextFactory $contexts = null,
	) {}

	public function register(): void {
		add_filter( 'wp_new_user_notification_email', fn ( array $email ): array => $this->filterArray( 'new_user', $email ) );
		add_filter( 'wp_new_user_notification_email_admin', fn ( array $email ): array => $this->filterArray( 'new_user_admin', $email ) );
		add_filter( 'password_change_email', fn ( array $email ): array => $this->filterArray( 'password_change', $email ) );
		add_filter( 'email_change_email', fn ( array $email ): array => $this->filterArray( 'email_change', $email ) );
		add_filter( 'retrieve_password_title', fn ( string $subject ): string => $this->text( 'password_reset', 'subject', $subject ) );
		add_filter( 'retrieve_password_message', fn ( string $message ): string => $this->text( 'password_reset', 'body', $message ) );
		add_filter( 'comment_notification_subject', fn ( string $subject ): string => $this->text( 'comment_author', 'subject', $subject ) );
		add_filter( 'comment_notification_text', fn ( string $message ): string => $this->text( 'comment_author', 'body', $message ) );
		add_filter( 'comment_moderation_subject', fn ( string $subject ): string => $this->text( 'comment_moderation', 'subject', $subject ) );
		add_filter( 'comment_moderation_text', fn ( string $message ): string => $this->text( 'comment_moderation', 'body', $message ) );
		add_filter( 'wp_send_new_user_notification_to_user', fn ( bool $send ): bool => $send && ! $this->off( 'new_user' ) );
		add_filter( 'wp_send_new_user_notification_to_admin', fn ( bool $send ): bool => $send && ! $this->off( 'new_user_admin' ) );
		add_filter( 'send_password_change_email', fn ( bool $send ): bool => $send && ! $this->off( 'password_change' ) );
		add_filter( 'notify_post_author', fn ( bool $send ): bool => $send && ! $this->off( 'comment_author' ) );
		add_filter( 'notify_moderator', fn ( bool $send ): bool => $send && ! $this->off( 'comment_moderation' ) );
	}

	public function registerAdmin( AbilityRegistry $abilities, WordPressExecutionContextFactory $contexts ): void {
		$this->abilities = $abilities;
		$this->contexts  = $contexts;
		// Shown on the module's own settings page, not as a separate menu page.
		add_filter(
			'wpessential_module_page_sections',
			function ( mixed $html, mixed $module ): mixed {
				if ( $module !== 'emails' || ! is_string( $html ) ) {
					return $html;
				}
				ob_start();
				$this->renderPage();

				return $html . (string) ob_get_clean();
			},
			10,
			2,
		);
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handleForm' ] );
	}

	/**
	 * @param array<string,mixed> $email wp_mail() arguments as core builds them.
	 * @return array<string,mixed>
	 */
	public function filterArray( string $key, array $email ): array {
		$email['subject'] = $this->text( $key, 'subject', (string) ( $email['subject'] ?? '' ) );
		$email['message'] = $this->text( $key, 'body', (string) ( $email['message'] ?? '' ) );

		return $email;
	}

	public function text( string $key, string $part, string $original ): string {
		return self::render( (string) ( $this->overrides()[ $key ][ $part ] ?? '' ), $original );
	}

	/** Empty template keeps the core text; {original}, {site_name}, {site_url} and {reset_link} are filled in. */
	public static function render( string $template, string $original ): string {
		if ( $template === '' ) {
			return $original;
		}
		preg_match( '#<?(https?://[^\s<>]*action=rp[^\s<>]*)>?#', $original, $link );

		return strtr(
			$template,
			[
				'{original}'   => $original,
				'{site_name}'  => function_exists( 'wp_specialchars_decode' ) ? wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ) : '',
				'{site_url}'   => function_exists( 'home_url' ) ? home_url( '/' ) : '',
				'{reset_link}' => $link[1] ?? '',
			],
		);
	}

	public function off( string $key ): bool {
		return ( $this->overrides()[ $key ]['off'] ?? false ) === true && ( self::EMAILS[ $key ][1] ?? false );
	}

	/**
	 * @param array<string,mixed> $input email key => {subject?, body?, off?}
	 * @return array<string,array<string,mixed>>
	 */
	public static function sanitize( array $input ): array {
		$out = [];
		foreach ( $input as $key => $row ) {
			if ( ! isset( self::EMAILS[ $key ] ) || ! is_array( $row ) ) {
				continue;
			}
			$clean   = [];
			$subject = trim( wp_strip_all_tags( (string) ( $row['subject'] ?? '' ) ) );
			$body    = trim( str_replace( "\r\n", "\n", (string) ( $row['body'] ?? '' ) ) );
			if ( $subject !== '' ) {
				$clean['subject'] = mb_substr( $subject, 0, 200 );
			}
			if ( $body !== '' ) {
				if ( $key === 'password_reset' && ! str_contains( $body, '{reset_link}' ) && ! str_contains( $body, '{original}' ) ) {
					throw new InvalidArgumentException( 'The password reset email must contain {reset_link} or {original}, or users cannot reset their password.' );
				}
				$clean['body'] = mb_substr( $body, 0, 10000 ); // Plain-text email: tags are kept as text, e.g. <{reset_link}>.
			}
			if ( ( $row['off'] ?? '' ) === '1' ) {
				if ( ! self::EMAILS[ $key ][1] ) {
					throw new InvalidArgumentException( 'Security emails can be reworded but not turned off.' );
				}
				$clean['off'] = true;
			}
			if ( $clean !== [] ) {
				$out[ $key ] = $clean;
			}
		}

		return $out;
	}

	/** Ability: {emails: map, revert?: bool}. */
	public function handle( array $input, ExecutionContext $context ): mixed {
		$existing  = $this->definitions->byType( self::DEFINITION_TYPE )[0] ?? null;
		$override  = ( $input['revert'] ?? false ) === true ? [] : self::sanitize( is_array( $input['emails'] ?? null ) ? $input['emails'] : [] );
		$status    = $override === [] ? DefinitionStatus::Archived : DefinitionStatus::Published;
		$candidate = new Definition(
			id: $existing->id ?? self::uuid(),
			slug: 'emails-core-overrides',
			type: self::DEFINITION_TYPE,
			schemaVersion: 1,
			ownerSurfaceId: EmailTemplateDefinition::OWNER_SURFACE_ID,
			status: $status,
			payload: [ 'emails' => $override ],
			revision: ( $existing->revision ?? 0 ) + 1,
		);
		$this->definitions->save( new Definition( $candidate->id, $candidate->slug, $candidate->type, 1, $candidate->ownerSurfaceId, $status, $candidate->payload, $candidate->revision, [], $candidate->computedChecksum() ) );
		$this->cache = null;

		return [
			'status' => $status->value,
			'emails' => $override,
		];
	}

	/** @return array<string,array<string,mixed>> */
	public function overrides(): array {
		if ( $this->cache === null ) {
			$definition  = $this->definitions->byType( self::DEFINITION_TYPE )[0] ?? null;
			$this->cache = $definition !== null && $definition->status === DefinitionStatus::Published ? (array) ( $definition->payload['emails'] ?? [] ) : [];
		}

		return $this->cache;
	}

	public function handleForm(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change emails.', 'wpessential' ), 403 );
		}
		check_admin_referer( self::ACTION );
		$input  = [
			'revert' => isset( $_POST['revert'] ),
			'emails' => is_array( $_POST['emails'] ?? null ) ? wp_unslash( $_POST['emails'] ) : [], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize() allowlists keys and strips tags.
		];
		$notice = 'saved';
		try {
			$current = $this->contexts?->current();
			if ( $this->abilities === null || $current === null ) {
				throw new InvalidArgumentException( 'Abilities are unavailable.' );
			}
			$context = new ExecutionContext( principal: $current->principal, siteId: $current->siteId, channel: ExecutionChannel::Ui, networkId: $current->networkId, correlationId: $current->correlationId );
			$this->abilities->execute( self::ABILITY, $input, $context );
		} catch ( Throwable $error ) {
			$notice = 'error';
			set_transient( 'wpessential_core_emails_error_' . get_current_user_id(), $error->getMessage(), 60 );
		}
		$query = [
			'page'       => self::PAGE_SLUG,
			'wpe_notice' => $notice,
		];
		wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function renderPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice    = isset( $_GET['wpe_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['wpe_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
		$overrides = $this->overrides();
		echo '<section class="wpe-ui-panel" aria-labelledby="wpessential-emails-existing"><h2 id="wpessential-emails-existing">' . esc_html__( 'Core emails', 'wpessential' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Reword or turn off the emails WordPress sends. Leave a box empty to keep the WordPress text. Placeholders: {original} (the WordPress text), {site_name}, {site_url}, and {reset_link} for the password reset email.', 'wpessential' ) . '</p>';
		if ( $notice === 'saved' ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Saved.', 'wpessential' ) . '</p></div>';
		} elseif ( $notice === 'error' ) {
			$message = (string) get_transient( 'wpessential_core_emails_error_' . get_current_user_id() );
			echo '<div class="notice notice-error"><p>' . esc_html( $message !== '' ? $message : __( 'Not saved.', 'wpessential' ) ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		foreach ( self::EMAILS as $key => [$label, $canTurnOff] ) {
			$row = $overrides[ $key ] ?? [];
			echo '<h2>' . esc_html( $label ) . '</h2><table class="form-table" role="presentation"><tbody>';
			echo '<tr><th scope="row"><label for="wpe-email-' . esc_attr( $key ) . '-subject">' . esc_html__( 'Subject', 'wpessential' ) . '</label></th><td><input class="large-text" id="wpe-email-' . esc_attr( $key ) . '-subject" name="emails[' . esc_attr( $key ) . '][subject]" value="' . esc_attr( (string) ( $row['subject'] ?? '' ) ) . '"></td></tr>';
			echo '<tr><th scope="row"><label for="wpe-email-' . esc_attr( $key ) . '-body">' . esc_html__( 'Message', 'wpessential' ) . '</label></th><td><textarea class="large-text" rows="5" id="wpe-email-' . esc_attr( $key ) . '-body" name="emails[' . esc_attr( $key ) . '][body]">' . esc_textarea( (string) ( $row['body'] ?? '' ) ) . '</textarea></td></tr>';
			echo '<tr><th scope="row">' . esc_html__( 'Sending', 'wpessential' ) . '</th><td>';
			if ( $canTurnOff ) {
				echo '<label><input type="checkbox" name="emails[' . esc_attr( $key ) . '][off]" value="1"' . checked( ( $row['off'] ?? false ) === true, true, false ) . '> ' . esc_html__( 'Do not send this email', 'wpessential' ) . '</label>';
			} else {
				echo '<span class="description">' . esc_html__( 'Security email: always sent.', 'wpessential' ) . '</span>';
			}
			echo '</td></tr></tbody></table>';
		}
		submit_button( __( 'Save changes', 'wpessential' ), 'primary', 'submit', false );
		echo ' ';
		submit_button( __( 'Revert to original', 'wpessential' ), 'secondary', 'revert', false );
		echo '</form></section>';
	}

	private static function uuid(): string {
		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $bytes ), 4 ) );
	}
}
