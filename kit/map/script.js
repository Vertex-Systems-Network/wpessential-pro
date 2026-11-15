/* OpenStreetMap iframe is created only on explicit user consent. */
( function () {
	'use strict';
	function init( root ) {
		if ( root.dataset.wpeMapReady ) { return; }
		root.dataset.wpeMapReady = '1';
		var button = root.querySelector( '.wpe-map__load' );
		var frame = root.querySelector( '.wpe-map__frame' );
		if ( ! button || ! frame ) { return; }
		button.addEventListener( 'click', function () {
			var raw = button.dataset.mapSrc || '';
			if ( ! /^https:\/\/www\.openstreetmap\.org\/export\/embed\.html\?/.test( raw ) ) { return; }
			var iframe = document.createElement( 'iframe' );
			iframe.className = 'wpe-map__iframe';
			iframe.title = button.dataset.mapTitle || 'Map';
			iframe.loading = 'lazy';
			iframe.referrerPolicy = 'no-referrer';
			iframe.src = raw;
			frame.replaceChildren( iframe );
		} );
	}
	function boot() { document.querySelectorAll( '[data-wpe-map]' ).forEach( init ); }
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
	if ( window.MutationObserver ) {
		new window.MutationObserver( function ( recs ) {
			recs.forEach( function ( rec ) { rec.addedNodes.forEach( function ( node ) {
				if ( node.nodeType !== 1 ) { return; }
				if ( node.matches( '[data-wpe-map]' ) ) { init( node ); }
				node.querySelectorAll( '[data-wpe-map]' ).forEach( init );
			} ); } );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}
}() );
