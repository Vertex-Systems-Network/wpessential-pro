/* Scroll-snap carousel: no JS required for ordinary horizontal scrolling. */
( function () {
	'use strict';
	function init( root ) {
		if ( root.dataset.wpeCarouselReady ) { return; }
		var viewport = root.querySelector( '.wpe-image-carousel__viewport' );
		var slides = Array.prototype.slice.call( root.querySelectorAll( '.wpe-image-carousel__card' ) );
		if ( ! viewport || ! slides.length ) { return; }
		root.dataset.wpeCarouselReady = '1';
		var controls = root.querySelector( '.wpe-image-carousel__controls' );
		var previous = root.querySelector( '[data-carousel-prev]' );
		var next = root.querySelector( '[data-carousel-next]' );
		var dots = Array.prototype.slice.call( root.querySelectorAll( '[data-carousel-dot]' ) );
		var loop = root.dataset.loop === '1';
		var reduced = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
		var index = 0, handle = null, paused = false;
		if ( controls ) { controls.hidden = false; }
		function show( desired ) {
			index = loop ? ( desired + slides.length ) % slides.length : Math.max( 0, Math.min( slides.length - 1, desired ) );
			if ( viewport.scrollTo ) {
				viewport.scrollTo( { left: slides[ index ].offsetLeft - slides[ 0 ].offsetLeft, behavior: reduced ? 'auto' : 'smooth' } );
			}
			updateControls();
		}
		function updateControls() {
			dots.forEach( function ( dot, i ) {
				if ( i === index ) { dot.setAttribute( 'aria-current', 'true' ); } else { dot.removeAttribute( 'aria-current' ); }
			} );
			if ( previous ) { previous.disabled = ! loop && index === 0; }
			if ( next ) { next.disabled = ! loop && index === slides.length - 1; }
		}
		function stop() {
			if ( handle !== null ) { window.clearInterval( handle ); handle = null; }
		}
		function start() {
			stop();
			if ( root.dataset.autoplay !== '1' || reduced || paused || slides.length < 2 || ! root.isConnected ) { return; }
			handle = window.setInterval( function () {
				if ( ! root.isConnected || document.hidden ) { stop(); return; }
				if ( ! loop && index === slides.length - 1 ) { stop(); return; }
				show( index + 1 );
			}, Math.max( 2000, Math.min( 15000, Number( root.dataset.interval ) || 5000 ) ) );
		}
		// Native wheel/touch/keyboard scrolling also updates the active indicator.
		viewport.addEventListener( 'scroll', function () {
			var position = viewport.scrollLeft;
			var nearest = 0;
			var distance = Infinity;
			slides.forEach( function ( slide, i ) {
				var delta = Math.abs( position - ( slide.offsetLeft - slides[ 0 ].offsetLeft ) );
				if ( delta < distance ) { distance = delta; nearest = i; }
			} );
			if ( nearest !== index ) { index = nearest; updateControls(); }
		} );
		if ( previous ) { previous.addEventListener( 'click', function () { show( index - 1 ); } ); }
		if ( next ) { next.addEventListener( 'click', function () { show( index + 1 ); } ); }
		dots.forEach( function ( dot, i ) { dot.addEventListener( 'click', function () { show( i ); } ); } );
		if ( root.dataset.pauseHover === '1' ) {
			root.addEventListener( 'mouseenter', function () { paused = true; stop(); } );
			root.addEventListener( 'mouseleave', function () { paused = false; start(); } );
			root.addEventListener( 'focusin', function () { paused = true; stop(); } );
			root.addEventListener( 'focusout', function ( event ) { if ( ! root.contains( event.relatedTarget ) ) { paused = false; start(); } } );
		}
		document.addEventListener( 'visibilitychange', function () { if ( document.hidden ) { stop(); } else { start(); } } );
		show( 0 );
		start();
	}
	function boot() { document.querySelectorAll( '[data-wpe-carousel]' ).forEach( init ); }
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
	if ( window.MutationObserver ) {
		new window.MutationObserver( function ( records ) {
			records.forEach( function ( record ) { record.addedNodes.forEach( function ( node ) {
				if ( node.nodeType !== 1 ) { return; }
				if ( node.matches( '[data-wpe-carousel]' ) ) { init( node ); }
				node.querySelectorAll( '[data-wpe-carousel]' ).forEach( init );
			} ); } );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}
}() );
