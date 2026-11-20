<?php

declare(strict_types=1);

namespace WPEssential\Modules\Fields;

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
 * Field Groups stored as term meta (taxonomy screens) or user meta (profile screens).
 *
 * A group opts in with storage.mode native_term_meta + taxonomy location rules, or
 * native_user_meta + user_role location rules. Values go through the same normalizer and
 * persistence guard as post meta, under the field key, via the write-object-value Ability.
 */
final class FieldTermUserScreens implements AbilityHandlerInterface {

	public const ABILITY = 'wpessential/fields/write-object-value';
	private const NONCE  = 'wpessential_fields_object';
	private const MODES  = [
		'native_term_meta' => [ 'term', 'taxonomy' ],
		'native_user_meta' => [ 'user', 'user_role' ],
	];

	/** @var array<string,callable> */
	private array $meta;

	/**
	 * @param array<string,callable>|null $meta get/update/delete for term and user meta; WordPress functions by default.
	 * @param (callable(string,int):bool)|null $can Capability check for an object, current_user_can() by default.
	 */
	public function __construct(
		private readonly DefinitionRepositoryInterface $definitions,
		private ?AbilityRegistry $abilities = null,
		private ?WordPressExecutionContextFactory $contexts = null,
		?array $meta = null,
		private $can = null,
		private readonly FieldValueNormalizer $values = new FieldValueNormalizer(),
		private readonly FieldValuePersistenceGuard $guard = new FieldValuePersistenceGuard(),
	) {
		$this->meta = $meta ?? [
			'term.get'    => 'get_term_meta',
			'term.update' => 'update_term_meta',
			'term.delete' => 'delete_term_meta',
			'user.get'    => 'get_user_meta',
			'user.update' => 'update_user_meta',
			'user.delete' => 'delete_user_meta',
		];
		$this->can ??= static fn ( string $kind, int $id ): bool => current_user_can( $kind === 'term' ? 'edit_term' : 'edit_user', $id );
	}

	/**
	 * Which screens a group belongs on, or null for groups stored elsewhere.
	 *
	 * @param array<string,mixed> $payload
	 * @return array{kind:string,keys:list<string>}|null
	 */
	public static function targets( array $payload ): ?array {
		$mode = (string) ( $payload['storage']['mode'] ?? '' );
		if ( ! isset( self::MODES[ $mode ] ) ) {
			return null;
		}
		[$kind, $source] = self::MODES[ $mode ];
		$keys            = [];
		foreach ( (array) ( $payload['locations'] ?? [] ) as $group ) {
			$anchored = false;
			foreach ( (array) $group as $rule ) {
				if ( ( $rule['source'] ?? null ) !== $source ) {
					continue;
				}
				$operator = (string) ( $rule['operator'] ?? '' );
				if ( ( $rule['negate'] ?? false ) === true || ! in_array( $operator, [ 'equals', 'in' ], true ) ) {
					throw new InvalidArgumentException( sprintf( '%s fields support only "%s equals" or "%s in" location rules.', ucfirst( $kind ), $source, $source ) );
				}
				foreach ( (array) $rule['value'] as $value ) {
					if ( ! is_string( $value ) || preg_match( '/^[a-z0-9_-]{1,32}$/', $value ) !== 1 ) {
						throw new InvalidArgumentException( sprintf( '"%s" location values must be machine keys.', $source ) );
					}
					$keys[ $value ] = true;
				}
				$anchored = true;
			}
			if ( ! $anchored ) {
				throw new InvalidArgumentException( sprintf( 'Every location group of a %s field group needs a "%s" rule.', $kind, $source ) );
			}
		}
		if ( $keys === [] ) {
			throw new InvalidArgumentException( sprintf( 'A %s field group needs at least one "%s" location rule.', $kind, $source ) );
		}

		return [
			'kind' => $kind,
			'keys' => array_keys( $keys ),
		];
	}

	/**
	 * Publish check: placement resolves and every field has a certified storage shape.
	 *
	 * @param array<string,mixed> $payload
	 */
	public static function assertBindable( array $payload ): void {
		self::targets( $payload );
		$compiler = new PostMetaRegistrationCompiler();
		foreach ( (array) ( $payload['fields'] ?? [] ) as $field ) {
			$compiler->compile( $field, 'post' ); // The shape is the same for term and user meta.
		}
	}

	public function register(): void {
		add_action( 'init', [ $this, 'hook' ], 99 );
		add_action( 'personal_options_update', [ $this, 'saveUser' ] );
		add_action( 'edit_user_profile_update', [ $this, 'saveUser' ] );
		add_action( 'show_user_profile', [ $this, 'renderUser' ] );
		add_action( 'edit_user_profile', [ $this, 'renderUser' ] );
	}

	public function hook(): void {
		foreach ( $this->groups( 'term' ) as [$group, $targets] ) {
			unset( $group );
			foreach ( $targets['keys'] as $taxonomy ) {
				if ( has_action( $taxonomy . '_edit_form_fields', [ $this, 'renderTermEdit' ] ) ) {
					continue;
				}
				add_action( $taxonomy . '_add_form_fields', [ $this, 'renderTermAdd' ] );
				add_action( $taxonomy . '_edit_form_fields', [ $this, 'renderTermEdit' ], 10, 2 );
				add_action( 'created_' . $taxonomy, [ $this, 'saveTerm' ] );
				add_action( 'edited_' . $taxonomy, [ $this, 'saveTerm' ] );
			}
		}
	}

	public function renderTermAdd( string $taxonomy ): void {
		$groups = $this->groupsFor( 'term', [ $taxonomy ] );
		if ( $groups === [] ) {
			return;
		}
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->renderGroups( $groups, 'term', 0 );
		echo '</tbody></table>';
	}

	public function renderTermEdit( object $term, string $taxonomy ): void {
		$this->renderGroups( $this->groupsFor( 'term', [ $taxonomy ] ), 'term', (int) $term->term_id );
	}

	public function renderUser( object $user ): void {
		$groups = $this->groupsFor( 'user', array_values( (array) ( $user->roles ?? [] ) ) );
		foreach ( $groups as $group ) {
			echo '<h2>' . esc_html( (string) ( $group->payload['title'] ?? '' ) ) . '</h2><table class="form-table" role="presentation"><tbody>';
			$this->renderGroups( [ $group ], 'user', (int) $user->ID );
			echo '</tbody></table>';
		}
	}

	/** @param list<Definition> $groups */
	private function renderGroups( array $groups, string $kind, int $id ): void {
		if ( $groups === [] ) {
			return;
		}
		wp_nonce_field( self::NONCE, self::NONCE );
		foreach ( $groups as $group ) {
			FieldPostMetaBox::renderRows(
				(array) ( $group->payload['fields'] ?? [] ),
				'wpe_object_fields[' . $group->id . ']',
				fn ( string $uuid ): mixed => $id > 0 ? $this->read( $kind, $id, $group, $uuid ) : null,
			);
		}
		FieldPostMetaBox::printConditionsScript();
	}

	public function saveTerm( int $termId ): void {
		$this->saveSubmitted( 'term', $termId );
	}

	public function saveUser( int $userId ): void {
		$this->saveSubmitted( 'user', $userId );
	}

	private function saveSubmitted( string $kind, int $id ): void {
		if ( ! isset( $_POST[ self::NONCE ], $_POST['wpe_object_fields'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		$submitted = wp_unslash( $_POST['wpe_object_fields'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is normalized by the Ability.
		if ( ! is_array( $submitted ) || $this->abilities === null || $this->contexts === null ) {
			return;
		}
		$current = $this->contexts->current();
		$context = new ExecutionContext( principal: $current->principal, siteId: $current->siteId, channel: ExecutionChannel::Ui, networkId: $current->networkId, correlationId: $current->correlationId );
		foreach ( $submitted as $groupId => $fields ) {
			$group = is_string( $groupId ) ? $this->definitions->get( $groupId ) : null;
			if ( $group === null || ! is_array( $fields ) ) {
				continue;
			}
			$byUuid = array_column( (array) ( $group->payload['fields'] ?? [] ), null, 'uuid' );
			$values = [];
			foreach ( $fields as $uuid => $raw ) {
				if ( isset( $byUuid[ $uuid ] ) ) {
					$values[ (string) $byUuid[ $uuid ]['key'] ] = FieldPostMetaBox::value( (string) $byUuid[ $uuid ]['type'], $raw, $byUuid[ $uuid ] );
				}
			}
			foreach ( $fields as $uuid => $raw ) {
				$field = $byUuid[ $uuid ] ?? null;
				if ( $field === null || ! FieldConditionalLogic::visible( (array) ( $field['settings']['conditional_logic'] ?? [] ), $values ) ) {
					continue;
				}
				try {
					$this->abilities->execute(
						self::ABILITY,
						[
							'object_type' => $kind,
							'object_id'   => $id,
							'group_id'    => $group->id,
							'field_uuid'  => (string) $uuid,
							'value'       => FieldPostMetaBox::value( (string) $field['type'], $raw, $field ),
						],
						$context,
					);
				} catch ( Throwable ) {
					continue; // Same rule as posts: one bad field never loses the others.
				}
			}
		}
	}

	/** Ability: {object_type: term|user, object_id, group_id, field_uuid, value}. */
	public function handle( array $input, ExecutionContext $context ): mixed {
		$kind = (string) ( $input['object_type'] ?? '' );
		$id   = (int) ( $input['object_id'] ?? 0 );
		if ( ! in_array( $kind, [ 'term', 'user' ], true ) || $id < 1 ) {
			throw new InvalidArgumentException( 'object_type must be term or user with a positive object_id.' );
		}
		if ( ! ( $this->can )( $kind, $id ) ) {
			throw new InvalidArgumentException( sprintf( 'You cannot edit this %s.', $kind ) );
		}
		$group = $this->definitions->get( (string) ( $input['group_id'] ?? '' ) );
		if ( $group === null || $group->status !== DefinitionStatus::Published || ( self::targets( $group->payload )['kind'] ?? null ) !== $kind ) {
			throw new InvalidArgumentException( sprintf( 'Field Group is not a published %s field group.', $kind ) );
		}
		$field = array_column( (array) ( $group->payload['fields'] ?? [] ), null, 'uuid' )[ (string) ( $input['field_uuid'] ?? '' ) ] ?? null;
		if ( ! is_array( $field ) ) {
			throw new InvalidArgumentException( 'Unknown field.' );
		}
		$value = $this->guard->assertSafe( $this->values->normalize( $field, $input['value'] ?? null ) );
		if ( $value === null ) {
			( $this->meta[ $kind . '.delete' ] )( $id, (string) $field['key'] );
		} else {
			( $this->meta[ $kind . '.update' ] )( $id, (string) $field['key'], $value );
		}

		return [
			'object_type' => $kind,
			'object_id'   => $id,
			'field_key'   => (string) $field['key'],
			'value'       => $value,
		];
	}

	private function read( string $kind, int $id, Definition $group, string $uuid ): mixed {
		$field = array_column( (array) ( $group->payload['fields'] ?? [] ), null, 'uuid' )[ $uuid ] ?? null;
		if ( ! is_array( $field ) ) {
			return null;
		}
		$value = ( $this->meta[ $kind . '.get' ] )( $id, (string) $field['key'], true );

		return $value === '' ? null : $value;
	}

	/** @return list<array{0:Definition,1:array{kind:string,keys:list<string>}}> */
	private function groups( string $kind ): array {
		$out = [];
		foreach ( $this->definitions->byType( FieldGroupDefinitionNormalizer::DEFINITION_TYPE ) as $group ) {
			if ( $group->status !== DefinitionStatus::Published ) {
				continue;
			}
			try {
				$targets = self::targets( $group->payload );
			} catch ( InvalidArgumentException ) {
				continue;
			}
			if ( $targets !== null && $targets['kind'] === $kind ) {
				$out[] = [ $group, $targets ];
			}
		}

		return $out;
	}

	/**
	 * @param list<string> $keys Taxonomy of the screen, or the user's roles.
	 * @return list<Definition>
	 */
	private function groupsFor( string $kind, array $keys ): array {
		$out = [];
		foreach ( $this->groups( $kind ) as [$group, $targets] ) {
			if ( array_intersect( $keys, $targets['keys'] ) !== [] ) {
				$out[] = $group;
			}
		}

		return $out;
	}
}
