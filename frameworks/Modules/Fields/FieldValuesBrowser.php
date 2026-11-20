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
use WPEssential\Platform\WordPress\Abilities\WordPressExecutionContextFactory;

/**
 * Saved values of a Field Group, for the list screen: how many objects hold values, and a table
 * of those values (object, field key, value) that can be updated or deleted in bulk.
 *
 * Values live under the field key in post, term or user meta, chosen by the group's storage mode.
 */
final class FieldValuesBrowser implements AbilityHandlerInterface {

	public const ABILITY     = 'wpessential/fields/values';
	public const AJAX_ACTION = 'wpessential_fields_values';
	private const LIMIT      = 500;

	private ?AbilityRegistry $abilities                = null;
	private ?WordPressExecutionContextFactory $contexts = null;

	public function __construct(
		private readonly DefinitionRepositoryInterface $definitions,
		private readonly object $wpdb,
		private readonly FieldValueNormalizer $values = new FieldValueNormalizer(),
		private readonly FieldValuePersistenceGuard $guard = new FieldValuePersistenceGuard(),
	) {}

	public function register( AbilityRegistry $abilities, WordPressExecutionContextFactory $contexts ): void {
		$this->abilities = $abilities;
		$this->contexts  = $contexts;
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax' ] );
	}

	public function ajax(): void {
		check_ajax_referer( self::AJAX_ACTION );
		$raw   = isset( $_POST['payload'] ) ? (string) wp_unslash( $_POST['payload'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and validated by handle().
		$input = json_decode( $raw, true );
		try {
			$current = $this->contexts?->current();
			if ( $this->abilities === null || $current === null || ! is_array( $input ) ) {
				throw new InvalidArgumentException( 'Request is not valid.' );
			}
			$context = new ExecutionContext( principal: $current->principal, siteId: $current->siteId, channel: ExecutionChannel::Ui, networkId: $current->networkId, correlationId: $current->correlationId );
			wp_send_json_success( $this->abilities->execute( self::ABILITY, $input, $context ) );
		} catch ( Throwable $error ) {
			wp_send_json_error( [ 'message' => $error->getMessage() ] );
		}
	}

	/** @param array<string,mixed> $input */
	public function handle( array $input, ExecutionContext $context ): mixed {
		$action = (string) ( $input['action'] ?? '' );
		if ( $action === 'counts' ) {
			$out = [];
			foreach ( $this->definitions->byType( FieldGroupDefinitionNormalizer::DEFINITION_TYPE ) as $group ) {
				$out[ $group->id ] = $this->count( $group );
			}

			return $out;
		}
		$group = $this->definitions->get( (string) ( $input['group_id'] ?? '' ) );
		if ( ! $group instanceof Definition || $group->type !== FieldGroupDefinitionNormalizer::DEFINITION_TYPE ) {
			throw new InvalidArgumentException( 'Unknown Field Group.' );
		}

		return match ( $action ) {
			'list'   => $this->rows( $group ),
			'delete' => $this->delete( $group, self::items( $input ) ),
			'update' => $this->update( $group, self::items( $input ) ),
			default  => throw new InvalidArgumentException( 'Unknown values action.' ),
		};
	}

	/** Number of objects (posts, terms or users) holding at least one value of the group. */
	public function count( Definition $group ): int {
		[$table, $column, $keys] = $this->target( $group );
		if ( $keys === [] ) {
			return 0;
		}
		$in = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column come from $wpdb, keys are placeholders.
		return (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(DISTINCT {$column}) FROM {$table} WHERE meta_key IN ({$in})", ...$keys ) );
	}

	/** @return array{kind:string,rows:list<array{object_id:int,object:string,key:string,label:string,value:string,editable:bool}>} */
	private function rows( Definition $group ): array {
		[$table, $column, $keys, $kind] = $this->target( $group );
		$rows                           = [];
		if ( $keys !== [] ) {
			$in    = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
			$found = $this->wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column come from $wpdb, keys are placeholders.
				$this->wpdb->prepare( "SELECT {$column} AS object_id, meta_key, meta_value FROM {$table} WHERE meta_key IN ({$in}) ORDER BY {$column} DESC, meta_key LIMIT " . self::LIMIT, ...$keys ),
				ARRAY_A
			);
			$labels = array_column( $this->fields( $group ), 'label', 'key' );
			foreach ( is_array( $found ) ? $found : [] as $row ) {
				$id      = (int) $row['object_id'];
				$value   = maybe_unserialize( $row['meta_value'] );
				$rows[]  = [
					'object_id' => $id,
					'object'    => self::objectLabel( $kind, $id ),
					'key'       => (string) $row['meta_key'],
					'label'     => (string) ( $labels[ $row['meta_key'] ] ?? $row['meta_key'] ),
					'value'     => is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value ),
					'editable'  => is_scalar( $value ),
				];
			}
		}

		return [
			'kind' => $kind,
			'rows' => $rows,
		];
	}

	/** @param list<array{object_id:int,key:string,value:mixed}> $items */
	private function delete( Definition $group, array $items ): array {
		[, , $keys, $kind] = $this->target( $group );
		$done              = 0;
		foreach ( $items as $item ) {
			if ( in_array( $item['key'], $keys, true ) && $this->can( $kind, $item['object_id'] ) ) {
				$done += delete_metadata( $kind, $item['object_id'], $item['key'] ) ? 1 : 0;
			}
		}

		return [ 'deleted' => $done ];
	}

	/** @param list<array{object_id:int,key:string,value:mixed}> $items */
	private function update( Definition $group, array $items ): array {
		[, , $keys, $kind] = $this->target( $group );
		$fields            = array_column( $this->fields( $group ), null, 'key' );
		$done              = 0;
		foreach ( $items as $item ) {
			$field = $fields[ $item['key'] ] ?? null;
			if ( ! is_array( $field ) || ! in_array( $item['key'], $keys, true ) || ! $this->can( $kind, $item['object_id'] ) ) {
				continue;
			}
			// Only plain values are edited here; lists and objects keep their own editors.
			if ( ! is_scalar( get_metadata( $kind, $item['object_id'], $item['key'], true ) ) ) {
				continue;
			}
			$value = $this->guard->assertSafe( $this->values->normalize( $field, $item['value'] ) );
			if ( $value === null ) {
				delete_metadata( $kind, $item['object_id'], $item['key'] );
			} else {
				update_metadata( $kind, $item['object_id'], $item['key'], $value );
			}
			++$done;
		}

		return [ 'updated' => $done ];
	}

	/** @return array{0:string,1:string,2:list<string>,3:string} Meta table, object id column, field keys, object kind. */
	private function target( Definition $group ): array {
		$kind = match ( (string) ( $group->payload['storage']['mode'] ?? 'native_post_meta' ) ) {
			'native_term_meta' => 'term',
			'native_user_meta' => 'user',
			default            => 'post',
		};
		$keys = array_values( array_filter( array_map( static fn ( array $field ): string => (string) ( $field['key'] ?? '' ), $this->fields( $group ) ) ) );

		return [ (string) $this->wpdb->{$kind . 'meta'}, $kind . '_id', $keys, $kind ];
	}

	/** @return list<array<string,mixed>> */
	private function fields( Definition $group ): array {
		return array_values( array_filter( (array) ( $group->payload['fields'] ?? [] ), 'is_array' ) );
	}

	private function can( string $kind, int $id ): bool {
		return current_user_can( match ( $kind ) {
			'term'  => 'edit_term',
			'user'  => 'edit_user',
			default => 'edit_post',
		}, $id );
	}

	private static function objectLabel( string $kind, int $id ): string {
		$object = match ( $kind ) {
			'term'  => get_term( $id ),
			'user'  => get_userdata( $id ),
			default => null,
		};
		$label  = match ( $kind ) {
			'term'  => $object instanceof \WP_Term ? $object->name : '',
			'user'  => $object instanceof \WP_User ? $object->display_name : '',
			default => get_the_title( $id ),
		};

		return ( $label !== '' ? $label : '(no title)' ) . ' #' . $id;
	}

	/**
	 * @param array<string,mixed> $input
	 * @return list<array{object_id:int,key:string,value:mixed}>
	 */
	private static function items( array $input ): array {
		$out = [];
		foreach ( is_array( $input['items'] ?? null ) ? $input['items'] : [] as $item ) {
			$id  = is_array( $item ) ? (int) ( $item['object_id'] ?? 0 ) : 0;
			$key = is_array( $item ) ? (string) ( $item['key'] ?? '' ) : '';
			if ( $id > 0 && $key !== '' ) {
				$out[] = [
					'object_id' => $id,
					'key'       => $key,
					'value'     => $item['value'] ?? null,
				];
			}
		}

		return $out;
	}
}
