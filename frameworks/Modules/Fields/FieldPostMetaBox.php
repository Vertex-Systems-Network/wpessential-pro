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
		'select'     => 'select',
		'radio'      => 'radio',
		'group'      => 'group',
		'repeater'   => 'repeater',
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
		self::renderRows(
			(array) ( $group->payload['fields'] ?? [] ),
			'wpe_fields[' . $group->id . ']',
			fn ( string $uuid ): mixed => $this->read( $group->id, $uuid, $post->ID ),
		);
		echo '</tbody></table>';
		self::printConditionsScript();
	}

	/**
	 * Table rows for every field the edit screens can show; shared by posts, terms and users.
	 *
	 * @param list<array<string,mixed>> $fields
	 * @param callable(string):mixed $valueOf Stored value by field UUID.
	 */
	public static function renderRows( array $fields, string $namePrefix, callable $valueOf ): void {
		foreach ( $fields as $field ) {
			$type = (string) ( $field['type'] ?? '' );
			$uuid = (string) ( $field['uuid'] ?? '' );
			if ( ! isset( self::INPUTS[ $type ] ) || $uuid === '' ) {
				continue;
			}
			$id    = 'wpe-field-' . $uuid;
			$name  = $namePrefix . '[' . $uuid . ']';
			$value = $valueOf( $uuid );
			$conditions = (array) ( $field['settings']['conditional_logic'] ?? [] );
			echo '<tr data-wpe-key="' . esc_attr( (string) ( $field['key'] ?? '' ) ) . '"' . ( $conditions !== [] ? ' data-wpe-conditions="' . esc_attr( (string) wp_json_encode( $conditions ) ) . '"' : '' ) . '><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( (string) ( $field['label'] ?? $field['key'] ?? '' ) ) . '</label>' . self::keyBadge( (string) ( $field['key'] ?? '' ) ) . '</th><td>';
			if ( $type === 'textarea' ) {
				echo '<textarea class="large-text" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( is_scalar( $value ) ? (string) $value : '' ) . '</textarea>';
			} elseif ( $type === 'group' || $type === 'repeater' ) {
				self::renderContainer( $type, $field, $id, $name, $value );
			} elseif ( $type === 'select' || $type === 'radio' ) {
				self::renderChoices( $type, $field, $id, $name, $value );
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
	}

	/** The meta key a field stores under, with a copy button; hidden or shown per user from the screen. */
	public static function keyBadge( string $key ): string {
		if ( $key === '' ) {
			return '';
		}

		return '<span class="wpe-field-key" style="display:block;margin-top:4px;font-weight:normal"><code>' . esc_html( $key ) . '</code> '
			. '<button type="button" class="button-link wpe-field-key-copy" data-key="' . esc_attr( $key ) . '" title="' . esc_attr__( 'Copy key', 'wpessential' ) . '">'
			. '<span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Copy key', 'wpessential' ) . '</span></button></span>';
	}

	private static bool $scriptPrinted = false;

	/** Shows or hides rows as values change; hidden rows are disabled so they are not submitted. */
	public static function printConditionsScript(): void {
		if ( self::$scriptPrinted ) {
			return;
		}
		self::$scriptPrinted = true;
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- tiny inline behavior for this box only.
		echo "<script>(function(){function val(row){var out=[];row.querySelectorAll('input,select,textarea').forEach(function(el){if(el.type==='hidden'&&row.querySelector('input[type=checkbox][name=\"'+el.name+'\"]'))return;if((el.type==='checkbox'||el.type==='radio')&&!el.checked)return;if(el.multiple){Array.from(el.selectedOptions).forEach(function(o){out.push(o.value)});return}out.push(el.value)});return out}function ok(rule,list){var empty=list.filter(function(v){return v!==''&&v!=='0'}).length===0;switch(rule.operator){case '==':return list.indexOf(rule.value)>-1;case '!=':return list.indexOf(rule.value)===-1;case 'empty':return empty;case 'not_empty':return !empty;case 'contains':return rule.value!==''&&list.some(function(v){return v.indexOf(rule.value)>-1});case 'not_contains':return !(rule.value!==''&&list.some(function(v){return v.indexOf(rule.value)>-1}));case 'starts_with':return rule.value!==''&&list.some(function(v){return v.indexOf(rule.value)===0});case 'ends_with':return rule.value!==''&&list.some(function(v){return v.length>=rule.value.length&&v.slice(-rule.value.length)===rule.value});case '>':return list.some(function(v){return v!==''&&!isNaN(v)&&+v>+rule.value});case '>=':return list.some(function(v){return v!==''&&!isNaN(v)&&+v>=+rule.value});case '<':return list.some(function(v){return v!==''&&!isNaN(v)&&+v<+rule.value});case '<=':return list.some(function(v){return v!==''&&!isNaN(v)&&+v<=+rule.value});case 'in':var a=rule.value.split(',').map(function(x){return x.trim()});return list.some(function(v){return a.indexOf(v)>-1});case 'not_in':var b=rule.value.split(',').map(function(x){return x.trim()});return !list.some(function(v){return b.indexOf(v)>-1});}return false}function run(box){var rows={};box.querySelectorAll('tr[data-wpe-key]').forEach(function(r){rows[r.dataset.wpeKey]=r});box.querySelectorAll('tr[data-wpe-conditions]').forEach(function(r){var groups=JSON.parse(r.dataset.wpeConditions);var show=groups.some(function(g){return g.every(function(rule){var src=rows[rule.field];return ok(rule,src&&src.style.display!=='none'?val(src):[])})});r.style.display=show?'':'none';r.querySelectorAll('input,select,textarea').forEach(function(el){el.disabled=!show})})}document.querySelectorAll('.form-table').forEach(function(box){if(!box.querySelector('tr[data-wpe-conditions]'))return;run(box);box.addEventListener('change',function(){run(box)});box.addEventListener('input',function(){run(box)})})})();(function(){var L={hide:'" . esc_js( __( 'Hide field keys', 'wpessential' ) ) . "',show:'" . esc_js( __( 'Show field keys', 'wpessential' ) ) . "',copied:'" . esc_js( __( 'Copied', 'wpessential' ) ) . "'};function hidden(){try{return localStorage.getItem('wpeFieldKeys')==='hidden'}catch(e){return false}}function apply(){var h=hidden();document.querySelectorAll('.wpe-field-key').forEach(function(k){k.style.display=h?'none':'block'});document.querySelectorAll('.wpe-field-keys-toggle').forEach(function(b){b.textContent=h?L.show:L.hide;b.setAttribute('aria-pressed',h?'false':'true')})}document.querySelectorAll('.form-table').forEach(function(t){if(!t.querySelector('.wpe-field-key'))return;var b=document.createElement('button');b.type='button';b.className='button-link wpe-field-keys-toggle';b.addEventListener('click',function(){try{localStorage.setItem('wpeFieldKeys',hidden()?'shown':'hidden')}catch(e){}apply()});var p=document.createElement('p');p.style.textAlign='right';p.style.margin='0';p.appendChild(b);t.parentNode.insertBefore(p,t)});document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('.wpe-field-key-copy');if(!b)return;var k=b.getAttribute('data-key');var done=function(){var s=b.querySelector('.screen-reader-text');b.title=L.copied;if(s){s.textContent=L.copied}};if(navigator.clipboard){navigator.clipboard.writeText(k).then(done)}else{var i=document.createElement('textarea');i.value=k;document.body.appendChild(i);i.select();document.execCommand('copy');i.remove();done()}});apply()})();</script>";
	}

	/**
	 * A group shows its subfields once; a repeater shows every saved row plus one empty row to add the next.
	 *
	 * shortcut: rows are added one per save and removed by clearing them, upgrade to a JS add/remove/reorder UI next.
	 *
	 * @param array<string,mixed> $field
	 */
	private static function renderContainer( string $type, array $field, string $id, string $name, mixed $value ): void {
		$subfields = array_values( array_filter( (array) ( $field['subfields'] ?? [] ), static fn ( mixed $sub ): bool => is_array( $sub ) && isset( self::INPUTS[ (string) ( $sub['type'] ?? '' ) ] ) && ! in_array( $sub['type'], [ 'group', 'repeater' ], true ) ) );
		$rows      = $type === 'group' ? [ is_array( $value ) ? $value : [] ] : array_values( array_filter( is_array( $value ) ? $value : [], 'is_array' ) );
		if ( $type === 'repeater' ) {
			$rows[] = [];
		}
		echo '<div id="' . esc_attr( $id ) . '" class="wpessential-fields-container">';
		foreach ( $rows as $index => $row ) {
			$rowName = $type === 'group' ? $name : $name . '[' . $index . ']';
			echo '<fieldset class="wpessential-fields-container-row" style="border:1px solid #c3c4c7;padding:8px 12px;margin:0 0 8px">';
			if ( $type === 'repeater' ) {
				$legend = $index === count( $rows ) - 1 ? __( 'New row', 'wpessential' ) : sprintf( /* translators: %d: row number */ __( 'Row %d', 'wpessential' ), $index + 1 );
				echo '<legend>' . esc_html( $legend ) . '</legend>';
			}
			foreach ( $subfields as $sub ) {
				$subType = (string) $sub['type'];
				$subKey  = (string) ( $sub['key'] ?? '' );
				$subId   = $id . '-' . $index . '-' . $subKey;
				$subName = $rowName . '[' . $subKey . ']';
				$current = $row[ $subKey ] ?? null;
				echo '<p><label for="' . esc_attr( $subId ) . '">' . esc_html( (string) ( $sub['label'] ?? $subKey ) ) . '</label><br>';
				if ( $subType === 'textarea' ) {
					echo '<textarea class="large-text" rows="3" id="' . esc_attr( $subId ) . '" name="' . esc_attr( $subName ) . '">' . esc_textarea( is_scalar( $current ) ? (string) $current : '' ) . '</textarea>';
				} elseif ( self::INPUTS[ $subType ] === 'checkbox' ) {
					echo '<input type="hidden" name="' . esc_attr( $subName ) . '" value="0"><input type="checkbox" id="' . esc_attr( $subId ) . '" name="' . esc_attr( $subName ) . '" value="1"' . checked( (bool) $current, true, false ) . '>';
				} elseif ( $subType === 'select' || $subType === 'radio' ) {
					self::renderChoices( 'select', $sub, $subId, $subName, $current );
				} else {
					$shown = $subType === 'datetime' ? self::localDateTime( $current ) : ( is_scalar( $current ) ? (string) $current : '' );
					echo '<input class="regular-text" type="' . esc_attr( self::INPUTS[ $subType ] ) . '" id="' . esc_attr( $subId ) . '" name="' . esc_attr( $subName ) . '" value="' . esc_attr( $shown ) . '">';
				}
				echo '</p>';
			}
			echo '</fieldset>';
		}
		echo '</div>';
	}

	/** @param array<string,mixed> $field */
	private static function renderChoices( string $type, array $field, string $id, string $name, mixed $value ): void {
		$choices  = self::choices( $field );
		$multiple = $type === 'select' && ( $field['settings']['multiple'] ?? false ) === true;
		$selected = array_map( 'strval', is_array( $value ) ? $value : ( $value === null ? [] : [ $value ] ) );
		if ( $type === 'radio' ) {
			echo '<fieldset id="' . esc_attr( $id ) . '">';
			foreach ( $choices as $key => $label ) {
				echo '<label><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $key ) . '"' . checked( in_array( (string) $key, $selected, true ), true, false ) . '> ' . esc_html( $label ) . '</label><br>';
			}
			echo '</fieldset>';
			return;
		}
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name . ( $multiple ? '[]' : '' ) ) . '"' . ( $multiple ? ' multiple' : '' ) . '>';
		if ( ! $multiple ) {
			echo '<option value="">' . esc_html__( '— Select —', 'wpessential' ) . '</option>';
		}
		foreach ( $choices as $key => $label ) {
			echo '<option value="' . esc_attr( (string) $key ) . '"' . selected( in_array( (string) $key, $selected, true ), true, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Choice value => label; a list of choices uses each value as its own label.
	 *
	 * @param array<string,mixed> $field
	 * @return array<int|string,string>
	 */
	public static function choices( array $field ): array {
		$choices = $field['settings']['choices'] ?? [];
		if ( ! is_array( $choices ) ) {
			return [];
		}
		$out = [];
		foreach ( $choices as $key => $label ) {
			$value         = array_is_list( $choices ) ? $label : $key;
			$out[ is_scalar( $value ) ? (string) $value : '' ] = is_scalar( $label ) ? (string) $label : (string) $value;
		}
		unset( $out[''] );

		return $out;
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
			$byUuid = array_column( (array) ( $group->payload['fields'] ?? [] ), null, 'uuid' );
			$values = [];
			foreach ( $fields as $uuid => $raw ) {
				$type = (string) ( $byUuid[ $uuid ]['type'] ?? '' );
				if ( is_string( $uuid ) && isset( self::INPUTS[ $type ] ) ) {
					$values[ (string) ( $byUuid[ $uuid ]['key'] ?? '' ) ] = self::value( $type, $raw, $byUuid[ $uuid ] );
				}
			}
			foreach ( $fields as $uuid => $raw ) {
				$type = (string) ( $byUuid[ $uuid ]['type'] ?? '' );
				if ( ! is_string( $uuid ) || ! isset( self::INPUTS[ $type ] ) ) {
					continue;
				}
				// A field hidden by its conditions keeps its stored value, as in ACF.
				if ( ! FieldConditionalLogic::visible( (array) ( $byUuid[ $uuid ]['settings']['conditional_logic'] ?? [] ), $values ) ) {
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
							'value'                   => self::value( $type, $raw, $byUuid[ $uuid ] ),
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
	/** @param array<string,mixed> $field */
	public static function value( string $type, mixed $raw, array $field = [] ): mixed {
		if ( $type === 'group' || $type === 'repeater' ) {
			return self::containerValue( $type, $raw, $field );
		}
		if ( $type === 'select' || $type === 'radio' ) {
			return self::choiceValue( $field, $raw );
		}
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

	/**
	 * Group: one map of subfield values. Repeater: rows that have at least one value, so the
	 * blank "new row" and cleared rows are dropped. Empty subfields are left out of the row.
	 *
	 * @param array<string,mixed> $field
	 */
	private static function containerValue( string $type, mixed $raw, array $field ): mixed {
		$subfields = [];
		foreach ( (array) ( $field['subfields'] ?? [] ) as $sub ) {
			if ( is_array( $sub ) && is_string( $sub['key'] ?? null ) ) {
				$subfields[ $sub['key'] ] = $sub;
			}
		}
		$row = static function ( mixed $posted ) use ( $subfields ): array {
			$out = [];
			foreach ( is_array( $posted ) ? $posted : [] as $key => $one ) {
				$sub = $subfields[ $key ] ?? null;
				if ( $sub === null ) {
					continue;
				}
				$value = self::value( (string) ( $sub['type'] ?? 'text' ), $one, $sub );
				// An unticked box (false) alone does not make a row worth keeping.
				if ( $value !== null && $value !== [] ) {
					$out[ $key ] = $value;
				}
			}

			return $out;
		};
		if ( $type === 'group' ) {
			$group = $row( $raw );

			return $group === [] ? null : $group;
		}
		$rows = [];
		foreach ( is_array( $raw ) ? $raw : [] as $posted ) {
			$one = $row( $posted );
			if ( array_filter( $one, static fn ( mixed $v ): bool => $v !== false ) !== [] ) {
				$rows[] = $one;
			}
		}

		return $rows === [] ? null : $rows;
	}

	/**
	 * Posted strings mapped back to the declared choice values, so integer keys keep their type.
	 *
	 * @param array<string,mixed> $field
	 */
	private static function choiceValue( array $field, mixed $raw ): mixed {
		$declared = $field['settings']['choices'] ?? [];
		$declared = is_array( $declared ) ? ( array_is_list( $declared ) ? $declared : array_keys( $declared ) ) : [];
		$map      = [];
		foreach ( $declared as $choice ) {
			if ( is_scalar( $choice ) ) {
				$map[ (string) $choice ] = $choice;
			}
		}
		$pick = static fn ( mixed $one ): mixed => is_scalar( $one ) ? ( $map[ (string) $one ] ?? (string) $one ) : null;
		if ( ( $field['settings']['multiple'] ?? false ) === true ) {
			return array_values( array_filter( array_map( $pick, is_array( $raw ) ? $raw : [] ), static fn ( mixed $v ): bool => $v !== null && $v !== '' ) );
		}
		$one = $pick( is_scalar( $raw ) ? trim( (string) $raw ) : '' );

		return $one === '' ? null : $one;
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
