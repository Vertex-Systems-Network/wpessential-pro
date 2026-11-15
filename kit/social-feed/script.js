/* Progressive enhancement: manual and WordPress items remain visible without JS. */
( function () {
	'use strict';
	function init( root ) {
		if ( root.dataset.wpeFeedReady ) { return; }
		root.dataset.wpeFeedReady = '1';
		var items = Array.prototype.slice.call( root.querySelectorAll( '[data-wpe-feed-item]' ) );
		var button = root.querySelector( '.wpe-social-feed__more' );
		if ( ! button || ! items.length ) { return; }
		var batch = Math.max( 1, Math.min( 18, Number( root.dataset.initial ) || 6 ) );
		var visible = Math.min( batch, items.length );
		function paint() {
			items.forEach( function ( item, i ) { item.hidden = i >= visible; } );
			button.hidden = visible >= items.length;
		}
		button.addEventListener( 'click', function () {
			visible = Math.min( visible + batch, items.length );
			paint();
		} );
		paint();
		button.hidden = visible >= items.length;
	}
	function boot() { document.querySelectorAll( '[data-wpe-feed]' ).forEach( init ); }
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
	if ( window.MutationObserver ) {
		new window.MutationObserver( function ( records ) {
			records.forEach( function ( rec ) { rec.addedNodes.forEach( function ( node ) {
				if ( node.nodeType !== 1 ) { return; }
				if ( node.matches( '[data-wpe-feed]' ) ) { init( node ); }
				node.querySelectorAll( '[data-wpe-feed]' ).forEach( init );
			} ); } );
		} ).observe( document.documentElement, { subtree: true, childList: true } );
	}
}() );
