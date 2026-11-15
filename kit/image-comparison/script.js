/* Native range works for keyboard, pointer and touch once enhanced. */
( function () {
	'use strict';
	function init( root ) {
		if ( root.dataset.wpeComparisonReady ) { return; }
		var input = root.querySelector( '.wpe-image-comparison__slider' );
		if ( ! input ) { return; }
		root.dataset.wpeComparisonReady = '1';
		input.hidden = false;
		function update() {
			var value = Math.max( 0, Math.min( 100, Number( input.value ) || 0 ) );
			root.style.setProperty( '--wpek-compare-progress', value + '%' );
			input.setAttribute( 'aria-valuetext', value + '% ' + ( input.labels && input.labels[ 0 ] ? input.labels[ 0 ].textContent : 'Before' ) );
		}
		input.addEventListener( 'input', update );
		update();
	}
	function boot() { document.querySelectorAll( '[data-wpe-comparison]' ).forEach( init ); }
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
	if ( window.MutationObserver ) {
		new window.MutationObserver( function ( records ) {
			records.forEach( function ( rec ) { rec.addedNodes.forEach( function ( node ) {
				if ( node.nodeType !== 1 ) { return; }
				if ( node.matches( '[data-wpe-comparison]' ) ) { init( node ); }
				node.querySelectorAll( '[data-wpe-comparison]' ).forEach( init );
			} ); } );
		} ).observe( document.documentElement, { subtree: true, childList: true } );
	}
}() );
