/* Native dialog progressively enhances a readable <details> fallback. */
( function () {
	'use strict';
	function init( root ) {
		if ( root.dataset.wpeModalReady ) { return; }
		var dialog = root.querySelector( '.wpe-modal__dialog' );
		var fallback = root.querySelector( '.wpe-modal__fallback' );
		var trigger = root.querySelector( '.wpe-modal__open' );
		if ( ! dialog || ! fallback || ! trigger || typeof dialog.showModal !== 'function' ) { return; }
		root.dataset.wpeModalReady = '1';
		var close = root.querySelector( '.wpe-modal__close' );
		var bottom = root.querySelector( '.wpe-modal__close-bottom' );
		fallback.hidden = true;
		trigger.hidden = false;
		trigger.addEventListener( 'click', function () {
			dialog.showModal();
			if ( close ) { close.focus(); }
		} );
		function dismiss() { dialog.close(); }
		if ( close ) { close.addEventListener( 'click', dismiss ); }
		if ( bottom ) { bottom.addEventListener( 'click', dismiss ); }
		dialog.addEventListener( 'click', function ( event ) {
			if ( '1' === root.dataset.closeBackdrop && event.target === dialog ) { dismiss(); }
		} );
		dialog.addEventListener( 'close', function () {
			if ( trigger.isConnected ) { trigger.focus(); }
		} );
	}
	function boot() { document.querySelectorAll( '[data-wpe-modal]' ).forEach( init ); }
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
	if ( window.MutationObserver ) {
		new window.MutationObserver( function ( records ) {
			records.forEach( function ( record ) { record.addedNodes.forEach( function ( node ) {
				if ( node.nodeType !== 1 ) { return; }
				if ( node.matches( '[data-wpe-modal]' ) ) { init( node ); }
				node.querySelectorAll( '[data-wpe-modal]' ).forEach( init );
			} ); } );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}
}() );
