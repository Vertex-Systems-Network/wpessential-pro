<?php

declare(strict_types=1);

namespace WPEssential\Modules\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use WP_Post;
use WPEssential\Platform\Abilities\AbilityRegistry;
use WPEssential\Platform\Auth\ExecutionChannel;
use WPEssential\Platform\Auth\ExecutionContext;
use WPEssential\Contracts\DefinitionRepositoryInterface;
use WPEssential\Platform\Definitions\DefinitionStatus;
use WPEssential\Platform\WordPress\Abilities\WordPressExecutionContextFactory;

/**
 * Edit-screen meta box for published Field Groups placed on a post type.
 *
 * Reads and writes go through the wpessential/fields/read-value and write-value Abilities, so
 * post authorization, value normalization and the group revision check stay in one place.
 */
final class FieldPostMetaBox {

	private const NONCE  = 'wpessential_fields_meta_box';
	private const ERRORS = 'wpessential_fields_meta_box_errors_';

	/** Field types the V1 editor can show; other types stay read-only in their own owner UI. */
	private const INPUTS = [
		'text'     => 'text',
		'textarea' => 'textarea',
		'number'   => 'number',
		'checkbox' => 'checkbox',
		'date'     => 'date',
		'time'     => 'time',
		'datetime' => 'datetime-local',
		'email'      => 'email',
		'url'        => 'url',
		'color'      => 'color',
		'range'      => 'number',
		'true_false' => 'checkbox',
		// shortcut: image/file take an attachment ID, upgrade to the media modal picker later.
		'image'      => 'number',
		'file'       => 'number',
	];

	public function __construct(
		private readonly DefinitionRepositoryInterface $definitions,
		private readonly FieldGroupPostTypeTargetCompiler $targets,
		private readonly AbilityRegistry $abilities,
		private readonly WordPressExecutionContextFactory $contexts,
	) {}

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'addBoxes' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'notices' ] );
	}

	public function addBoxes( string $postType, mixed $post = null ): void {
		foreach ( $this->groupsFor( $postType ) as $group ) {
			add_meta_box(
				'wpessential-fields-' . sanitize_key( $group->slug ),
				(string) ( $group->payload['title'] ?? $group->slug ),
				fn ( WP_Post $post ) => $this->render( $group, $post ),
				$postType,
				'normal',
				'default',
			);
		}
	}

	private function render( object $group, WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		printf( '<input type="hidden" name="wpe_fields_revision[%s]" value="%d">', esc_attr( $group->id ), (int) $group->revision );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( (array) ( $group->payload['fields'] ?? [] ) as $field ) {
			$type = (string) ( $field['type'] ?? '' );
			$uuid = (string) ( $field['uuid'] ?? '' );
			if ( ! isset( self::INPUTS[ $type ] ) || $uuid === '' ) {
				continue;
			}
			$id    = 'wpe-field-' . $uuid;
			$name  = sprintf( 'wpe_fields[%s][%s]', $group->id, $uuid );
			$value = $this->read( $group->id, $uuid, $post->ID );
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( (string) ( $field['label'] ?? $field['key'] ?? '' ) ) . '</label></th><td>';
			if ( $type === 'textarea' ) {
				echo '<textarea class="large-text" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( is_scalar( $value ) ? (string) $value : '' ) . '</textarea>';
			} elseif ( self::INPUTS[ $type ] === 'checkbox' ) {
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $value, true, false ) . '>';
			} else {
				$shown = $type === 'datetime' ? self::localDateTime( $value ) : ( is_scalar( $value ) ? (string) $value : '' );
				echo '<input class="regular-text" type="' . esc_attr( self::INPUTS[ $type ] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $shown ) . '"' . ( in_array( $type, [ 'number', 'range' ], true ) ? ' step="any"' : ( in_array( $type, [ 'image', 'file' ], true ) ? ' min="1" step="1"' : '' ) ) . '>';
			}
			if ( ! empty( $field['instructions'] ) && is_string( $field['instructions'] ) ) {
				echo '<p class="description">' . esc_html( $field['instructions'] ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function save( int $postId, mixed $post = null ): void {
		if ( ! isset( $_POST[ self::NONCE ], $_POST['wpe_fields'] ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $postId ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		$submitted = wp_unslash( $_POST['wpe_fields'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is normalized by the write-value Ability.
		$revisions = isset( $_POST['wpe_fields_revision'] ) ? (array) wp_unslash( $_POST['wpe_fields_revision'] ) : [];
		if ( ! is_array( $submitted ) ) {
			return;
		}

		$errors = [];
		foreach ( $submitted as $groupId => $fields ) {
			if ( ! is_string( $groupId ) || ! is_array( $fields ) ) {
				continue;
			}
			$group = $this->definitions->get( $groupId );
			if ( $group === null ) {
				continue;
			}
			$types = array_column( (array) ( $group->payload['fields'] ?? [] ), 'type', 'uuid' );
			foreach ( $fields as $uuid => $raw ) {
				$type = $types[ $uuid ] ?? '';
				if ( ! is_string( $uuid ) || ! isset( self::INPUTS[ $type ] ) ) {
					continue;
				}
				try {
					$this->abilities->execute(
						'wpessential/fields/write-value',
						[
							'group_id'                => $groupId,
							'field_uuid'              => $uuid,
							'post_id'                 => $postId,
							'expected_group_revision' => (int) ( $revisions[ $groupId ] ?? $group->revision ),
							'value'                   => self::value( $type, $raw ),
						],
						$this->context(),
					);
				} catch ( Throwable $error ) {
					$errors[] = $error->getMessage();
				}
			}
		}
		if ( $errors !== [] ) {
			// One error must not lose the other fields; show what failed on the next screen.
			set_transient( self::ERRORS . get_current_user_id(), array_slice( $errors, 0, 10 ), 60 );
		}
	}

	public function notices(): void {
		$errors = get_transient( self::ERRORS . get_current_user_id() );
		if ( ! is_array( $errors ) || $errors === [] ) {
			return;
		}
		delete_transient( self::ERRORS . get_current_user_id() );
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Some custom fields were not saved:', 'wpessential' ) . '</p><ul>';
		foreach ( $errors as $error ) {
			echo '<li>' . esc_html( (string) $error ) . '</li>';
		}
		echo '</ul></div>';
	}

	/** @return list<object> Published groups whose location rules place them on the post type. */
	private function groupsFor( string $postType ): array {
		$groups = [];
		foreach ( $this->definitions->byType( FieldGroupDefinitionNormalizer::DEFINITION_TYPE ) as $group ) {
			if ( $group->status !== DefinitionStatus::Published || $group->ownerSurfaceId !== FieldGroupDefinitionNormalizer::OWNER_SURFACE_ID ) {
				continue;
			}
			try {
				if ( in_array( $postType, $this->targets->compile( $group->payload ), true ) ) {
					$groups[] = $group;
				}
			} catch ( Throwable ) {
				continue; // A group without a placement shows nowhere, as at runtime registration.
			}
		}

		return $groups;
	}

	private function read( string $groupId, string $uuid, int $postId ): mixed {
		if ( $postId < 1 ) {
			return null;
		}
		try {
			$result = $this->abilities->execute( 'wpessential/fields/read-value', [ 'group_id' => $groupId, 'field_uuid' => $uuid, 'post_id' => $postId ], $this->context() );
		} catch ( Throwable ) {
			return null;
		}

		return is_array( $result ) ? ( $result['value'] ?? null ) : null;
	}

	/** Form strings in the shape the write-value Ability expects; empty means "no value". */
	public static function value( string $type, mixed $raw ): mixed {
		$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
		if ( $type === 'checkbox' || $type === 'true_false' ) {
			return $raw === '1';
		}
		if ( $raw === '' ) {
			return null;
		}
		if ( in_array( $type, [ 'number', 'range', 'image', 'file' ], true ) ) {
			return is_numeric( $raw ) ? 0 + $raw : $raw;
		}
		if ( $type === 'datetime' ) {
			// datetime-local has no offset: read it in the site's timezone.
			$zone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
			try {
				return ( new DateTimeImmutable( $raw, $zone ) )->format( DATE_RFC3339 );
			} catch ( Throwable ) {
				return $raw;
			}
		}

		return $raw;
	}

	/** Stored UTC instant shown as the site's local time for a datetime-local input. */
	public static function localDateTime( mixed $value ): string {
		if ( ! is_string( $value ) || $value === '' ) {
			return '';
		}
		try {
			$zone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
			return ( new DateTimeImmutable( $value ) )->setTimezone( $zone )->format( 'Y-m-d\TH:i' );
		} catch ( Throwable ) {
			return '';
		}
	}

	private function context(): ExecutionContext {
		$current = $this->contexts->current();

		return new ExecutionContext(
			principal: $current->principal,
			siteId: $current->siteId,
			channel: ExecutionChannel::Ui,
			networkId: $current->networkId,
			correlationId: $current->correlationId,
		);
	}
}
