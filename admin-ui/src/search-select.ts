// Select with a search box inside it (like select2): type to filter the list, pick one or many
// (chips with ×), results can come from REST as you type. Keyboard: ↑/↓ move, Enter picks,
// Escape closes, Backspace in the empty box removes the last chip. A hidden native <select>
// holds the values so forms and existing readers keep working.

export type Choice = [ value: string, label: string ];

export interface SearchSelectOptions {
	label: string;
	multiple: boolean;
	choices: Choice[];
	selected: string[];
	placeholder?: string;
	/** Remote search: called with the typed text (2+ characters). */
	search?: ( term: string ) => Promise< Choice[] >;
	/** Labels for saved values that are not in `choices` (e.g. post IDs). */
	resolve?: ( values: string[] ) => Promise< Choice[] >;
	onChange?: ( values: string[] ) => void;
}

export interface SearchSelect {
	element: HTMLElement;
	select: HTMLSelectElement;
	values(): string[];
}

let uid = 0;

export function searchSelect( options: SearchSelectOptions ): SearchSelect {
	const id = `wpe-ss-${ ++uid }`;
	const known = new Map< string, string >( options.choices );
	const root = document.createElement( 'div' );
	root.className = 'wpe-ss';
	const store = document.createElement( 'select' );
	store.multiple = options.multiple;
	store.hidden = true;
	store.tabIndex = -1;
	store.setAttribute( 'aria-hidden', 'true' );
	const control = document.createElement( 'div' );
	control.className = 'wpe-ss__control';
	const chips = document.createElement( 'span' );
	chips.className = 'wpe-ss__chips';
	const input = document.createElement( 'input' );
	input.type = 'text';
	input.className = 'wpe-ss__input';
	input.id = `${ id }-input`;
	input.autocomplete = 'off';
	input.setAttribute( 'role', 'combobox' );
	input.setAttribute( 'aria-label', options.label );
	input.setAttribute( 'aria-expanded', 'false' );
	input.setAttribute( 'aria-controls', `${ id }-list` );
	input.setAttribute( 'aria-autocomplete', 'list' );
	const list = document.createElement( 'ul' );
	list.className = 'wpe-ss__list';
	list.id = `${ id }-list`;
	list.setAttribute( 'role', 'listbox' );
	list.setAttribute( 'aria-label', options.label );
	if ( options.multiple ) {
		list.setAttribute( 'aria-multiselectable', 'true' );
	}
	list.hidden = true;
	const status = document.createElement( 'span' );
	status.className = 'screen-reader-text';
	status.setAttribute( 'role', 'status' );
	control.append( chips, input );
	root.append( control, list, status, store );

	let chosen: string[] = [ ...new Set( options.selected ) ];
	let active = -1;
	let shown: Choice[] = [];
	let timer = 0;

	const labelOf = ( value: string ): string => known.get( value ) ?? `#${ value }`;
	const sync = (): void => {
		store.replaceChildren(
			...chosen.map( ( value ) => {
				const option = document.createElement( 'option' );
				option.value = value;
				option.textContent = labelOf( value );
				option.selected = true;
				return option;
			} )
		);
		chips.replaceChildren();
		if ( options.multiple ) {
			for ( const value of chosen ) {
				const chip = document.createElement( 'span' );
				chip.className = 'wpe-ss__chip';
				chip.textContent = labelOf( value );
				const remove = document.createElement( 'button' );
				remove.type = 'button';
				remove.className = 'wpe-ss__remove';
				remove.textContent = '×';
				remove.setAttribute( 'aria-label', `Remove ${ labelOf( value ) }` );
				remove.addEventListener( 'click', () => toggle( value ) );
				chip.append( remove );
				chips.append( chip );
			}
			input.placeholder = chosen.length === 0 ? options.placeholder ?? 'Search…' : '';
		} else {
			input.placeholder = chosen[ 0 ] !== undefined ? labelOf( chosen[ 0 ] ) : options.placeholder ?? 'Search…';
		}
	};
	const render = (): void => {
		list.replaceChildren(
			...shown.map( ( [ value, label ], index ) => {
				const item = document.createElement( 'li' );
				item.id = `${ id }-opt-${ index }`;
				item.className = 'wpe-ss__option';
				item.setAttribute( 'role', 'option' );
				item.setAttribute( 'aria-selected', chosen.includes( value ) ? 'true' : 'false' );
				item.classList.toggle( 'is-active', index === active );
				item.textContent = label;
				item.addEventListener( 'mousedown', ( event ) => {
					event.preventDefault(); // keep focus in the input
					toggle( value );
				} );
				return item;
			} )
		);
		if ( shown.length === 0 ) {
			const none = document.createElement( 'li' );
			none.className = 'wpe-ss__empty';
			none.textContent = options.search && input.value.trim().length < 2 ? 'Type 2 or more characters…' : 'No matches';
			list.append( none );
		}
		input.setAttribute( 'aria-activedescendant', active >= 0 ? `${ id }-opt-${ active }` : '' );
		status.textContent = `${ shown.length } results`;
	};
	const filter = (): void => {
		const term = input.value.trim().toLowerCase();
		shown = [ ...known.entries() ].filter( ( [ value, label ] ) => term === '' || label.toLowerCase().includes( term ) || value.toLowerCase().includes( term ) );
		active = shown.length > 0 ? 0 : -1;
		render();
		if ( options.search && term.length >= 2 ) {
			window.clearTimeout( timer );
			const search = options.search;
			timer = window.setTimeout( async () => {
				for ( const [ value, label ] of await search( input.value.trim() ).catch( () => [] as Choice[] ) ) {
					known.set( value, label );
				}
				const now = input.value.trim().toLowerCase();
				shown = [ ...known.entries() ].filter( ( [ value, label ] ) => now === '' || label.toLowerCase().includes( now ) || value === now );
				active = shown.length > 0 ? 0 : -1;
				render();
			}, 250 );
		}
	};
	const open = (): void => {
		list.hidden = false;
		input.setAttribute( 'aria-expanded', 'true' );
		filter();
	};
	const close = (): void => {
		list.hidden = true;
		input.setAttribute( 'aria-expanded', 'false' );
		input.value = '';
	};
	function toggle( value: string ): void {
		if ( options.multiple ) {
			chosen = chosen.includes( value ) ? chosen.filter( ( item ) => item !== value ) : [ ...chosen, value ];
		} else {
			chosen = [ value ];
			close();
		}
		sync();
		if ( ! list.hidden ) {
			render();
		}
		options.onChange?.( [ ...chosen ] );
		store.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	input.addEventListener( 'focus', open );
	input.addEventListener( 'input', () => {
		if ( list.hidden ) {
			open();
		} else {
			filter();
		}
	} );
	input.addEventListener( 'blur', () => window.setTimeout( close, 120 ) );
	input.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
			event.preventDefault();
			if ( list.hidden ) {
				open();
				return;
			}
			if ( shown.length > 0 ) {
				active = ( active + ( event.key === 'ArrowDown' ? 1 : -1 ) + shown.length ) % shown.length;
				render();
				list.querySelector( '.is-active' )?.scrollIntoView( { block: 'nearest' } );
			}
		} else if ( event.key === 'Enter' ) {
			event.preventDefault();
			const pick = shown[ active ];
			if ( ! list.hidden && pick ) {
				toggle( pick[ 0 ] );
			}
		} else if ( event.key === 'Escape' ) {
			close();
		} else if ( event.key === 'Backspace' && input.value === '' && options.multiple && chosen.length > 0 ) {
			toggle( chosen[ chosen.length - 1 ] ?? '' );
		}
	} );
	control.addEventListener( 'click', () => input.focus() );

	sync();
	const unknown = chosen.filter( ( value ) => ! known.has( value ) );
	if ( unknown.length > 0 && options.resolve ) {
		void options
			.resolve( unknown )
			.then( ( found ) => {
				for ( const [ value, label ] of found ) {
					known.set( value, label );
				}
				sync();
			} )
			.catch( () => undefined );
	}

	return { element: root, select: store, values: () => [ ...chosen ] };
}
