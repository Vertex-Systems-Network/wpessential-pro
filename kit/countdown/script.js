/* Client countdown uses a fixed epoch; no reliance on the visitor's timezone. */
( function () {
	'use strict';
	function init( root ) {
		if ( root.dataset.wpeCountdownReady ) { return; }
		root.dataset.wpeCountdownReady = '1';
		var end = Number( root.dataset.end );
		if ( ! Number.isFinite( end ) || end <= 0 ) { return; }
		var nodes = {};
		root.querySelectorAll( '[data-unit]' ).forEach( function ( el ) {
			nodes[ el.dataset.unit ] = el.querySelector( '.wpe-countdown__number' );
		} );
		var completed = root.querySelector( '.wpe-countdown__completed' );
		var handle = null;
		function display( value, unit ) {
			if ( nodes[ unit ] ) {
				nodes[ unit ].textContent = String( value ).padStart( 2, '0' );
			}
		}
		function tick() {
			var remaining = Math.max( 0, Math.ceil( ( end - Date.now() ) / 1000 ) );
			var rest = remaining;
			[ [ 'days', 86400 ], [ 'hours', 3600 ], [ 'minutes', 60 ], [ 'seconds', 1 ] ].forEach( function ( entry ) {
				if ( nodes[ entry[ 0 ] ] ) {
					display( Math.floor( rest / entry[ 1 ] ), entry[ 0 ] );
					rest = rest % entry[ 1 ];
				}
			} );
			if ( ! root.isConnected || 0 === remaining ) {
				if ( handle !== null ) { window.clearInterval( handle ); }
				if ( 0 === remaining ) {
					root.dataset.expired = '1';
					if ( completed ) { completed.hidden = false; }
				}
			}
		}
		root.dataset.ready = '1';
		tick();
		if ( root.isConnected && root.dataset.expired !== '1' ) {
			handle = window.setInterval( tick, 1000 );
		}
	}
	function boot() {
		document.querySelectorAll( '[data-wpe-countdown]' ).forEach( init );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
	if ( window.MutationObserver ) {
		new window.MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( node.nodeType !== 1 ) { return; }
					if ( node.matches( '[data-wpe-countdown]' ) ) { init( node ); }
					node.querySelectorAll( '[data-wpe-countdown]' ).forEach( init );
				} );
			} );
		} ).observe( document.documentElement, { subtree: true, childList: true } );
	}
}() );
