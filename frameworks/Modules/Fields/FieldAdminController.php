<?php

declare(strict_types=1);

namespace WPEssential\Modules\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;
use Throwable;
use WPEssential\Contracts\DefinitionRepositoryInterface;
use WPEssential\Platform\Abilities\AbilityRegistry;
use WPEssential\Platform\Admin\AdminAssetManifest;
use WPEssential\Platform\Admin\PlatformAdminController;
use WPEssential\Platform\Auth\ExecutionChannel;
use WPEssential\Platform\Auth\ExecutionContext;
use WPEssential\Platform\WordPress\Abilities\WordPressExecutionContextFactory;
use WPEssential\Platform\WordPress\Ajax\AjaxDispatcher;
use WPEssential\Platform\Admin\Ui\Shell;

final class FieldAdminController {

	public const PAGE_SLUG   = 'wpessential-fields';
	private const CAPABILITY = 'manage_options';

	private ?string $hookSuffix = null;

	public function __construct(
		private readonly AbilityRegistry $abilities,
		private readonly WordPressExecutionContextFactory $contexts,
		private readonly AjaxDispatcher $ajax,
		private readonly AdminAssetManifest $assets,
		private readonly FieldAdminCatalogProjector $catalogProjector,
		private readonly string $ajaxAction,
		private readonly ?DefinitionRepositoryInterface $definitions = null,
	) {
		if ( trim( $this->ajaxAction ) === '' ) {
			throw new InvalidArgumentException( 'Fields admin AJAX action cannot be blank.' );
		}
	}

	public function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'registerMenu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
	}

	public function registerMenu(): void {
		if ( ! function_exists( 'add_submenu_page' ) ) {
			return;
		}

		$hook = add_submenu_page(
			PlatformAdminController::PAGE_SLUG,
			__( 'Custom Fields', 'wpessential' ),
			__( 'Custom Fields', 'wpessential' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ $this, 'render' ],
		);

		$this->hookSuffix = is_string( $hook ) && $hook !== '' ? $hook : null;
	}

	public function enqueueAssets( string $hookSuffix ): void {
		if ( $this->hookSuffix === null || $hookSuffix !== $this->hookSuffix ) {
			return;
		}

		$entry = $this->assets->entry( 'fields' );
		if ( $entry === null ) {
			return;
		}

		$version = $entry['version'] ?? ( defined( 'WPE_VERSION' ) ? (string) WPE_VERSION : null );
		foreach ( $entry['styles'] as $index => $style ) {
			wp_enqueue_style( 'wpessential-fields-admin-' . $index, $style, [], $version );
		}
		wp_enqueue_script(
			'wpessential-fields-admin',
			$entry['script'],
			$entry['dependencies'],
			$version,
			true,
		);
		if ( function_exists( 'wp_script_add_data' ) ) {
			wp_script_add_data( 'wpessential-fields-admin', 'strategy', 'defer' );
		}
	}

	public function render(): void {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( self::CAPABILITY ) ) {
			if ( function_exists( 'wp_die' ) ) {
				wp_die(
					esc_html__( 'You are not allowed to manage WPEssential Custom Fields.', 'wpessential' ),
					esc_html__( 'WPEssential', 'wpessential' ),
					[ 'response' => 403 ],
				);
			}
			return;
		}

		[$definitions, $catalog, $readError] = $this->readSnapshot();
		// List and editor are separate screens, like the post type builder.
		$editing = isset( $_GET['view'] ) && sanitize_key( wp_unslash( (string) $_GET['view'] ) ) === 'edit'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		$editId  = isset( $_GET['definition'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['definition'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		$notice  = isset( $_GET['wpe_notice'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['wpe_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
		$listUrl = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=' . self::PAGE_SLUG ) : '';
		$editUrl = $listUrl . '&view=edit';
		$bootstrap                           = [
			'surface'     => 'custom-fields',
			'ajaxUrl'     => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '',
			'ajaxAction'  => $this->ajaxAction,
			'routes'      => [
				'list'     => [
					'type'  => 'fields.list.groups',
					'nonce' => $this->ajax->createNonce( 'fields.list.groups' ),
				],
				'catalog'  => [
					'type'  => 'fields.catalog',
					'nonce' => $this->ajax->createNonce( 'fields.catalog' ),
				],
				'validate' => [
					'type'  => 'fields.validate.group',
					'nonce' => $this->ajax->createNonce( 'fields.validate.group' ),
				],
				'save'     => [
					'type'  => 'fields.save.group',
					'nonce' => $this->ajax->createNonce( 'fields.save.group' ),
				],
				'status'   => [
					'type'  => 'fields.status.group',
					'nonce' => $this->ajax->createNonce( 'fields.status.group' ),
				],
			],
			'definitions' => $definitions,
			'catalog'     => $catalog,
			'view'        => $editing ? 'edit' : 'list',
			'editId'      => $editId,
			'listUrl'     => $listUrl,
			'editUrl'     => $editUrl,
			'values'      => [
				'ajaxUrl' => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '',
				'action'  => FieldValuesBrowser::AJAX_ACTION,
				'nonce'   => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( FieldValuesBrowser::AJAX_ACTION ) : '',
			],
			'listMeta'    => $editing ? [] : $this->listMeta( $definitions ),
			// Choices for the "Show this group when" value pickers; posts are searched through REST.
			'locations'   => self::locationChoices(),
		];
		$json                                = function_exists( 'wp_json_encode' )
			? wp_json_encode( $bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT )
			: json_encode( $bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		$json                                = is_string( $json ) ? $json : '{}';

		echo '<div class="wrap wpe-ui wpessential-fields-wrap">';
		echo Shell::topbar( self::PAGE_SLUG ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- toolkit component escapes its own output.
		echo '<section id="wpessential-fields-root" aria-labelledby="wpessential-fields-title">';
		echo '<h1 id="wpessential-fields-title">' . esc_html__( 'Custom Fields', 'wpessential' ) . '</h1>';
		echo '<p>' . esc_html__( 'Build canonical Field Groups through the shared Ability layer. Existing field UUIDs and storage keys remain immutable in ordinary editing.', 'wpessential' ) . '</p>';
		if ( $readError !== null ) {
			echo '<div class="notice notice-error inline" role="alert"><p>' . esc_html( $readError ) . '</p></div>';
		}
		echo '<div id="wpessential-fields-notice" class="notice inline' . ( $notice !== '' ? ' notice-success' : '' ) . '" role="status" aria-live="polite"' . ( $notice !== '' ? '' : ' hidden' ) . '><p>' . esc_html( $notice ) . '</p></div>';
		if ( $editing ) {
			echo '<p><a class="button" href="' . esc_url( $listUrl ) . '">' . esc_html__( '← All field groups', 'wpessential' ) . '</a></p>';
			$this->renderEditor( $catalog );
		} else {
			echo '<p><a class="button button-primary" id="wpessential-fields-add-new" href="' . esc_url( $editUrl ) . '">' . esc_html__( 'Add field group', 'wpessential' ) . '</a></p>';
			$this->renderDefinitions( $definitions, $this->listMeta( $definitions ) );
		}
		echo '<noscript><p class="notice notice-warning inline">' . esc_html__( 'JavaScript is required to create or edit Field Groups. Saved groups remain readable below.', 'wpessential' ) . '</p></noscript>';
		echo '</section>';
		echo '<script id="wpessential-fields-bootstrap" type="application/json">' . $json . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_* flags.
		echo '</div>';
	}

	/** @param array<string,mixed> $catalog */
	private function renderEditor( array $catalog ): void {
		echo '<section class="wpessential-fields-panel" aria-labelledby="wpessential-fields-editor-title">';
		echo '<h2 id="wpessential-fields-editor-title">' . esc_html__( 'Add field group', 'wpessential' ) . '</h2>';
		echo '<form id="wpessential-fields-form">';
		echo '<input type="hidden" id="wpessential-fields-id" value="">';
		echo '<input type="hidden" id="wpessential-fields-revision" value="">';
		echo '<div class="wpessential-fields-grid">';
		$this->renderTextField( 'wpessential-fields-group-key', __( 'Group key', 'wpessential' ), 'event_details', true, __( 'Lowercase machine key. Existing group keys remain read-only in this V1 editor.', 'wpessential' ) );
		$this->renderTextField( 'wpessential-fields-group-title', __( 'Title', 'wpessential' ), 'Event details', true );
		$this->renderTextField( 'wpessential-fields-group-description', __( 'Description', 'wpessential' ), '', false );
		echo '</div>';
		echo '<p><label><input type="checkbox" id="wpessential-fields-show-rest"> ' . esc_html__( 'Expose values in REST when the certified storage compiler allows it', 'wpessential' ) . '</label></p>';

		echo '<div class="wpessential-fields-list-heading"><h3>' . esc_html__( 'Fields', 'wpessential' ) . '</h3>';
		echo '<div><label for="wpessential-fields-add-type" class="screen-reader-text">' . esc_html__( 'Field type', 'wpessential' ) . '</label>';
		echo '<select id="wpessential-fields-add-type"><option value="">' . esc_html__( 'Select field type…', 'wpessential' ) . '</option>';
		foreach ( ( $catalog['types'] ?? [] ) as $type ) {
			if ( ! is_array( $type ) ) {
				continue;
			}
			$key   = is_string( $type['key'] ?? null ) ? $type['key'] : '';
			$label = is_string( $type['label'] ?? null ) ? $type['label'] : $key;
			if ( $key === '' ) {
				continue;
			}
			$available = ( $type['admin_available'] ?? false ) === true;
			echo '<option value="' . esc_attr( $key ) . '"' . ( $available ? '' : ' disabled' ) . '>' . esc_html( $label . ( $available ? '' : ' — unavailable in V1' ) ) . '</option>';
		}
		echo '</select> <button type="button" class="button" id="wpessential-fields-add">' . esc_html__( 'Add field', 'wpessential' ) . '</button></div></div>';
		echo '<div id="wpessential-fields-rows" class="wpessential-fields-rows" aria-live="polite"></div>';
		echo '<p class="description">' . esc_html__( 'Provider-owned and complex/container field types are shown by the canonical catalog but remain read-only until their admin owner contracts are certified.', 'wpessential' ) . '</p>';

		echo '<p><label for="wpessential-fields-status"><strong>' . esc_html__( 'Lifecycle status', 'wpessential' ) . '</strong></label><br><select id="wpessential-fields-status">';
		foreach ( [
			'draft'     => __( 'Draft', 'wpessential' ),
			'published' => __( 'Published', 'wpessential' ),
			'disabled'  => __( 'Disabled', 'wpessential' ),
			'archived'  => __( 'Archived', 'wpessential' ),
		] as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';

		echo '<div id="wpessential-fields-validation" role="status" aria-live="polite" hidden><p data-wpessential-fields-validation-summary></p><ul data-wpessential-fields-validation-issues></ul></div>';
		echo '<p class="submit"><button type="button" class="button" id="wpessential-fields-validate">' . esc_html__( 'Validate', 'wpessential' ) . '</button> ';
		echo '<button type="submit" class="button button-primary" id="wpessential-fields-save">' . esc_html__( 'Save field group', 'wpessential' ) . '</button> ';
		echo '<button type="button" class="button" id="wpessential-fields-cancel" hidden>' . esc_html__( 'Cancel edit', 'wpessential' ) . '</button></p>';
		echo '</form></section>';
	}

	/**
	 * Attached-to summary, saved-values count and dependents count per group id.
	 *
	 * @param list<array<string,mixed>> $definitions
	 * @return array<string,array{attached:string,values:int,dependencies:int}>
	 */
	private function listMeta( array $definitions ): array {
		static $cache = null;
		if ( is_array( $cache ) ) {
			return $cache;
		}
		$counts = [];
		try {
			$result = $this->abilities->execute( FieldValuesBrowser::ABILITY, [ 'action' => 'counts' ], $this->uiContext() );
			$counts = is_array( $result ) ? $result : [];
		} catch ( \Throwable ) {
			$counts = [];
		}
		$choices = self::locationChoices();
		$cache   = [];
		foreach ( $definitions as $definition ) {
			$id = is_string( $definition['id'] ?? null ) ? $definition['id'] : '';
			try {
				$dependents = $this->definitions?->dependentsOf( $id ) ?? [];
			} catch ( \Throwable ) {
				$dependents = [];
			}
			$cache[ $id ] = [
				'attached'     => self::attachedTo( is_array( $definition['payload'] ?? null ) ? $definition['payload'] : [], $choices ),
				'values'       => is_int( $counts[ $id ] ?? null ) ? $counts[ $id ] : 0,
				'dependencies' => count( $dependents ),
			];
		}

		return $cache;
	}

	/**
	 * "Pages (2 posts) or Categories" style summary of where a group shows.
	 *
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $choices
	 */
	public static function attachedTo( array $payload, array $choices ): string {
		$objects = [
			'entity_id' => 'posts',
			'term'      => 'terms',
			'user'      => 'users',
		];
		$groups  = [];
		foreach ( (array) ( $payload['locations'] ?? [] ) as $group ) {
			$parts = [];
			foreach ( (array) $group as $rule ) {
				$source = (string) ( $rule['source'] ?? '' );
				$values = array_map( 'strval', is_array( $rule['value'] ?? null ) ? $rule['value'] : [ $rule['value'] ?? '' ] );
				$not    = ( ( $rule['negate'] ?? false ) === true ) !== in_array( $rule['operator'] ?? '', [ 'not_equals', 'not_in' ], true );
				if ( isset( $objects[ $source ] ) ) {
					$parts[] = sprintf( '(%s%d %s)', $not ? 'not ' : '', count( $values ), $objects[ $source ] );
					continue;
				}
				$labels  = array_column( is_array( $choices[ $source ] ?? null ) ? $choices[ $source ] : [], 1, 0 );
				$parts[] = ( $not ? 'not ' : '' ) . implode( ', ', array_map( static fn ( string $value ): string => (string) ( $labels[ $value ] ?? $value ), $values ) );
			}
			if ( $parts !== [] ) {
				$groups[] = implode( ' ', $parts );
			}
		}

		return $groups === [] ? '—' : implode( ' or ', $groups );
	}

	/**
	 * @param list<array<string,mixed>> $definitions
	 * @param array<string,array{attached:string,values:int,dependencies:int}> $meta
	 */
	private function renderDefinitions( array $definitions, array $meta = [] ): void {
		echo '<section class="wpessential-fields-panel" aria-labelledby="wpessential-fields-list-title">';
		echo '<div class="wpessential-fields-list-heading"><h2 id="wpessential-fields-list-title">' . esc_html__( 'Saved field groups', 'wpessential' ) . '</h2>';
		echo '<button type="button" class="button" id="wpessential-fields-refresh">' . esc_html__( 'Refresh', 'wpessential' ) . '</button></div>';
		echo '<table class="widefat striped" aria-label="' . esc_attr__( 'Saved Field Groups', 'wpessential' ) . '"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Title', 'wpessential' ) . '</th><th scope="col">' . esc_html__( 'Key', 'wpessential' ) . '</th><th scope="col">' . esc_html__( 'Attached to', 'wpessential' ) . '</th><th scope="col">' . esc_html__( 'Fields', 'wpessential' ) . '</th><th scope="col">' . esc_html__( 'Saved values', 'wpessential' ) . '</th><th scope="col">' . esc_html__( 'Dependencies', 'wpessential' ) . '</th><th scope="col">' . esc_html__( 'Status', 'wpessential' ) . '</th><th scope="col">' . esc_html__( 'Revision', 'wpessential' ) . '</th><th scope="col">' . esc_html__( 'Actions', 'wpessential' ) . '</th></tr></thead>';
		echo '<tbody id="wpessential-fields-definitions">';
		foreach ( $definitions as $definition ) {
			$this->renderDefinitionRow( $definition, $meta[ is_string( $definition['id'] ?? null ) ? $definition['id'] : '' ] ?? [] );
		}
		if ( $definitions === [] ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No Field Groups have been created yet.', 'wpessential' ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}

	/**
	 * @param array<string,mixed> $definition
	 * @param array{attached?:string,values?:int,dependencies?:int} $meta
	 */
	private function renderDefinitionRow( array $definition, array $meta = [] ): void {
		$payload  = is_array( $definition['payload'] ?? null ) ? $definition['payload'] : [];
		$id       = is_string( $definition['id'] ?? null ) ? $definition['id'] : '';
		$title    = is_string( $payload['title'] ?? null ) ? $payload['title'] : '';
		$key      = is_string( $payload['group_key'] ?? null ) ? $payload['group_key'] : '';
		$fields   = is_array( $payload['fields'] ?? null ) ? count( $payload['fields'] ) : 0;
		$status   = is_string( $definition['status'] ?? null ) ? $definition['status'] : '';
		$revision = is_int( $definition['revision'] ?? null ) ? $definition['revision'] : 0;

		echo '<tr data-wpessential-fields-definition="' . esc_attr( $id ) . '">';
		$values = (int) ( $meta['values'] ?? 0 );
		echo '<td><strong>' . esc_html( $title ) . '</strong></td><td><code>' . esc_html( $key ) . '</code></td><td>' . esc_html( (string) ( $meta['attached'] ?? '' ) ) . '</td><td>' . esc_html( (string) $fields ) . '</td>';
		echo '<td><button type="button" class="button-link" data-wpessential-fields-values="' . esc_attr( $id ) . '" aria-label="' . esc_attr( sprintf( /* translators: 1: count, 2: group title. */ __( '%1$d saved values of %2$s', 'wpessential' ), $values, $title ) ) . '">' . esc_html( (string) $values ) . '</button></td>';
		echo '<td>' . esc_html( (string) (int) ( $meta['dependencies'] ?? 0 ) ) . '</td>';
		echo '<td>' . \WPEssential\Modules\CustomPostTypes\CustomPostTypeAdminController::statusPill( $status ) . '</td><td>' . esc_html( (string) $revision ) . '</td><td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StatusPill escapes.
		echo '<button type="button" class="button button-small" data-wpessential-fields-edit="' . esc_attr( $id ) . '">' . esc_html__( 'Edit', 'wpessential' ) . '</button> ';
		if ( $status === 'published' ) {
			echo '<button type="button" class="button button-small" data-wpessential-fields-status="disabled" data-wpessential-fields-id="' . esc_attr( $id ) . '">' . esc_html__( 'Disable', 'wpessential' ) . '</button> ';
		} else {
			echo '<button type="button" class="button button-small" data-wpessential-fields-status="published" data-wpessential-fields-id="' . esc_attr( $id ) . '">' . esc_html__( 'Publish', 'wpessential' ) . '</button> ';
		}
		if ( $status !== 'archived' ) {
			echo '<button type="button" class="button button-small" data-wpessential-fields-status="archived" data-wpessential-fields-id="' . esc_attr( $id ) . '">' . esc_html__( 'Archive', 'wpessential' ) . '</button>';
		}
		echo '</td></tr>';
	}

	private function renderTextField( string $id, string $label, string $placeholder, bool $required, string $help = '' ): void {
		echo '<p><label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
		echo '<input class="regular-text" type="text" id="' . esc_attr( $id ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( $required ? ' required' : '' ) . '>';
		if ( $help !== '' ) {
			echo '<span class="description">' . esc_html( $help ) . '</span>';
		}
		echo '</p>';
	}

	/**
	 * Choices per location source as [value, label] pairs, plus the REST details for post search.
	 *
	 * @return array<string,mixed>
	 */
	private static function locationChoices(): array {
		if ( ! function_exists( 'get_post_types' ) ) {
			return [];
		}
		$pairs = static fn ( array $objects ): array => array_values(
			array_map( static fn ( object $object ): array => [ (string) $object->name, (string) $object->label ], $objects )
		);
		$roles = [];
		foreach ( function_exists( 'wp_roles' ) ? wp_roles()->get_names() : [] as $key => $name ) {
			$roles[] = [ (string) $key, translate_user_role( (string) $name ) ];
		}

		return [
			'post_type'   => $pairs( get_post_types( [ 'show_ui' => true ], 'objects' ) ),
			'post_status' => $pairs( get_post_stati( [ 'show_in_admin_status_list' => true ], 'objects' ) ),
			'taxonomy'    => $pairs( get_taxonomies( [ 'show_ui' => true ], 'objects' ) ),
			'user_role'   => $roles,
			'search'      => [
				'url'   => esc_url_raw( rest_url( 'wp/v2/search' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			],
		];
	}

	/** @return array{list<array<string,mixed>>,array<string,mixed>,?string} */
	private function readSnapshot(): array {
		try {
			$context       = $this->uiContext();
			$groupResult   = $this->abilities->execute( 'wpessential/fields/list-groups', [], $context );
			$catalogResult = $this->abilities->execute( 'wpessential/fields/catalog', [], $context );
			if ( ! is_array( $groupResult ) || ! is_array( $groupResult['definitions'] ?? null ) || ! is_array( $catalogResult ) ) {
				return [ [], $this->catalogProjector->project( [] ), __( 'Custom Fields returned an invalid admin snapshot.', 'wpessential' ) ];
			}

			$definitions = [];
			foreach ( $groupResult['definitions'] as $definition ) {
				if ( ! is_array( $definition ) ) {
					return [ [], $this->catalogProjector->project( $catalogResult ), __( 'Field Group list returned an invalid record.', 'wpessential' ) ];
				}
				$definitions[] = $definition;
			}

			return [ $definitions, $this->catalogProjector->project( $catalogResult ), null ];
		} catch ( Throwable ) {
			return [ [], $this->catalogProjector->project( [] ), __( 'Custom Fields admin data could not be loaded.', 'wpessential' ) ];
		}
	}

	private function uiContext(): ExecutionContext {
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
