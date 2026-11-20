// "Visibility" of a Field Group (JetEngine "Visibility conditions", extended):
//   Meta box for   posts / taxonomy terms / users            (storage mode)
//   Enable for     post types / taxonomies / user roles      (required anchor, several allowed)
//   Visible at     edit-user screen only, or also the own profile          (users)
//   Conditions     include / exclude rows, matched All (AND) or Any (OR):
//     posts:  specific posts, post status, page template, post format, parent, post has terms
//     terms:  specific terms (search or IDs), parent term
//     users:  specific users (search or IDs)
//     all:    current user role, current user
// Saved as the normalizer's location groups: [anchor, ...conditions] for All, one
// [anchor, condition] group per condition for Any. Exclude = not_in.
import { type Choice, searchSelect } from './search-select';

type JsonObject = Record< string, unknown >;

let CHOICES: Record< string, Choice[] > = {};
let SEARCH: { url: string; nonce: string } | null = null;

const isObject = ( candidate: unknown ): candidate is JsonObject =>
	typeof candidate === 'object' && candidate !== null && ! Array.isArray( candidate );

/** Lists for the pickers, from the builder bootstrap (post types, statuses, taxonomies, roles, templates, formats). */
export function setLocationChoices( data: unknown ): void {
	if ( ! isObject( data ) ) {
		return;
	}
	const next: Record< string, Choice[] > = {};
	for ( const [ key, list ] of Object.entries( data ) ) {
		if ( Array.isArray( list ) ) {
			next[ key ] = list.filter( ( pair ): pair is Choice => Array.isArray( pair ) && typeof pair[ 0 ] === 'string' && typeof pair[ 1 ] === 'string' );
		}
	}
	CHOICES = next;
	const search = data.search;
	SEARCH = isObject( search ) && typeof search.url === 'string' && typeof search.nonce === 'string' ? { url: search.url, nonce: search.nonce } : null;
}

export const MODES: Choice[] = [
	[ 'native_post_meta', 'Posts' ],
	[ 'native_term_meta', 'Taxonomy terms' ],
	[ 'native_user_meta', 'Users' ],
];
const ANCHOR: Record< string, { source: string; label: string } > = {
	native_post_meta: { source: 'post_type', label: 'Enable for post types' },
	native_term_meta: { source: 'taxonomy', label: 'Enable for taxonomies' },
	native_user_meta: { source: 'user_role', label: 'Enable for user roles' },
};
type Kind = 'list' | 'post' | 'term' | 'user' | 'post_term';
const CONDITIONS: Record< string, Array< { source: string; label: string; kind: Kind } > > = {
	native_post_meta: [
		{ source: 'entity_id', label: 'Posts', kind: 'post' },
		{ source: 'post_status', label: 'Post status', kind: 'list' },
		{ source: 'page_template', label: 'Page template', kind: 'list' },
		{ source: 'post_format', label: 'Post format', kind: 'list' },
		{ source: 'post_parent', label: 'Parent post', kind: 'post' },
		{ source: 'post_term', label: 'Post has terms', kind: 'post_term' },
	],
	native_term_meta: [
		{ source: 'term', label: 'Terms', kind: 'term' },
		{ source: 'term_parent', label: 'Parent term', kind: 'term' },
	],
	native_user_meta: [ { source: 'user', label: 'Users', kind: 'user' } ],
};
const COMMON = [
	{ source: 'current_user_role', label: 'Current user role (editor)', kind: 'list' as Kind },
	{ source: 'current_user', label: 'Current user (editor)', kind: 'user' as Kind },
];
const conditionsFor = ( mode: string ) => [ ...( CONDITIONS[ mode ] ?? [] ), ...COMMON ];
const CHOICE_KEY: Record< string, string > = { current_user_role: 'user_role' };

function el< K extends keyof HTMLElementTagNameMap >( tag: K, className?: string, text?: string ): HTMLElementTagNameMap[ K ] {
	const node = document.createElement( tag );
	if ( className ) {
		node.className = className;
	}
	if ( text !== undefined ) {
		node.textContent = text;
	}
	return node;
}

async function rest( path: string, query: Record< string, string > ): Promise< unknown[] > {
	if ( SEARCH === null ) {
		return [];
	}
	const base = SEARCH.url.replace( /search\/?$/, path );
	const url = `${ base }${ base.includes( '?' ) ? '&' : '?' }${ new URLSearchParams( query ).toString() }`;
	const response = await fetch( url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': SEARCH.nonce } } );
	const json: unknown = response.ok ? await response.json() : [];
	return Array.isArray( json ) ? json : [];
}

const searchRow = ( item: unknown ): Choice | null =>
	isObject( item ) && typeof item.id === 'number'
		? [ String( item.id ), `${ typeof item.title === 'string' ? item.title : '' } (#${ item.id }${ typeof item.subtype === 'string' ? `, ${ item.subtype }` : '' })` ]
		: null;
const userRow = ( item: unknown ): Choice | null =>
	isObject( item ) && typeof item.id === 'number' ? [ String( item.id ), `${ typeof item.name === 'string' ? item.name : '' } (#${ item.id })` ] : null;
const rows = ( list: unknown[], map: ( item: unknown ) => Choice | null ): Choice[] => list.map( map ).filter( ( row ): row is Choice => row !== null );

/** Search + label lookup for posts, terms (optionally of one taxonomy) and users. */
function remote( kind: Kind, subtype = '' ) {
	if ( kind === 'user' ) {
		return {
			search: async ( term: string ) => rows( await rest( 'users', { search: term, per_page: '20', context: 'edit' } ), userRow ),
			resolve: async ( ids: string[] ) => rows( await rest( 'users', { include: ids.join( ',' ), per_page: '100', context: 'edit' } ), userRow ),
		};
	}
	const type = kind === 'post' ? 'post' : 'term';
	const extra: Record< string, string > = subtype !== '' ? { subtype } : {};
	return {
		search: async ( term: string ) => rows( await rest( 'search', { type, search: term, per_page: '20', ...extra } ), searchRow ),
		resolve: async ( ids: string[] ) => rows( await rest( 'search', { type, include: ids.join( ',' ), per_page: '100', ...extra } ), searchRow ),
	};
}

/** One condition row: [condition] [include/exclude] [values…] [remove]. */
function conditionRow( mode: string, rule: JsonObject ): HTMLDivElement {
	const row = el( 'div', 'wpessential-fields-condition' );
	const source = el( 'select' );
	source.setAttribute( 'aria-label', 'Condition' );
	source.dataset.wpessentialVisibility = 'source';
	for ( const item of conditionsFor( mode ) ) {
		const option = el( 'option', undefined, item.label );
		option.value = item.source;
		source.append( option );
	}
	source.value = typeof rule.source === 'string' && conditionsFor( mode ).some( ( item ) => item.source === rule.source ) ? rule.source : conditionsFor( mode )[ 0 ]?.source ?? '';
	const mode2 = el( 'select' );
	mode2.setAttribute( 'aria-label', 'Include or exclude' );
	mode2.dataset.wpessentialVisibility = 'operator';
	for ( const [ value, label ] of [
		[ 'in', 'Include' ],
		[ 'not_in', 'Exclude' ],
	] ) {
		const option = el( 'option', undefined, label );
		option.value = value ?? '';
		mode2.append( option );
	}
	const exclude = ( rule.operator === 'not_in' || rule.operator === 'not_equals' ) !== ( rule.negate === true );
	mode2.value = exclude ? 'not_in' : 'in';
	const values = el( 'span', 'wpessential-fields-condition__values' );
	const remove = el( 'button', 'button-link wpessential-fields-condition__remove', 'Remove' );
	remove.type = 'button';
	remove.addEventListener( 'click', () => row.remove() );

	const saved = ( Array.isArray( rule.value ) ? rule.value : rule.value === undefined || rule.value === '' ? [] : [ rule.value ] ).map( String );
	const build = ( current: string[] ): void => {
		values.replaceChildren();
		const spec = conditionsFor( mode ).find( ( item ) => item.source === source.value );
		if ( ! spec ) {
			return;
		}
		if ( spec.kind === 'post_term' ) {
			// "Post has terms": pick the taxonomy, then search its terms. Saved as "taxonomy:termId".
			const firstTax = current[ 0 ]?.split( ':' )[ 0 ] ?? CHOICES.taxonomy?.[ 0 ]?.[ 0 ] ?? 'category';
			const taxonomy = el( 'select' );
			taxonomy.setAttribute( 'aria-label', 'Taxonomy' );
			for ( const [ value, label ] of CHOICES.taxonomy ?? [] ) {
				const option = el( 'option', undefined, label );
				option.value = value;
				taxonomy.append( option );
			}
			taxonomy.value = firstTax;
			const terms = (): void => {
				const tax = taxonomy.value;
				const ids = current.filter( ( item ) => item.startsWith( `${ tax }:` ) ).map( ( item ) => item.slice( tax.length + 1 ) );
				const lookup = remote( 'term', tax );
				const picker = searchSelect( {
					label: 'Terms',
					multiple: true,
					choices: [],
					selected: ids,
					search: lookup.search,
					resolve: lookup.resolve,
				} );
				picker.select.dataset.wpessentialVisibility = 'values';
				picker.select.dataset.wpessentialVisibilityPrefix = `${ tax }:`;
				values.querySelector( '.wpe-ss' )?.remove();
				values.append( picker.element );
			};
			taxonomy.addEventListener( 'change', () => {
				current = [];
				terms();
			} );
			values.append( taxonomy );
			terms();
			return;
		}
		const lookup = spec.kind === 'list' ? null : remote( spec.kind );
		const picker = searchSelect( {
			label: spec.label,
			multiple: true,
			choices: spec.kind === 'list' ? CHOICES[ CHOICE_KEY[ spec.source ] ?? spec.source ] ?? [] : spec.source.endsWith( '_parent' ) ? [ [ '0', '(no parent, top level)' ] ] : [],
			selected: current,
			...( lookup ? { search: lookup.search, resolve: lookup.resolve } : {} ),
		} );
		picker.select.dataset.wpessentialVisibility = 'values';
		values.append( picker.element );
		if ( spec.kind === 'term' || spec.kind === 'user' ) {
			// Custom IDs typed by hand, for terms and users that are easier to name by number.
			const ids = el( 'input' );
			ids.type = 'text';
			ids.placeholder = 'Custom IDs, e.g. 12, 40';
			ids.setAttribute( 'aria-label', `Custom ${ spec.kind } IDs` );
			ids.dataset.wpessentialVisibility = 'ids';
			values.append( ids );
		}
	};
	source.addEventListener( 'change', () => build( [] ) );
	build( saved );
	row.append( source, mode2, values, remove );
	return row;
}

function readRow( row: HTMLElement ): JsonObject | null {
	const source = row.querySelector< HTMLSelectElement >( '[data-wpessential-visibility="source"]' )?.value ?? '';
	const operator = row.querySelector< HTMLSelectElement >( '[data-wpessential-visibility="operator"]' )?.value ?? 'in';
	const store = row.querySelector< HTMLSelectElement >( 'select[data-wpessential-visibility="values"]' );
	const prefix = store?.dataset.wpessentialVisibilityPrefix ?? '';
	const picked = store ? Array.from( store.options, ( option ) => prefix + option.value ) : [];
	const typed = ( row.querySelector< HTMLInputElement >( '[data-wpessential-visibility="ids"]' )?.value ?? '' )
		.split( ',' )
		.map( ( item ) => item.trim() )
		.filter( ( item ) => /^\d+$/.test( item ) );
	const value = [ ...new Set( [ ...picked, ...typed ] ) ];
	return source !== '' && value.length > 0 ? { source, operator, value, negate: false } : null;
}

export interface VisibilityEditor {
	element: HTMLElement;
	set( mode: string, locations: unknown ): void;
	collect( mode: string ): JsonObject[][];
}

/** The whole Visibility block. The storage select ("Meta box for") lives in the group settings and calls set() on change. */
export function visibilityEditor(): VisibilityEditor {
	const root = el( 'div', 'wpessential-fields-visibility' );
	root.id = 'wpessential-fields-locations';
	const anchorHost = el( 'div', 'wpessential-fields-visibility__anchor' );
	const screenHost = el( 'div', 'wpessential-fields-visibility__screen' );
	const relation = el( 'select' );
	relation.id = 'wpessential-fields-visibility-relation';
	for ( const [ value, label ] of [
		[ 'all', 'All conditions match (AND)' ],
		[ 'any', 'Any condition matches (OR)' ],
	] ) {
		const option = el( 'option', undefined, label );
		option.value = value ?? '';
		relation.append( option );
	}
	const relationLabel = el( 'label', 'wpessential-fields-setting' );
	relationLabel.append( el( 'span', undefined, 'Show when' ), relation );
	const list = el( 'div', 'wpessential-fields-conditions-list' );
	const add = el( 'button', 'button', '+ New condition' );
	add.type = 'button';
	let mode = 'native_post_meta';
	let anchor = searchSelect( { label: 'Enable for', multiple: true, choices: [], selected: [] } );
	add.addEventListener( 'click', () => list.append( conditionRow( mode, {} ) ) );
	const screen = el( 'select' );
	screen.id = 'wpessential-fields-visible-at';
	for ( const [ value, label ] of [
		[ '', 'Edit user and the user’s own profile' ],
		[ 'edit', 'Edit user screen only (administrators)' ],
	] ) {
		const option = el( 'option', undefined, label );
		option.value = value ?? '';
		screen.append( option );
	}
	const screenLabel = el( 'label', 'wpessential-fields-setting' );
	screenLabel.append( el( 'span', undefined, 'Visible at' ), screen );
	screenHost.append( screenLabel );
	root.append(
		anchorHost,
		screenHost,
		el( 'h5', undefined, 'Conditions (optional)' ),
		el( 'p', 'description', 'Narrow where the fields show. Include or exclude specific items; leave empty to show everywhere the group is enabled.' ),
		relationLabel,
		list,
		add
	);

	const set = ( nextMode: string, locations: unknown ): void => {
		mode = ANCHOR[ nextMode ] ? nextMode : 'native_post_meta';
		const spec = ANCHOR[ mode ] ?? { source: 'post_type', label: 'Enable for' };
		const groups = ( Array.isArray( locations ) ? locations : [] ).map( ( group ) => ( Array.isArray( group ) ? group.filter( isObject ) : [] ) );
		const anchorValues = new Set< string >();
		const conditions: JsonObject[] = [];
		const seen = new Set< string >();
		let screenValue = '';
		for ( const group of groups ) {
			for ( const rule of group ) {
				const values = ( Array.isArray( rule.value ) ? rule.value : [ rule.value ] ).map( String ).filter( ( item ) => item !== '' );
				if ( rule.source === spec.source ) {
					for ( const item of values ) {
						anchorValues.add( item );
					}
				} else if ( rule.source === 'user_screen' ) {
					screenValue = values[ 0 ] ?? '';
				} else {
					const key = JSON.stringify( rule );
					if ( ! seen.has( key ) ) {
						seen.add( key );
						conditions.push( rule );
					}
				}
			}
		}
		relation.value = groups.length > 1 && conditions.length > 1 ? 'any' : 'all';
		anchor = searchSelect( {
			label: spec.label,
			multiple: true,
			choices: CHOICES[ spec.source ] ?? [],
			selected: [ ...anchorValues ],
			placeholder: 'Choose one or more…',
		} );
		anchor.select.id = 'wpessential-fields-enable-for';
		const anchorLabel = el( 'div', 'wpessential-fields-setting' );
		anchorLabel.append( el( 'span', undefined, `${ spec.label } (required)` ), anchor.element );
		anchorHost.replaceChildren( anchorLabel );
		screen.value = screenValue === 'edit' ? 'edit' : '';
		screenHost.hidden = mode !== 'native_user_meta';
		list.replaceChildren( ...conditions.map( ( rule ) => conditionRow( mode, rule ) ) );
	};

	const collect = ( current: string ): JsonObject[][] => {
		const spec = ANCHOR[ current ] ?? { source: 'post_type', label: 'Enable for' };
		const enabled = anchor.values();
		const conditions = Array.from( list.querySelectorAll< HTMLElement >( '.wpessential-fields-condition' ) )
			.map( readRow )
			.filter( ( rule ): rule is JsonObject => rule !== null );
		if ( enabled.length === 0 ) {
			if ( conditions.length > 0 ) {
				throw new Error( `${ spec.label }: choose at least one, so the fields know where to appear.` );
			}
			return [];
		}
		const head: JsonObject[] = [ { source: spec.source, operator: 'in', value: enabled, negate: false } ];
		if ( current === 'native_user_meta' && screen.value === 'edit' ) {
			head.push( { source: 'user_screen', operator: 'in', value: [ 'edit' ], negate: false } );
		}
		if ( relation.value === 'any' && conditions.length > 1 ) {
			return conditions.map( ( rule ) => [ ...head, rule ] );
		}
		return [ [ ...head, ...conditions ] ];
	};

	return { element: root, set, collect };
}
