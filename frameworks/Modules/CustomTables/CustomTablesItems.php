<?php

declare(strict_types=1);

namespace WPEssential\Modules\CustomTables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;
use WPEssential\Contracts\AbilityHandlerInterface;
use WPEssential\Contracts\DefinitionRepositoryInterface;
use WPEssential\Modules\CustomTables\Definition\TableDefinitionCompiler;
use WPEssential\Modules\CustomTables\Schema\Ct1ManagedTableIdentity;
use WPEssential\Platform\Abilities\AbilityRegistry;
use WPEssential\Platform\Admin\PlatformAdminController;
use WPEssential\Platform\Auth\ExecutionChannel;
use WPEssential\Platform\Auth\ExecutionContext;
use WPEssential\Platform\Definitions\Definition;
use WPEssential\Platform\WordPress\Abilities\WordPressExecutionContextFactory;

/**
 * List, add, edit and delete the rows (items) of a Custom Content Type table.
 *
 * Only tables made with the Table builder (primary key "id") are offered. Every value is checked
 * against its column type before it reaches $wpdb->insert() / update() / delete(), which prepare
 * the statement; the table name comes from the managed identity, never from input.
 */
final class CustomTablesItems implements AbilityHandlerInterface {

	public const ABILITY     = 'wpessential/custom-tables/item';
	public const CAPABILITY  = 'manage_options';
	private const PAGE_SLUG  = 'wpessential-custom-table-items';
	private const ACTION     = 'wpessential_custom_table_item';
	private const PER_PAGE   = 20;

	/**
	 * @param object $db wpdb, or any object with insert/update/delete/get_row/get_results/get_var/prepare/esc_like.
	 */
	public function __construct(
		private readonly DefinitionRepositoryInterface $definitions,
		private readonly object $db,
		private readonly string $sitePrefix,
		private ?AbilityRegistry $abilities = null,
		private ?WordPressExecutionContextFactory $contexts = null,
	) {}

	/**
	 * Form strings to values of the column types; required columns must be filled.
	 *
	 * @param list<array<string,mixed>> $columns
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public static function values( array $columns, array $input ): array {
		$out = [];
		foreach ( $columns as $column ) {
			$key = (string) $column['key'];
			if ( $key === 'id' ) {
				continue;
			}
			$raw = $input[ $key ] ?? null;
			$raw = is_string( $raw ) ? trim( $raw ) : $raw;
			if ( $column['type'] === 'boolean' ) {
				$out[ $key ] = in_array( $raw, [ '1', 1, true, 'true', 'on' ], true ) ? 1 : 0;
				continue;
			}
			if ( $raw === null || $raw === '' ) {
				if ( ( $column['nullable'] ?? true ) === false ) {
					throw new InvalidArgumentException( sprintf( '"%s" is required.', $key ) );
				}
				$out[ $key ] = null;
				continue;
			}
			$out[ $key ] = match ( $column['type'] ) {
				'varchar' => is_string( $raw ) && mb_strlen( $raw ) <= (int) ( $column['length'] ?? 191 ) ? $raw : throw new InvalidArgumentException( sprintf( '"%s" is too long.', $key ) ),
				'text' => is_string( $raw ) ? $raw : throw new InvalidArgumentException( sprintf( '"%s" must be text.', $key ) ),
				'integer', 'bigint' => is_numeric( $raw ) && (string) (int) $raw === (string) $raw ? (int) $raw : throw new InvalidArgumentException( sprintf( '"%s" must be a whole number.', $key ) ),
				'decimal' => is_numeric( $raw ) ? (string) $raw : throw new InvalidArgumentException( sprintf( '"%s" must be a number.', $key ) ),
				'datetime' => self::datetime( $key, $raw ),
				'json' => is_string( $raw ) && json_decode( $raw ) !== null ? $raw : throw new InvalidArgumentException( sprintf( '"%s" must be valid JSON.', $key ) ),
				default => throw new InvalidArgumentException( sprintf( 'Column "%s" has an unsupported type.', $key ) ),
			};
		}

		return $out;
	}

	private static function datetime( string $key, mixed $raw ): string {
		$value = is_string( $raw ) ? str_replace( 'T', ' ', $raw ) : '';
		foreach ( [ 'Y-m-d H:i:s', 'Y-m-d H:i' ] as $format ) {
			$date = DateTimeImmutable::createFromFormat( $format, $value );
			if ( $date instanceof DateTimeImmutable && $date->format( $format ) === $value ) {
				return $date->format( 'Y-m-d H:i:s' );
			}
		}
		throw new InvalidArgumentException( sprintf( '"%s" must be a date and time.', $key ) );
	}

	/** Ability: {definition_id, operation: save|delete, id?, values?}. */
	public function handle( array $input, ExecutionContext $context ): mixed {
		$definition = $this->table( (string) ( $input['definition_id'] ?? '' ) );
		$table      = $this->physical( $definition );
		$operation  = (string) ( $input['operation'] ?? '' );
		$id         = (int) ( $input['id'] ?? 0 );
		if ( $operation === 'delete' ) {
			if ( $id < 1 || $this->db->delete( $table, [ 'id' => $id ], [ '%d' ] ) === false ) {
				throw new InvalidArgumentException( 'The item could not be deleted.' );
			}

			return [ 'deleted' => $id ];
		}
		if ( $operation !== 'save' ) {
			throw new InvalidArgumentException( 'operation must be save or delete.' );
		}
		$values  = self::values( (array) $definition->payload['columns'], is_array( $input['values'] ?? null ) ? $input['values'] : [] );
		$formats = array_map( static fn ( mixed $value ): string => is_int( $value ) ? '%d' : '%s', $values );
		if ( $id > 0 ) {
			if ( $this->db->update( $table, $values, [ 'id' => $id ], array_values( $formats ), [ '%d' ] ) === false ) {
				throw new InvalidArgumentException( 'The item could not be saved. Has the table been created on the Custom Tables screen?' );
			}
		} else {
			if ( $this->db->insert( $table, $values, array_values( $formats ) ) === false ) {
				throw new InvalidArgumentException( 'The item could not be saved. Has the table been created on the Custom Tables screen?' );
			}
			$id = (int) ( $this->db->insert_id ?? 0 );
		}

		return [
			'id'     => $id,
			'values' => $values,
		];
	}

	private function table( string $definitionId ): Definition {
		$definition = $this->definitions->get( $definitionId );
		if ( $definition === null || $definition->type !== TableDefinitionCompiler::TYPE || ( $definition->payload['primary_key'] ?? null ) !== [ 'id' ] ) {
			throw new InvalidArgumentException( 'Unknown table, or a table not made with the Table builder.' );
		}

		return $definition;
	}

	private function physical( Definition $definition ): string {
		return Ct1ManagedTableIdentity::derive( $definition->id, (string) $definition->payload['key'], $this->sitePrefix )->physicalName;
	}

	public function register( AbilityRegistry $abilities, WordPressExecutionContextFactory $contexts ): void {
		$this->abilities = $abilities;
		$this->contexts  = $contexts;
		add_action(
			'admin_menu',
			function (): void {
				add_submenu_page( PlatformAdminController::PAGE_SLUG, __( 'Table items', 'wpessential' ), __( 'Table items', 'wpessential' ), self::CAPABILITY, self::PAGE_SLUG, [ $this, 'renderPage' ] );
			},
			20,
		);
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handleForm' ] );
	}

	public function handleForm(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change table items.', 'wpessential' ), 403 );
		}
		check_admin_referer( self::ACTION );
		$definitionId = sanitize_text_field( wp_unslash( (string) ( $_POST['definition_id'] ?? '' ) ) );
		$input        = [
			'definition_id' => $definitionId,
			'operation'     => isset( $_POST['delete'] ) ? 'delete' : 'save',
			'id'            => absint( $_POST['id'] ?? 0 ),
			'values'        => is_array( $_POST['values'] ?? null ) ? wp_unslash( $_POST['values'] ) : [], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- values() checks every column type.
		];
		$query = [
			'page'  => self::PAGE_SLUG,
			'table' => $definitionId,
		];
		try {
			$current = $this->contexts?->current();
			if ( $this->abilities === null || $current === null ) {
				throw new InvalidArgumentException( 'Abilities are unavailable.' );
			}
			$context             = new ExecutionContext( principal: $current->principal, siteId: $current->siteId, channel: ExecutionChannel::Ui, networkId: $current->networkId, correlationId: $current->correlationId );
			$this->abilities->execute( self::ABILITY, $input, $context );
			$query['wpe_notice'] = $input['operation'] === 'delete' ? 'deleted' : 'saved';
		} catch ( Throwable $error ) {
			$query['wpe_notice'] = 'error';
			$query['item']       = $input['id'];
			set_transient( 'wpessential_table_items_error_' . get_current_user_id(), $error->getMessage(), 60 );
		}
		wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function renderPage(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen state.
		$definitionId = isset( $_GET['table'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['table'] ) ) : '';
		$itemId       = isset( $_GET['item'] ) ? absint( $_GET['item'] ) : -1;
		$paged        = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$notice       = isset( $_GET['wpe_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['wpe_notice'] ) ) : '';
		// phpcs:enable
		echo '<div class="wrap"><h1>' . esc_html__( 'Table items', 'wpessential' ) . '</h1>';
		if ( $notice === 'error' ) {
			$message = (string) get_transient( 'wpessential_table_items_error_' . get_current_user_id() );
			echo '<div class="notice notice-error"><p>' . esc_html( $message !== '' ? $message : __( 'Not saved.', 'wpessential' ) ) . '</p></div>';
		} elseif ( $notice !== '' ) {
			echo '<div class="notice notice-success"><p>' . esc_html( $notice === 'deleted' ? __( 'Item deleted.', 'wpessential' ) : __( 'Item saved.', 'wpessential' ) ) . '</p></div>';
		}
		$tables = array_values( array_filter( $this->definitions->byType( TableDefinitionCompiler::TYPE ), static fn ( Definition $table ): bool => ( $table->payload['primary_key'] ?? null ) === [ 'id' ] ) );
		echo '<p>';
		foreach ( $tables as $table ) {
			$url = add_query_arg( [ 'page' => self::PAGE_SLUG, 'table' => $table->id ], admin_url( 'admin.php' ) );
			echo '<a class="button' . ( $table->id === $definitionId ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( (string) ( $table->payload['label'] ?? $table->slug ) ) . '</a> ';
		}
		if ( $tables === [] ) {
			echo esc_html__( 'No tables yet. Create one with the Table builder.', 'wpessential' );
		}
		echo '</p>';
		$definition = null;
		foreach ( $tables as $table ) {
			if ( $table->id === $definitionId ) {
				$definition = $table;
			}
		}
		if ( $definition === null ) {
			echo '</div>';
			return;
		}
		$name    = $this->physical( $definition );
		$columns = (array) $definition->payload['columns'];
		if ( $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $this->db->esc_like( $name ) ) ) !== $name ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'This table is defined but not created in the database yet. Apply it from the Custom Tables screen first.', 'wpessential' ) . '</p></div></div>';
			return;
		}
		if ( $itemId >= 0 ) {
			$row = $itemId > 0 ? (array) $this->db->get_row( $this->db->prepare( "SELECT * FROM `{$name}` WHERE id = %d", $itemId ), ARRAY_A ) : []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- managed physical name, never input.
			$this->renderForm( $definition, $columns, $itemId, $row );
		}
		$total  = (int) $this->db->get_var( "SELECT COUNT(*) FROM `{$name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- managed physical name.
		$offset = ( $paged - 1 ) * self::PER_PAGE;
		$rows   = (array) $this->db->get_results( $this->db->prepare( "SELECT * FROM `{$name}` ORDER BY id DESC LIMIT %d OFFSET %d", self::PER_PAGE, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- managed physical name.
		$new    = add_query_arg( [ 'page' => self::PAGE_SLUG, 'table' => $definition->id, 'item' => 0 ], admin_url( 'admin.php' ) );
		echo '<h2>' . esc_html( (string) ( $definition->payload['label'] ?? '' ) ) . ' <a class="page-title-action" href="' . esc_url( $new ) . '">' . esc_html__( 'Add item', 'wpessential' ) . '</a></h2>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array_slice( $columns, 0, 6 ) as $column ) {
			echo '<th scope="col">' . esc_html( (string) $column['key'] ) . '</th>';
		}
		echo '<th></th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( array_slice( $columns, 0, 6 ) as $column ) {
				echo '<td>' . esc_html( mb_strimwidth( (string) ( $row[ $column['key'] ] ?? '' ), 0, 80, '…' ) ) . '</td>';
			}
			$edit = add_query_arg( [ 'page' => self::PAGE_SLUG, 'table' => $definition->id, 'item' => (int) $row['id'] ], admin_url( 'admin.php' ) );
			echo '<td><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'wpessential' ) . '</a></td></tr>';
		}
		if ( $rows === [] ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No items yet.', 'wpessential' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages > 1 ) {
			echo '<p>';
			for ( $page = 1; $page <= $pages; $page++ ) {
				$url = add_query_arg( [ 'page' => self::PAGE_SLUG, 'table' => $definition->id, 'paged' => $page ], admin_url( 'admin.php' ) );
				echo $page === $paged ? '<strong>' . (int) $page . '</strong> ' : '<a href="' . esc_url( $url ) . '">' . (int) $page . '</a> ';
			}
			echo '</p>';
		}
		echo '</div>';
	}

	/**
	 * @param list<array<string,mixed>> $columns
	 * @param array<string,mixed> $row
	 */
	private function renderForm( Definition $definition, array $columns, int $itemId, array $row ): void {
		echo '<h2>' . esc_html( $itemId > 0 ? sprintf( /* translators: %d: item id */ __( 'Edit item #%d', 'wpessential' ), $itemId ) : __( 'New item', 'wpessential' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="definition_id" value="' . esc_attr( $definition->id ) . '"><input type="hidden" name="id" value="' . (int) $itemId . '">';
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $columns as $column ) {
			$key = (string) $column['key'];
			if ( $key === 'id' ) {
				continue;
			}
			$value    = (string) ( $row[ $key ] ?? '' );
			$id       = 'wpe-item-' . $key;
			$name     = 'values[' . $key . ']';
			$required = ( $column['nullable'] ?? true ) === false && $column['type'] !== 'boolean' ? ' required' : '';
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $key ) . '</label></th><td>';
			echo match ( $column['type'] ) {
				'text', 'json' => '<textarea class="large-text" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $required . '>' . esc_textarea( $value ) . '</textarea>',
				'boolean' => '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( $value, '1', false ) . '>',
				'integer', 'bigint' => '<input type="number" step="1" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $required . '>',
				'decimal' => '<input type="number" step="any" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $required . '>',
				'datetime' => '<input type="datetime-local" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value !== '' ? str_replace( ' ', 'T', substr( $value, 0, 16 ) ) : '' ) . '"' . $required . '>',
				default => '<input class="regular-text" maxlength="' . (int) ( $column['length'] ?? 191 ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $required . '>',
			};
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		submit_button( __( 'Save item', 'wpessential' ), 'primary', 'submit', false );
		if ( $itemId > 0 ) {
			echo ' ';
			submit_button( __( 'Delete item', 'wpessential' ), 'delete', 'delete', false, [ 'onclick' => "return confirm('" . esc_js( __( 'Delete this item?', 'wpessential' ) ) . "')" ] );
		}
		echo '</form>';
	}
}
