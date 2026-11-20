import { MODES, visibilityEditor } from './visibility';

export { setLocationChoices } from './visibility';
// Field Group settings and per-field settings (Penpot page 10 · Fields, boards F5 and 14b).
// Only keys that FieldGroupDefinitionNormalizer, FieldDefinitionNormalizer or FieldValueNormalizer read are offered.
type JsonObject = Record< string, unknown >;

const STORAGE_MODES = MODES;
const VISIBILITY = visibilityEditor();
const STRING_TYPES = [ 'text', 'textarea' ];
// FieldValueNormalizer trims these by default; other string types keep spaces unless trim is set.
const TRIM_BY_DEFAULT = [ 'text', 'email', 'url' ];

function el< K extends keyof HTMLElementTagNameMap >(
	tag: K,
	className?: string,
	text?: string
): HTMLElementTagNameMap[ K ] {
	const node = document.createElement( tag );
	if ( className ) {
		node.className = className;
	}
	if ( text !== undefined ) {
		node.textContent = text;
	}
	return node;
}

function selectBox(
	id: string,
	choices: [ string, string ][],
	value: string
): HTMLSelectElement {
	const box = el( 'select' );
	box.id = id;
	for ( const [ choice, text ] of choices ) {
		const item = el( 'option', undefined, text );
		item.value = choice;
		box.append( item );
	}
	box.value = value;
	return box;
}

function labelled( text: string, control: HTMLElement, help?: string ): HTMLLabelElement {
	const label = el( 'label', 'wpessential-fields-setting' );
	label.append( el( 'span', undefined, text ), control );
	if ( help ) {
		label.append( el( 'span', 'description', help ) );
	}
	return label;
}

function tickBox( id: string, text: string, checked: boolean ): HTMLLabelElement {
	const box = el( 'input' );
	box.type = 'checkbox';
	box.id = id;
	box.checked = checked;
	const label = el( 'label', 'wpessential-fields-setting wpessential-fields-setting--check' );
	label.append( box, document.createTextNode( ` ${ text }` ) );
	return label;
}

function value( id: string ): string {
	const node = document.getElementById( id );
	return node instanceof HTMLInputElement || node instanceof HTMLSelectElement ? node.value : '';
}

function ticked( id: string ): boolean {
	const node = document.getElementById( id );
	return node instanceof HTMLInputElement && node.checked;
}

function setValue( id: string, next: string ): void {
	const node = document.getElementById( id );
	if ( node instanceof HTMLInputElement || node instanceof HTMLSelectElement ) {
		node.value = next;
	}
}

function setTicked( id: string, next: boolean ): void {
	const node = document.getElementById( id );
	if ( node instanceof HTMLInputElement ) {
		node.checked = next;
	}
}

function isObject( candidate: unknown ): candidate is JsonObject {
	return typeof candidate === 'object' && candidate !== null && ! Array.isArray( candidate );
}

function text( object: JsonObject, key: string, fallback: string ): string {
	const raw = object[ key ];
	return typeof raw === 'string' ? raw : fallback;
}

export function mountGroupSettings( form: HTMLElement ): void {
	if ( document.getElementById( 'wpessential-fields-group-settings' ) ) {
		return;
	}
	const anchor = form.querySelector( '.wpessential-fields-list-heading' );
	const panel = el( 'fieldset', 'wpessential-fields-group-settings' );
	panel.id = 'wpessential-fields-group-settings';
	panel.append( el( 'legend', undefined, 'Group settings' ) );

	const presentation = el( 'div', 'wpessential-fields-settings-grid' );
	presentation.append(
		labelled(
			'Panel style',
			selectBox( 'wpessential-fields-panel-style', [
				[ 'standard', 'Standard box' ],
				[ 'seamless', 'Seamless (no box)' ],
				[ 'sectioned', 'Sectioned' ],
			], 'standard' )
		),
		labelled(
			'Label placement',
			selectBox( 'wpessential-fields-label-placement', [
				[ 'top', 'Above the input' ],
				[ 'left', 'Left of the input' ],
			], 'top' )
		),
		labelled(
			'Instruction placement',
			selectBox( 'wpessential-fields-instruction-placement', [
				[ 'below_label', 'Below the label' ],
				[ 'below_input', 'Below the input' ],
			], 'below_label' )
		),
		labelled(
			'Meta box for',
			selectBox( 'wpessential-fields-storage-mode', STORAGE_MODES, 'native_post_meta' ),
			'Posts save post meta, taxonomy terms save term meta, users save user meta.'
		),
		tickBox( 'wpessential-fields-collapsible', 'Editors can collapse this group', false ),
		tickBox( 'wpessential-fields-collapsed', 'Start collapsed', false ),
		tickBox( 'wpessential-fields-revisions', 'Keep values in post revisions', false )
	);

	panel.append( presentation, el( 'h4', undefined, 'Visibility' ), VISIBILITY.element );
	if ( anchor ) {
		anchor.before( panel );
	} else {
		form.prepend( panel );
	}

	document.getElementById( 'wpessential-fields-storage-mode' )?.addEventListener( 'change', () => VISIBILITY.set( value( 'wpessential-fields-storage-mode' ), [] ) );
	VISIBILITY.set( 'native_post_meta', [] );

	const collapsible = document.getElementById( 'wpessential-fields-collapsible' );
	collapsible?.addEventListener( 'change', () => {
		const collapsed = document.getElementById( 'wpessential-fields-collapsed' );
		if ( collapsed instanceof HTMLInputElement ) {
			collapsed.disabled = ! ticked( 'wpessential-fields-collapsible' );
			if ( collapsed.disabled ) {
				collapsed.checked = false;
			}
		}
	} );
}

/** Fills the group settings panel from a saved payload (or the defaults for a new group). */
export function setGroupSettings( payload: JsonObject ): void {
	const presentation = isObject( payload.presentation ) ? payload.presentation : {};
	setValue( 'wpessential-fields-panel-style', text( presentation, 'panel_style', 'standard' ) );
	setValue( 'wpessential-fields-label-placement', text( presentation, 'label_placement', 'top' ) );
	setValue( 'wpessential-fields-instruction-placement', text( presentation, 'instruction_placement', 'below_label' ) );
	setTicked( 'wpessential-fields-collapsible', presentation.collapsible === true );
	setTicked( 'wpessential-fields-collapsed', presentation.collapsed === true );
	const collapsed = document.getElementById( 'wpessential-fields-collapsed' );
	if ( collapsed instanceof HTMLInputElement ) {
		collapsed.disabled = presentation.collapsible !== true;
	}
	const storage = isObject( payload.storage ) ? payload.storage : {};
	const mode = text( storage, 'mode', 'native_post_meta' );
	const storageBox = document.getElementById( 'wpessential-fields-storage-mode' );
	if ( storageBox instanceof HTMLSelectElement ) {
		storageBox.querySelector( '[data-wpessential-fields-stored-mode]' )?.remove();
		// Keep a stored mode visible so editing never changes it silently.
		if ( ! STORAGE_MODES.some( ( [ choice ] ) => choice === mode ) ) {
			const stored = el( 'option', undefined, `${ mode } (not bound at runtime yet)` );
			stored.value = mode;
			stored.dataset.wpessentialFieldsStoredMode = '';
			storageBox.append( stored );
		}
		storageBox.value = mode;
	}
	setTicked( 'wpessential-fields-revisions', payload.revision_policy === 'enabled' );
	VISIBILITY.set( mode, payload.locations );
}

/** The group settings as payload keys. Throws with a message the editor shows when a value cannot work. */
export function collectGroupSettings(): JsonObject {
	const invalid = document.querySelector( '.wpessential-fields-row-settings [aria-invalid="true"]' );
	if ( invalid instanceof HTMLInputElement ) {
		throw new Error( `${ invalid.dataset.wpessentialFieldsLabel ?? 'A field setting' } must be a whole number.` );
	}
	const locations = VISIBILITY.collect( value( 'wpessential-fields-storage-mode' ) || 'native_post_meta' );
	if ( locations.length === 0 && value( 'wpessential-fields-status' ) === 'published' ) {
		throw new Error( 'Choose where to enable the group (Visibility) before publishing, so the fields have somewhere to appear.' );
	}
	return {
		presentation: {
			panel_style: value( 'wpessential-fields-panel-style' ) || 'standard',
			label_placement: value( 'wpessential-fields-label-placement' ) || 'top',
			instruction_placement: value( 'wpessential-fields-instruction-placement' ) || 'below_label',
			collapsible: ticked( 'wpessential-fields-collapsible' ),
			collapsed: ticked( 'wpessential-fields-collapsible' ) && ticked( 'wpessential-fields-collapsed' ),
		},
		storage: { mode: value( 'wpessential-fields-storage-mode' ) || 'unconfigured' },
		revision_policy: ticked( 'wpessential-fields-revisions' ) ? 'enabled' : 'disabled',
		locations,
	};
}

function settingsOf( field: JsonObject ): JsonObject {
	if ( ! isObject( field.settings ) ) {
		field.settings = {};
	}
	return field.settings as JsonObject;
}

/** Parses a number input; null means the text is not a valid number of that kind. */
function optionalNumber( raw: string, integer: boolean ): number | undefined | null {
	const trimmed = raw.trim();
	if ( trimmed === '' ) {
		return undefined;
	}
	if ( integer ? ! /^\d+$/.test( trimmed ) : ! Number.isFinite( Number( trimmed ) ) ) {
		return null;
	}
	return integer ? Number.parseInt( trimmed, 10 ) : Number( trimmed );
}

const OPERATORS: Array< [ string, string ] > = [
	[ '==', 'is equal to' ],
	[ '!=', 'is not equal to' ],
	[ 'empty', 'is empty' ],
	[ 'not_empty', 'is not empty' ],
	[ 'contains', 'contains' ],
	[ 'not_contains', 'does not contain' ],
	[ 'starts_with', 'starts with' ],
	[ 'ends_with', 'ends with' ],
	[ '>', 'is greater than' ],
	[ '>=', 'is at least' ],
	[ '<', 'is less than' ],
	[ '<=', 'is at most' ],
	[ 'in', 'is one of (comma-separated)' ],
	[ 'not_in', 'is none of (comma-separated)' ],
];

/**
 * "Show this field only if": OR groups of AND rules, written into settings.conditional_logic.
 * Rules name another field by its storage key.
 */
function conditionsEditor( settings: JsonObject, baseId: string ): HTMLFieldSetElement {
	const box = el( 'fieldset', 'wpessential-fields-conditions' );
	box.append( el( 'legend', undefined, 'Show this field only if' ) );
	const list = el( 'div' );
	box.append( list );
	const groups: JsonObject[][] = Array.isArray( settings.conditional_logic )
		? ( settings.conditional_logic as JsonObject[][] )
		: [];
	const save = (): void => {
		// Rules still missing a field key are kept on screen but not saved.
		const kept = groups
			.map( ( group ) => group.filter( ( rule ) => typeof rule.field === 'string' && rule.field !== '' ) )
			.filter( ( group ) => group.length > 0 );
		if ( kept.length === 0 ) {
			delete settings.conditional_logic;
		} else {
			settings.conditional_logic = kept;
		}
	};
	const draw = (): void => {
		list.replaceChildren();
		groups.forEach( ( group, g ) => {
			const groupBox = el( 'div', 'wpessential-fields-condition-group' );
			if ( g > 0 ) {
				groupBox.append( el( 'p', undefined, 'or' ) );
			}
			group.forEach( ( rule, r ) => {
				const row = el( 'div', 'wpessential-fields-condition' );
				const key = el( 'input' );
				key.type = 'text';
				key.id = `${ baseId }-${ g }-${ r }-field`;
				key.placeholder = 'other_field_key';
				key.value = typeof rule.field === 'string' ? rule.field : '';
				key.addEventListener( 'input', () => {
					rule.field = key.value.trim();
					save();
				} );
				const operator = selectBox( `${ baseId }-${ g }-${ r }-operator`, OPERATORS, typeof rule.operator === 'string' ? rule.operator : '==' );
				const value = el( 'input' );
				value.type = 'text';
				value.id = `${ baseId }-${ g }-${ r }-value`;
				value.value = typeof rule.value === 'string' ? rule.value : '';
				value.hidden = operator.value === 'empty' || operator.value === 'not_empty';
				operator.addEventListener( 'change', () => {
					rule.operator = operator.value;
					value.hidden = operator.value === 'empty' || operator.value === 'not_empty';
					save();
				} );
				value.addEventListener( 'input', () => {
					rule.value = value.value;
					save();
				} );
				const remove = el( 'button', 'button-link-delete', 'Remove rule' );
				remove.type = 'button';
				remove.addEventListener( 'click', () => {
					group.splice( r, 1 );
					save();
					draw();
				} );
				row.append( labelled( 'Field key', key ), labelled( 'Condition', operator ), labelled( 'Value', value ), remove );
				groupBox.append( row );
			} );
			const and = el( 'button', 'button', 'And' );
			and.type = 'button';
			and.addEventListener( 'click', () => {
				group.push( { field: '', operator: '==', value: '' } );
				draw();
			} );
			groupBox.append( and );
			list.append( groupBox );
		} );
	};
	const or = el( 'button', 'button', groups.length === 0 ? 'Add condition' : 'Or' );
	or.type = 'button';
	or.addEventListener( 'click', () => {
		groups.push( [ { field: '', operator: '==', value: '' } ] );
		or.textContent = 'Or';
		draw();
	} );
	box.append( or );
	draw();
	return box;
}

/** Subfield types the edit-screen box renders inside a group or repeater row. */
export const SUBFIELD_TYPES: Array< [ string, string ] > = [
	[ 'text', 'Text' ],
	[ 'textarea', 'Text area' ],
	[ 'number', 'Number' ],
	[ 'email', 'Email' ],
	[ 'url', 'URL' ],
	[ 'true_false', 'True / False' ],
	[ 'date', 'Date' ],
	[ 'color', 'Color' ],
	[ 'image', 'Image (attachment ID)' ],
];

/** Label, key and type for each subfield of a group or repeater; edits write into field.subfields. */
function subfieldsEditor( field: JsonObject, baseId: string ): HTMLFieldSetElement {
	const box = el( 'fieldset', 'wpessential-fields-subfields' );
	box.append( el( 'legend', undefined, 'Subfields' ) );
	const list = el( 'div' );
	box.append( list );
	if ( ! Array.isArray( field.subfields ) ) {
		field.subfields = [];
	}
	const subfields = field.subfields as JsonObject[];
	const draw = (): void => {
		list.replaceChildren();
		subfields.forEach( ( sub, at ) => {
			const row = el( 'div', 'wpessential-fields-subfield' );
			const input = ( key: 'label' | 'key', text: string ): HTMLLabelElement => {
				const control = el( 'input' );
				control.type = 'text';
				control.id = `${ baseId }-${ at }-${ key }`;
				control.value = typeof sub[ key ] === 'string' ? ( sub[ key ] as string ) : '';
				control.readOnly = key === 'key' && typeof sub.uuid === 'string' && sub.uuid !== '';
				control.addEventListener( 'input', () => {
					sub[ key ] = control.value;
				} );
				return labelled( text, control );
			};
			const current = typeof sub.type === 'string' ? sub.type : 'text';
			const kind = selectBox( `${ baseId }-${ at }-type`, SUBFIELD_TYPES, current );
			kind.disabled = typeof sub.uuid === 'string' && sub.uuid !== '';
			kind.addEventListener( 'change', () => {
				sub.type = kind.value;
			} );
			const remove = el( 'button', 'button-link-delete', 'Remove subfield' );
			remove.type = 'button';
			remove.addEventListener( 'click', () => {
				subfields.splice( at, 1 );
				draw();
			} );
			row.append( input( 'label', 'Label' ), input( 'key', 'Key' ), labelled( 'Type', kind ), remove );
			list.append( row );
		} );
	};
	const add = el( 'button', 'button', 'Add subfield' );
	add.type = 'button';
	add.addEventListener( 'click', () => {
		subfields.push( { key: '', label: '', type: 'text' } );
		draw();
	} );
	box.append( add );
	draw();
	return box;
}

/** Choices as editable text: a list prints one value per line, a map prints "value : Label". */
export function choicesToText( choices: unknown ): string {
	if ( Array.isArray( choices ) ) {
		return choices.map( String ).join( '\n' );
	}
	if ( isObject( choices ) ) {
		return Object.entries( choices )
			.map( ( [ key, label ] ) => ( key === String( label ) ? key : `${ key } : ${ String( label ) }` ) )
			.join( '\n' );
	}
	return '';
}

/** Lines without " : " keep the value as the label; undefined clears the setting. */
export function textToChoices( raw: string ): string[] | Record< string, string > | undefined {
	const lines = raw.split( '\n' ).map( ( line ) => line.trim() ).filter( ( line ) => line !== '' );
	if ( lines.length === 0 ) {
		return undefined;
	}
	if ( ! lines.some( ( line ) => line.includes( ' : ' ) ) ) {
		return lines;
	}
	const map: Record< string, string > = {};
	for ( const line of lines ) {
		const at = line.indexOf( ' : ' );
		const key = at === -1 ? line : line.slice( 0, at ).trim();
		map[ key ] = at === -1 ? line : line.slice( at + 3 ).trim();
	}
	return map;
}

/**
 * Settings for one field: required, REST, the type's value rules and cloning.
 * Changes are written straight into the field object the editor saves.
 */
export function fieldSettingsPanel( field: JsonObject, index: number ): HTMLDetailsElement {
	const type = typeof field.type === 'string' ? field.type : '';
	const settings = settingsOf( field );
	const repeat = isObject( field.repeatability ) ? field.repeatability : {};
	const id = ( key: string ): string => `wpessential-fields-${ index }-${ key }`;
	const box = el( 'details', 'wpessential-fields-row-settings' );
	box.append( el( 'summary', undefined, 'Field settings' ) );
	const grid = el( 'div', 'wpessential-fields-settings-grid' );
	box.append( grid );

	const bindTick = ( key: string, label: string, checked: boolean, write: ( on: boolean ) => void ): void => {
		const control = tickBox( id( key ), label, checked );
		control.querySelector( 'input' )?.addEventListener( 'change', ( event ) => {
			write( ( event.target as HTMLInputElement ).checked );
		} );
		grid.append( control );
	};
	const bindNumber = (
		key: string,
		label: string,
		current: unknown,
		integer: boolean,
		write: ( next: number | undefined ) => void
	): void => {
		const input = el( 'input' );
		input.type = 'number';
		input.id = id( key );
		input.step = integer ? '1' : 'any';
		input.value = typeof current === 'number' ? String( current ) : '';
		input.dataset.wpessentialFieldsLabel = label;
		input.addEventListener( 'input', () => {
			// Unparseable text such as "1e" reads as an empty value, so badInput is checked too.
			const parsed = input.validity.badInput ? null : optionalNumber( input.value, integer );
			// A bad value is flagged and blocks the save; the last good value is never replaced by a guess.
			input.setAttribute( 'aria-invalid', parsed === null ? 'true' : 'false' );
			if ( parsed !== null ) {
				write( parsed );
			}
		} );
		grid.append( labelled( label, input ) );
	};
	const setting = ( key: string ) => ( next: unknown ): void => {
		if ( next === undefined || next === false ) {
			delete settings[ key ];
		} else {
			settings[ key ] = next;
		}
	};

	bindTick( 'required', 'Required', settings.required === true, setting( 'required' ) );
	bindTick( 'rest', 'Show in the REST API', field.show_in_rest === true, ( on ) => {
		field.show_in_rest = on;
	} );

	if ( STRING_TYPES.includes( type ) ) {
		const trimDefault = TRIM_BY_DEFAULT.includes( type );
		bindTick( 'trim', 'Trim spaces from the value', typeof settings.trim === 'boolean' ? settings.trim : trimDefault, ( on ) => {
			settings.trim = on;
		} );
		bindNumber( 'min_length', 'Minimum length', settings.min_length, true, setting( 'min_length' ) );
		bindNumber( 'max_length', 'Maximum length', settings.max_length ?? settings.maxlength, true, ( next ) => {
			delete settings.maxlength;
			setting( 'max_length' )( next );
		} );
	}
	grid.append( conditionsEditor( settings, id( 'conditions' ) ) );
	if ( type === 'group' || type === 'repeater' ) {
		grid.append( subfieldsEditor( field, id( 'subfields' ) ) );
		if ( type === 'repeater' ) {
			bindNumber( 'min_rows', 'Minimum rows', settings.min_rows, true, setting( 'min_rows' ) );
			bindNumber( 'max_rows', 'Maximum rows', settings.max_rows, true, setting( 'max_rows' ) );
		}
	}
	if ( type === 'select' || type === 'radio' ) {
		const choices = el( 'textarea' );
		choices.id = id( 'choices' );
		choices.rows = 4;
		choices.value = choicesToText( settings.choices );
		choices.addEventListener( 'input', () => {
			setting( 'choices' )( textToChoices( choices.value ) );
		} );
		grid.append( labelled( 'Choices', choices, 'One per line. Use "value : Label" to store a value that differs from its label.' ) );
		if ( type === 'select' ) {
			bindTick( 'multiple', 'Allow several choices', settings.multiple === true, setting( 'multiple' ) );
		}
	}
	if ( type === 'number' || type === 'range' ) {
		bindTick( 'integer', 'Whole numbers only', settings.integer === true, setting( 'integer' ) );
		bindNumber( 'min', 'Minimum value', settings.min, false, setting( 'min' ) );
		bindNumber( 'max', 'Maximum value', settings.max, false, setting( 'max' ) );
	}
	if ( type === 'date' ) {
		const precision = selectBox( id( 'precision' ), [
			[ 'day', 'Day (YYYY-MM-DD)' ],
			[ 'week', 'Week (YYYY-Www)' ],
			[ 'month', 'Month (YYYY-MM)' ],
		], text( settings, 'precision', text( settings, 'control_mode', 'day' ) ) );
		precision.addEventListener( 'change', () => {
			delete settings.control_mode;
			setting( 'precision' )( precision.value === 'day' ? undefined : precision.value );
		} );
		grid.append( labelled( 'Date precision', precision ) );
	}

	// Group and repeater manage their own rows; the normalizer rejects common cloning on them.
	if ( type === 'group' || type === 'repeater' ) {
		return box;
	}

	// Cloning: the normalizer reads these top-level keys and folds them into `repeatability`.
	const clone =( key: string, fallback: unknown ): unknown => ( key in field ? field[ key ] : fallback );
	const cloning = clone( 'cloneable', repeat.enabled ) === true;
	// Reordering needs cloning, so the sortable box follows the cloneable box.
	const syncSortable = ( on: boolean ): void => {
		const sortable = grid.querySelector< HTMLInputElement >( `#${ id( 'sortable' ) }` );
		if ( sortable ) {
			sortable.disabled = ! on;
			if ( ! on ) {
				sortable.checked = false;
			}
		}
	};
	bindTick( 'cloneable', 'Allow several values (clone)', cloning, ( on ) => {
		field.cloneable = on;
		if ( ! on ) {
			field.sortable = false;
		}
		syncSortable( on );
	} );
	bindTick( 'sortable', 'Editors can reorder clones', cloning && clone( 'sortable', repeat.sortable ) === true, ( on ) => {
		field.sortable = on;
	} );
	syncSortable( cloning );
	bindNumber( 'min_clones', 'Minimum clones', clone( 'min_clones', repeat.min ), true, ( next ) => {
		field.min_clones = next ?? 0;
	} );
	bindNumber( 'max_clones', 'Maximum clones', clone( 'max_clones', repeat.max ), true, ( next ) => {
		field.max_clones = next ?? null;
	} );
	bindTick( 'clone_default', 'New clones start with the default value', clone( 'clone_default', repeat.clone_default ) === true, ( on ) => {
		field.clone_default = on;
	} );
	bindTick( 'clone_as_multiple', 'Store each clone as its own meta row', clone( 'clone_as_multiple', repeat.store_as_multiple ) === true, ( on ) => {
		field.clone_as_multiple = on;
	} );
	bindTick( 'clone_empty_start', 'Start with no clones', clone( 'clone_empty_start', repeat.empty_start ) === true, ( on ) => {
		field.clone_empty_start = on;
	} );
	const addLabel = el( 'input' );
	addLabel.type = 'text';
	addLabel.id = id( 'add_button_label' );
	const currentAdd = clone( 'add_button_label', repeat.add_button_label );
	addLabel.value = typeof currentAdd === 'string' ? currentAdd : '';
	addLabel.placeholder = '+ Add more';
	addLabel.addEventListener( 'input', () => {
		field.add_button_label = addLabel.value.trim() === '' ? null : addLabel.value.trim();
	} );
	grid.append( labelled( 'Add button label', addLabel ) );

	return box;
}
