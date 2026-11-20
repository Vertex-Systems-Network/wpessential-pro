<?php

declare(strict_types=1);

namespace WPEssential\Modules\CustomTables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;
use Throwable;
use WPEssential\Contracts\AbilityHandlerInterface;
use WPEssential\Contracts\DefinitionRepositoryInterface;
use WPEssential\Modules\CustomTables\Definition\TableDefinitionCompiler;
use WPEssential\Platform\Abilities\AbilityRegistry;
use WPEssential\Platform\Admin\PlatformAdminController;
use WPEssential\Platform\Auth\ExecutionChannel;
use WPEssential\Platform\Auth\ExecutionContext;
use WPEssential\Platform\Definitions\Definition;
use WPEssential\Platform\Definitions\DefinitionStatus;
use WPEssential\Platform\WordPress\Abilities\WordPressExecutionContextFactory;

/**
 * Create a Custom Content Type table and add columns to it.
 *
 * Every table gets an auto-increment `id` primary key, as JetEngine CCT does. Saved columns are
 * locked (key and type) because migrations are additive; the database change itself is applied
 * from the Custom Tables migration screen, which reviews it and requires a backup.
 */
final class CustomTablesTableBuilder implements AbilityHandlerInterface {

	public const ABILITY    = 'wpessential/custom-tables/save-table';
	private const PAGE_SLUG = 'wpessential-custom-table-builder';
	private const ACTION    = 'wpessential_custom_table_save';

	public const TYPES = [
		'varchar'  => 'Text (up to 191 characters)',
		'text'     => 'Long text',
		'integer'  => 'Whole number',
		'bigint'   => 'Big whole number',
		'decimal'  => 'Decimal number',
		'boolean'  => 'Yes / No',
		'datetime' => 'Date and time',
		'json'     => 'JSON',
	];

	public function __construct(
		private readonly DefinitionRepositoryInterface $definitions,
		private readonly TableDefinitionCompiler $compiler = new TableDefinitionCompiler(),
		private ?AbilityRegistry $abilities = null,
		private ?WordPressExecutionContextFactory $contexts = null,
	) {}

	/**
	 * Builder input (key, label, columns as rows) to the canonical table payload.
	 *
	 * @param array<string,mixed> $input
	 * @param array<string,mixed>|null $existing Saved payload; its columns stay as they are.
	 * @return array<string,mixed>
	 */
	public static function payload( array $input, ?array $existing = null ): array {
		$columns = [
			[
				'key'            => 'id',
				'type'           => 'bigint',
				'nullable'       => false,
				'auto_increment' => true,
			],
		];
		$saved   = [];
		foreach ( (array) ( $existing['columns'] ?? [] ) as $column ) {
			if ( ( $column['key'] ?? '' ) !== 'id' ) {
				$saved[ $column['key'] ] = $column;
			}
		}
		$columns = [ ...$columns, ...array_values( $saved ) ];
		foreach ( (array) ( $input['columns'] ?? [] ) as $row ) {
			$key = strtolower( trim( (string) ( $row['key'] ?? '' ) ) );
			if ( $key === '' || isset( $saved[ $key ] ) ) {
				continue; // Blank rows are spare slots; saved columns are not edited here.
			}
			if ( $key === 'id' ) {
				throw new InvalidArgumentException( 'The id column is added automatically.' );
			}
			$type   = (string) ( $row['type'] ?? '' );
			$column = [
				'key'      => $key,
				'type'     => $type,
				'nullable' => ( $row['required'] ?? '' ) !== '1',
			];
			if ( $type === 'varchar' ) {
				$column['length'] = (int) ( ( $row['length'] ?? '' ) !== '' ? $row['length'] : 191 );
			}
			if ( $type === 'decimal' ) {
				$column['precision'] = (int) ( ( $row['precision'] ?? '' ) !== '' ? $row['precision'] : 10 );
				$column['scale']     = (int) ( ( $row['scale'] ?? '' ) !== '' ? $row['scale'] : 2 );
			}
			$columns[] = $column;
		}
		$indexes = [];
		foreach ( $columns as $column ) {
			if ( ( $column['key'] ?? '' ) !== 'id' && in_array( $column['key'], array_map( 'strval', (array) ( $input['indexed'] ?? [] ) ), true ) ) {
				$indexes[] = [
					'key'     => 'idx_' . $column['key'],
					'columns' => [ $column['key'] ],
					'unique'  => false,
				];
			}
		}
		foreach ( (array) ( $existing['indexes'] ?? [] ) as $index ) {
			if ( ! in_array( $index['key'], array_column( $indexes, 'key' ), true ) ) {
				$indexes[] = $index;
			}
		}

		return [
			'key'                    => $existing['key'] ?? strtolower( trim( (string) ( $input['key'] ?? '' ) ) ),
			'label'                  => trim( wp_strip_all_tags( (string) ( $input['label'] ?? '' ) ) ),
			'storage_mode'           => 'managed',
			'scope'                  => 'site',
			'desired_schema_version' => (int) ( $existing['desired_schema_version'] ?? 0 ) + 1,
			'columns'                => $columns,
			'primary_key'            => [ 'id' ],
			'indexes'                => $indexes,
			'charset_collation'      => 'inherit',
			'data_classification'    => 'internal',
		];
	}

	/** Ability: {id?, key, label, columns: list<{key,type,required?,length?,precision?,scale?}>, indexed?: list<string>}. */
	public function handle( array $input, ExecutionContext $context ): mixed {
		$id       = (string) ( $input['id'] ?? '' );
		$existing = $id !== '' ? $this->definitions->get( $id ) : null;
		if ( $id !== '' && ( $existing === null || $existing->type !== TableDefinitionCompiler::TYPE ) ) {
			throw new InvalidArgumentException( 'Unknown table.' );
		}
		if ( $existing !== null && ( $existing->payload['primary_key'] ?? null ) !== [ 'id' ] ) {
			throw new InvalidArgumentException( 'This table was defined outside the builder (its primary key is not "id"); edit it where it was created.' );
		}
		$payload = self::payload( $input, $existing?->payload );
		if ( $existing === null ) {
			foreach ( $this->definitions->byType( TableDefinitionCompiler::TYPE ) as $other ) {
				if ( ( $other->payload['key'] ?? null ) === $payload['key'] ) {
					throw new InvalidArgumentException( 'A table with this key already exists.' );
				}
			}
		}
		$candidate = new Definition(
			id: $existing->id ?? self::uuid(),
			slug: str_replace( '_', '-', (string) $payload['key'] ),
			type: TableDefinitionCompiler::TYPE,
			schemaVersion: TableDefinitionCompiler::SCHEMA_VERSION,
			ownerSurfaceId: TableDefinitionCompiler::OWNER_SURFACE_ID,
			status: DefinitionStatus::Published,
			payload: $payload,
			revision: ( $existing->revision ?? 0 ) + 1,
		);
		$this->compiler->compile( $candidate ); // Same rules the migration planner applies.
		$this->definitions->save( new Definition( $candidate->id, $candidate->slug, $candidate->type, $candidate->schemaVersion, $candidate->ownerSurfaceId, $candidate->status, $candidate->payload, $candidate->revision, [], $candidate->computedChecksum() ) );

		return [
			'id'       => $candidate->id,
			'key'      => $payload['key'],
			'revision' => $candidate->revision,
		];
	}

	public function register( AbilityRegistry $abilities, WordPressExecutionContextFactory $contexts ): void {
		$this->abilities = $abilities;
		$this->contexts  = $contexts;
		add_action(
			'admin_menu',
			function (): void {
				add_submenu_page( PlatformAdminController::PAGE_SLUG, __( 'Table builder', 'wpessential' ), __( 'Table builder', 'wpessential' ), CustomTablesModule::CAPABILITY, self::PAGE_SLUG, [ $this, 'renderPage' ] );
			},
			20,
		);
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handleForm' ] );
	}

	public function handleForm(): void {
		if ( ! current_user_can( CustomTablesModule::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change Custom Tables.', 'wpessential' ), 403 );
		}
		check_admin_referer( self::ACTION );
		$input  = [
			'id'      => sanitize_text_field( wp_unslash( (string) ( $_POST['id'] ?? '' ) ) ),
			'key'     => sanitize_key( wp_unslash( (string) ( $_POST['key'] ?? '' ) ) ),
			'label'   => sanitize_text_field( wp_unslash( (string) ( $_POST['label'] ?? '' ) ) ),
			'columns' => is_array( $_POST['columns'] ?? null ) ? wp_unslash( $_POST['columns'] ) : [], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- payload() and the compiler allowlist every value.
			'indexed' => is_array( $_POST['indexed'] ?? null ) ? array_map( 'sanitize_key', wp_unslash( $_POST['indexed'] ) ) : [],
		];
		$query = [ 'page' => self::PAGE_SLUG ];
		try {
			$current = $this->contexts?->current();
			if ( $this->abilities === null || $current === null ) {
				throw new InvalidArgumentException( 'Abilities are unavailable.' );
			}
			$context     = new ExecutionContext( principal: $current->principal, siteId: $current->siteId, channel: ExecutionChannel::Ui, networkId: $current->networkId, correlationId: $current->correlationId );
			$result      = $this->abilities->execute( self::ABILITY, $input, $context );
			$query['id'] = is_array( $result ) ? (string) ( $result['id'] ?? '' ) : '';
			$query['wpe_notice'] = 'saved';
		} catch ( Throwable $error ) {
			$query['id']         = $input['id'];
			$query['wpe_notice'] = 'error';
			set_transient( 'wpessential_table_builder_error_' . get_current_user_id(), $error->getMessage(), 60 );
		}
		wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function renderPage(): void {
		if ( ! current_user_can( CustomTablesModule::CAPABILITY ) ) {
			return;
		}
		$id      = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selection.
		$notice  = isset( $_GET['wpe_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['wpe_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = $id !== '' ? $this->definitions->get( $id ) : null;
		$payload = $current !== null ? $current->payload : [];
		echo '<div class="wrap"><h1>' . esc_html__( 'Table builder', 'wpessential' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Define a Custom Content Type table. Every table gets an "id" column. After saving, create or update the database table from the Custom Tables screen, which reviews the change and requires a backup.', 'wpessential' ) . '</p>';
		if ( $notice === 'saved' ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Saved. Apply it from the Custom Tables screen.', 'wpessential' ) . ' <a href="' . esc_url( add_query_arg( 'page', CustomTablesAdminController::PAGE_SLUG, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Open Custom Tables', 'wpessential' ) . '</a></p></div>';
		} elseif ( $notice === 'error' ) {
			$message = (string) get_transient( 'wpessential_table_builder_error_' . get_current_user_id() );
			echo '<div class="notice notice-error"><p>' . esc_html( $message !== '' ? $message : __( 'Not saved.', 'wpessential' ) ) . '</p></div>';
		}
		echo '<h2>' . esc_html__( 'Tables', 'wpessential' ) . '</h2><ul>';
		foreach ( $this->definitions->byType( TableDefinitionCompiler::TYPE ) as $table ) {
			echo '<li><a href="' . esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'id' => $table->id ], admin_url( 'admin.php' ) ) ) . '">' . esc_html( (string) ( $table->payload['label'] ?? $table->slug ) ) . '</a> <code>' . esc_html( (string) ( $table->payload['key'] ?? '' ) ) . '</code></li>';
		}
		echo '<li><a href="' . esc_url( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( '+ New table', 'wpessential' ) . '</a></li></ul>';

		echo '<h2>' . esc_html( $current !== null ? __( 'Edit table', 'wpessential' ) : __( 'New table', 'wpessential' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="id" value="' . esc_attr( $current->id ?? '' ) . '">';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="wpe-table-label">' . esc_html__( 'Name', 'wpessential' ) . '</label></th><td><input class="regular-text" id="wpe-table-label" name="label" required value="' . esc_attr( (string) ( $payload['label'] ?? '' ) ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="wpe-table-key">' . esc_html__( 'Key', 'wpessential' ) . '</label></th><td><input class="regular-text" id="wpe-table-key" name="key" pattern="[a-z][a-z0-9_]*" required value="' . esc_attr( (string) ( $payload['key'] ?? '' ) ) . '"' . ( $current !== null ? ' readonly' : '' ) . '><p class="description">' . esc_html__( 'Lowercase letters, numbers and underscores. It cannot change after saving.', 'wpessential' ) . '</p></td></tr>';
		echo '</tbody></table>';

		$indexed = array_map( static fn ( array $index ): string => (string) ( $index['columns'][0] ?? '' ), (array) ( $payload['indexes'] ?? [] ) );
		echo '<h3>' . esc_html__( 'Columns', 'wpessential' ) . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Key', 'wpessential' ) . '</th><th>' . esc_html__( 'Type', 'wpessential' ) . '</th><th>' . esc_html__( 'Required', 'wpessential' ) . '</th><th>' . esc_html__( 'Length / precision, scale', 'wpessential' ) . '</th><th>' . esc_html__( 'Searchable (index)', 'wpessential' ) . '</th></tr></thead><tbody>';
		echo '<tr><td><code>id</code></td><td>' . esc_html__( 'Automatic number', 'wpessential' ) . '</td><td>' . esc_html__( 'Yes', 'wpessential' ) . '</td><td></td><td></td></tr>';
		foreach ( (array) ( $payload['columns'] ?? [] ) as $column ) {
			if ( ( $column['key'] ?? '' ) === 'id' ) {
				continue;
			}
			$size = isset( $column['length'] ) ? (string) $column['length'] : ( isset( $column['precision'] ) ? $column['precision'] . ', ' . ( $column['scale'] ?? 0 ) : '' );
			echo '<tr><td><code>' . esc_html( (string) $column['key'] ) . '</code></td><td>' . esc_html( self::TYPES[ $column['type'] ] ?? (string) $column['type'] ) . '</td><td>' . esc_html( ( $column['nullable'] ?? true ) ? __( 'No', 'wpessential' ) : __( 'Yes', 'wpessential' ) ) . '</td><td>' . esc_html( $size ) . '</td>';
			echo '<td><label><input type="checkbox" name="indexed[]" value="' . esc_attr( (string) $column['key'] ) . '"' . checked( in_array( $column['key'], $indexed, true ), true, false ) . '> ' . esc_html__( 'Index', 'wpessential' ) . '</label></td></tr>';
		}
		for ( $row = 0; $row < 5; $row++ ) {
			echo '<tr><td><input aria-label="' . esc_attr__( 'New column key', 'wpessential' ) . '" name="columns[' . (int) $row . '][key]" pattern="[a-z][a-z0-9_]*" placeholder="new_column"></td><td><select aria-label="' . esc_attr__( 'New column type', 'wpessential' ) . '" name="columns[' . (int) $row . '][type]">';
			foreach ( self::TYPES as $type => $label ) {
				echo '<option value="' . esc_attr( $type ) . '">' . esc_html( $label ) . '</option>';
			}
			echo '</select></td><td><label><input type="checkbox" name="columns[' . (int) $row . '][required]" value="1"> ' . esc_html__( 'Required', 'wpessential' ) . '</label></td>';
			echo '<td><input type="number" min="1" max="191" aria-label="' . esc_attr__( 'Length', 'wpessential' ) . '" name="columns[' . (int) $row . '][length]" placeholder="191" style="width:6em"> <input type="number" min="1" max="38" aria-label="' . esc_attr__( 'Precision', 'wpessential' ) . '" name="columns[' . (int) $row . '][precision]" placeholder="10" style="width:5em"> <input type="number" min="0" max="38" aria-label="' . esc_attr__( 'Scale', 'wpessential' ) . '" name="columns[' . (int) $row . '][scale]" placeholder="2" style="width:5em"></td><td></td></tr>';
		}
		echo '</tbody></table><p class="description">' . esc_html__( 'Fill a blank row to add a column. Saved columns cannot be renamed, retyped or removed here, because table changes only add.', 'wpessential' ) . '</p>';
		submit_button( __( 'Save table', 'wpessential' ) );
		echo '</form></div>';
	}

	private static function uuid(): string {
		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $bytes ), 4 ) );
	}
}
