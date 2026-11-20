<?php

declare(strict_types=1);

namespace WPEssential\Modules\Status;

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
 * Rename core post statuses and hide some of them on chosen post types.
 *
 * Status keys and core behavior never change; only labels are rewritten after core registers them,
 * and a hidden status is removed from list views and refused when a post would move into it.
 */
final class ExistingStatusOverrides implements AbilityHandlerInterface {

	public const DEFINITION_TYPE = 'status-existing-override';
	public const ABILITY         = 'wpessential/status/existing-override';
	private const PAGE_SLUG      = 'wpessential-module-status';
	private const ACTION         = 'wpessential_existing_statuses';

	/** Core statuses editors see. Draft and Published can be renamed but never hidden. */
	public const CORE = [ 'draft', 'pending', 'private', 'future', 'publish' ];

	private const HIDEABLE = [ 'pending', 'private', 'future' ];

	/** @var array<string,array<string,mixed>>|null */
	private ?array $cache = null;

	public function __construct(
		private readonly DefinitionRepositoryInterface $definitions,
		private ?AbilityRegistry $abilities = null,
		private ?WordPressExecutionContextFactory $contexts = null,
	) {}

	public function register(): void {
		add_action( 'init', [ $this, 'applyLabels' ], 100 );
		add_filter( 'wp_insert_post_data', [ $this, 'guardHidden' ], 10, 2 );
		add_action(
			'admin_init',
			function (): void {
				foreach ( $this->overrides() as $override ) {
					foreach ( (array) ( $override['hidden_for'] ?? [] ) as $postType ) {
						add_filter( 'views_edit-' . $postType, [ $this, 'filterViews' ] );
					}
				}
			},
		);
	}

	public function registerAdmin( AbilityRegistry $abilities, WordPressExecutionContextFactory $contexts ): void {
		$this->abilities = $abilities;
		$this->contexts  = $contexts;
		// Shown on the module's own settings page, not as a separate menu page.
		add_filter(
			'wpessential_module_page_sections',
			function ( mixed $html, mixed $module ): mixed {
				if ( $module !== 'status' || ! is_string( $html ) ) {
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

	public function applyLabels(): void {
		global $wp_post_statuses;
		foreach ( $this->overrides() as $key => $override ) {
			if ( ! isset( $wp_post_statuses[ $key ] ) || ! is_object( $wp_post_statuses[ $key ] ) ) {
				continue;
			}
			$status = $wp_post_statuses[ $key ];
			if ( isset( $override['label'] ) ) {
				$status->label = $override['label'];
			}
			if ( isset( $override['label'] ) || isset( $override['plural'] ) ) {
				$status->label_count = self::labelCount( (string) ( $override['label'] ?? $status->label ), (string) ( $override['plural'] ?? $override['label'] ?? $status->label ) );
			}
		}
	}

	/** Same shape as _n_noop(), which core uses for label_count. @return array<string,mixed> */
	public static function labelCount( string $singular, string $plural ): array {
		$count = ' <span class="count">(%s)</span>';

		return [
			0          => $singular . $count,
			1          => $plural . $count,
			'singular' => $singular . $count,
			'plural'   => $plural . $count,
			'context'  => null,
			'domain'   => null,
		];
	}

	/**
	 * @param array<string,mixed> $views
	 * @return array<string,mixed>
	 */
	public function filterViews( array $views ): array {
		$postType = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list view.
		foreach ( $this->overrides() as $key => $override ) {
			if ( in_array( $postType, (array) ( $override['hidden_for'] ?? [] ), true ) ) {
				unset( $views[ $key ] );
			}
		}

		return $views;
	}

	/**
	 * A post may not move into a status hidden for its type; it stays in its current status, or Draft when new.
	 *
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $postarr
	 * @return array<string,mixed>
	 */
	public function guardHidden( array $data, array $postarr ): array {
		return self::guard( $data, $postarr, $this->overrides(), static fn ( int $id ): string => (string) get_post_status( $id ) );
	}

	/**
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $postarr
	 * @param array<string,array<string,mixed>> $overrides
	 * @param callable(int):string $currentStatus
	 * @return array<string,mixed>
	 */
	public static function guard( array $data, array $postarr, array $overrides, callable $currentStatus ): array {
		$status = (string) ( $data['post_status'] ?? '' );
		$type   = (string) ( $data['post_type'] ?? '' );
		if ( ! in_array( $type, (array) ( $overrides[ $status ]['hidden_for'] ?? [] ), true ) ) {
			return $data;
		}
		$id      = (int) ( $postarr['ID'] ?? 0 );
		$current = $id > 0 ? $currentStatus( $id ) : '';
		if ( $current === $status ) {
			return $data; // Posts already in that status keep it; only new moves are refused.
		}
		$data['post_status'] = in_array( $current, [ '', 'auto-draft', 'new' ], true ) ? 'draft' : $current;

		return $data;
	}

	/**
	 * @param array<string,mixed> $input Map of core status key => {label?, plural?, hidden_for?}.
	 * @param list<string> $postTypes Post types that may be named in hidden_for.
	 * @return array<string,array<string,mixed>>
	 */
	public static function sanitize( array $input, array $postTypes ): array {
		$out = [];
		foreach ( $input as $key => $row ) {
			if ( ! in_array( $key, self::CORE, true ) || ! is_array( $row ) ) {
				continue;
			}
			$clean = [];
			foreach ( [ 'label', 'plural' ] as $field ) {
				$value = trim( wp_strip_all_tags( (string) ( $row[ $field ] ?? '' ) ) );
				if ( $value !== '' ) {
					$clean[ $field ] = mb_substr( $value, 0, 60 );
				}
			}
			$hidden = array_values( array_intersect( $postTypes, array_map( 'strval', (array) ( $row['hidden_for'] ?? [] ) ) ) );
			if ( $hidden !== [] ) {
				if ( ! in_array( $key, self::HIDEABLE, true ) ) {
					throw new InvalidArgumentException( 'Draft and Published cannot be hidden; WordPress needs them to save and publish.' );
				}
				$clean['hidden_for'] = $hidden;
			}
			if ( $clean !== [] ) {
				$out[ $key ] = $clean;
			}
		}

		return $out;
	}

	/** Ability: {statuses: map, post_types: list, revert?: bool}. */
	public function handle( array $input, ExecutionContext $context ): mixed {
		$existing  = $this->find();
		$revert    = ( $input['revert'] ?? false ) === true;
		$postTypes = array_values( array_filter( array_map( 'strval', (array) ( $input['post_types'] ?? [] ) ), static fn ( string $type ): bool => preg_match( '/^[a-z0-9_-]{1,20}$/', $type ) === 1 ) );
		$override  = $revert ? [] : self::sanitize( is_array( $input['statuses'] ?? null ) ? $input['statuses'] : [], $postTypes );
		$status    = $override === [] ? DefinitionStatus::Archived : DefinitionStatus::Published;
		$candidate = new Definition(
			id: $existing->id ?? self::uuid(),
			slug: 'status-existing-core',
			type: self::DEFINITION_TYPE,
			schemaVersion: 1,
			ownerSurfaceId: 5,
			status: $status,
			payload: [ 'statuses' => $override ],
			revision: ( $existing->revision ?? 0 ) + 1,
		);
		$this->definitions->save( new Definition( $candidate->id, $candidate->slug, $candidate->type, 1, 5, $status, $candidate->payload, $candidate->revision, [], $candidate->computedChecksum() ) );
		$this->cache = null;

		return [
			'status'   => $status->value,
			'statuses' => $override,
		];
	}

	/** @return array<string,array<string,mixed>> */
	public function overrides(): array {
		if ( $this->cache === null ) {
			$definition  = $this->find();
			$this->cache = $definition !== null && $definition->status === DefinitionStatus::Published ? (array) ( $definition->payload['statuses'] ?? [] ) : [];
		}

		return $this->cache;
	}

	private function find(): ?Definition {
		return $this->definitions->byType( self::DEFINITION_TYPE )[0] ?? null;
	}

	public function handleForm(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change statuses.', 'wpessential' ), 403 );
		}
		check_admin_referer( self::ACTION );
		$input  = [
			'revert'     => isset( $_POST['revert'] ),
			'statuses'   => is_array( $_POST['statuses'] ?? null ) ? wp_unslash( $_POST['statuses'] ) : [], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize() allowlists every key.
			'post_types' => array_values( get_post_types( [ 'show_ui' => true ] ) ),
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
			set_transient( 'wpessential_existing_status_error_' . get_current_user_id(), $error->getMessage(), 60 );
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
		global $wp_post_statuses;
		$notice    = isset( $_GET['wpe_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['wpe_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
		$overrides = $this->overrides();
		$types     = get_post_types( [ 'show_ui' => true ], 'objects' );
		echo '<section class="wpe-ui-panel" aria-labelledby="wpessential-status-existing"><h2 id="wpessential-status-existing">' . esc_html__( 'Existing statuses', 'wpessential' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Rename the statuses WordPress ships with, or hide Pending, Private and Scheduled on post types that do not use them. Status keys and existing posts never change.', 'wpessential' ) . '</p>';
		if ( $notice === 'saved' ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Saved.', 'wpessential' ) . '</p></div>';
		} elseif ( $notice === 'error' ) {
			$message = (string) get_transient( 'wpessential_existing_status_error_' . get_current_user_id() );
			echo '<div class="notice notice-error"><p>' . esc_html( $message !== '' ? $message : __( 'Not saved.', 'wpessential' ) ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Status', 'wpessential' ) . '</th><th>' . esc_html__( 'Name', 'wpessential' ) . '</th><th>' . esc_html__( 'Plural name', 'wpessential' ) . '</th><th>' . esc_html__( 'Hide on', 'wpessential' ) . '</th></tr></thead><tbody>';
		foreach ( self::CORE as $key ) {
			$original = isset( $wp_post_statuses[ $key ] ) ? (string) $wp_post_statuses[ $key ]->label : $key;
			$row      = $overrides[ $key ] ?? [];
			echo '<tr><td><code>' . esc_html( $key ) . '</code></td>';
			echo '<td><input type="text" aria-label="' . esc_attr( $key . ' name' ) . '" name="statuses[' . esc_attr( $key ) . '][label]" value="' . esc_attr( (string) ( $row['label'] ?? '' ) ) . '" placeholder="' . esc_attr( $original ) . '"></td>';
			echo '<td><input type="text" aria-label="' . esc_attr( $key . ' plural name' ) . '" name="statuses[' . esc_attr( $key ) . '][plural]" value="' . esc_attr( (string) ( $row['plural'] ?? '' ) ) . '" placeholder="' . esc_attr( $original ) . '"></td><td>';
			if ( in_array( $key, self::HIDEABLE, true ) ) {
				foreach ( $types as $type ) {
					echo '<label><input type="checkbox" name="statuses[' . esc_attr( $key ) . '][hidden_for][]" value="' . esc_attr( $type->name ) . '"' . checked( in_array( $type->name, (array) ( $row['hidden_for'] ?? [] ), true ), true, false ) . '> ' . esc_html( (string) $type->label ) . '</label> ';
				}
			} else {
				echo '<span class="description">' . esc_html__( 'Always available', 'wpessential' ) . '</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'The block editor shows its own built-in wording for some statuses; list tables, quick edit and the classic editor use these names.', 'wpessential' ) . '</p>';
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
