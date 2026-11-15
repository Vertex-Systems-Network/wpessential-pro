/**
 * Interactive Kit progressive enhancement.
 * Tooltip content works on hover/focus without JS; native details need no JS;
 * the switcher hides inactive panels only after tab keyboard support is ready.
 */
( function () {
	'use strict';

	function tooltip( root ) {
		var wrap = root.querySelector( '.wpe-tooltip__tip-wrap' );
		if ( ! wrap ) {
			return;
		}
		var trigger = wrap.querySelector( '.wpe-tooltip__trigger' );
		if ( ! trigger || ! wrap.querySelector( '.wpe-tooltip__bubble' ) ) {
			return;
		}
		trigger.addEventListener( 'click', function () {
			var opened = wrap.classList.contains( 'is-open' );
			wrap.classList.toggle( 'is-open', ! opened );
			wrap.classList.toggle( 'is-dismissed', opened );
		} );
		trigger.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				wrap.classList.remove( 'is-open' );
				wrap.classList.add( 'is-dismissed' );
				event.preventDefault();
			}
		} );
		wrap.addEventListener( 'mouseleave', function () {
			wrap.classList.remove( 'is-dismissed' );
		} );
		wrap.addEventListener( 'focusout', function ( event ) {
			if ( ! wrap.contains( event.relatedTarget ) ) {
				wrap.classList.remove( 'is-open' );
				wrap.classList.remove( 'is-dismissed' );
			}
		} );
	}

	function switcher( root ) {
		var nav = root.querySelector( '[data-switch-buttons]' );
		if ( ! nav ) {
			return;
		}
		var buttons = Array.prototype.slice.call( nav.querySelectorAll( '[data-switch-to]' ) );
		var panels = Array.prototype.slice.call( root.querySelectorAll( '[data-switch-panel]' ) );
		if ( 2 !== buttons.length || 2 !== panels.length ) {
			return;
		}
		var keys = [ 'first', 'second' ];
		var active = root.dataset.initial === 'second' ? 1 : 0;
		var rtl = document.documentElement && 'rtl' === document.documentElement.dir;
		nav.setAttribute( 'role', 'tablist' );
		nav.setAttribute( 'aria-label', nav.dataset.switchLabel || 'Options' );

		function activate( index, focus ) {
			active = index;
			buttons.forEach( function ( button, i ) {
				button.setAttribute( 'role', 'tab' );
				button.setAttribute( 'aria-selected', i === active ? 'true' : 'false' );
				button.tabIndex = i === active ? 0 : -1;
				if ( focus && i === active ) {
					button.focus();
				}
			} );
			panels.forEach( function ( panel, i ) {
				panel.setAttribute( 'role', 'tabpanel' );
				panel.hidden = i !== active;
				panel.tabIndex = 0;
			} );
		}
		buttons.forEach( function ( button, index ) {
			if ( button.dataset.switchTo !== keys[ index ] || panels[ index ].dataset.switchPanel !== keys[ index ] ) {
				return;
			}
			button.addEventListener( 'click', function () {
				activate( index, false );
			} );
			button.addEventListener( 'keydown', function ( event ) {
				var target = null;
				if ( 'Home' === event.key ) {
					target = 0;
				} else if ( 'End' === event.key ) {
					target = buttons.length - 1;
				} else if ( 'ArrowRight' === event.key ) {
					target = ( active + ( rtl ? -1 : 1 ) + buttons.length ) % buttons.length;
				} else if ( 'ArrowLeft' === event.key ) {
					target = ( active + ( rtl ? 1 : -1 ) + buttons.length ) % buttons.length;
				}
				if ( null !== target ) {
					event.preventDefault();
					activate( target, true );
				}
			} );
		} );
		activate( active, false );
	}

	function init( root ) {
		if ( root.dataset.wpeInteractiveReady ) {
			return;
		}
		root.dataset.wpeInteractiveReady = '1';
		if ( 'tooltip' === root.dataset.wpeInteractive ) {
			tooltip( root );
		} else if ( 'switcher' === root.dataset.wpeInteractive ) {
			switcher( root );
		}
	}
	function boot() {
		document.querySelectorAll( '[data-wpe-interactive]' ).forEach( init );
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
	if ( 'MutationObserver' in window ) {
		new window.MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( 1 !== node.nodeType ) {
						return;
					}
					if ( node.matches( '[data-wpe-interactive]' ) ) {
						init( node );
					}
					node.querySelectorAll( '[data-wpe-interactive]' ).forEach( init );
				} );
			} );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}
}() );
