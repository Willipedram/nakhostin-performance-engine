( function () {
	'use strict';

	function ready( callback ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', callback, { once: true } );
			return;
		}
		callback();
	}

	ready( function () {
		var tablist = document.querySelector( '.npe-settings-tabs' );
		var tabs = Array.prototype.slice.call( document.querySelectorAll( '.npe-settings-tabs [data-npe-tab]' ) );
		var panels = Array.prototype.slice.call( document.querySelectorAll( '.npe-settings-panel[data-npe-section]' ) );
		if ( ! tablist || ! tabs.length || ! panels.length ) {
			return;
		}

		function hasKey( key ) {
			return tabs.some( function ( tab ) { return tab.getAttribute( 'data-npe-tab' ) === key; } );
		}

		function activate( requestedKey, moveFocus, updateUrl ) {
			var key = hasKey( requestedKey ) ? requestedKey : 'general';
			tabs.forEach( function ( tab ) {
				var active = tab.getAttribute( 'data-npe-tab' ) === key;
				tab.classList.toggle( 'nav-tab-active', active );
				tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				tab.setAttribute( 'tabindex', active ? '0' : '-1' );
				if ( active ) {
					tab.setAttribute( 'aria-current', 'page' );
					if ( moveFocus ) {
						tab.focus();
					}
				} else {
					tab.removeAttribute( 'aria-current' );
				}
			} );
			panels.forEach( function ( panel ) {
				var visible = panel.getAttribute( 'data-npe-section' ) === key;
				panel.hidden = ! visible;
				panel.setAttribute( 'aria-hidden', visible ? 'false' : 'true' );
			} );
			if ( updateUrl && window.history && window.history.replaceState ) {
				var url = new URL( window.location.href );
				url.searchParams.set( 'section', key );
				url.hash = 'npe-settings-' + key;
				window.history.replaceState( null, '', url.toString() );
			}
		}

		tabs.forEach( function ( tab, index ) {
			tab.setAttribute( 'role', 'tab' );
			tab.addEventListener( 'click', function ( event ) {
				// The normal URL remains a no-JavaScript fallback.
				event.preventDefault();
				activate( tab.getAttribute( 'data-npe-tab' ), false, true );
			} );
			tab.addEventListener( 'keydown', function ( event ) {
				if ( 'ArrowLeft' !== event.key && 'ArrowRight' !== event.key && 'Home' !== event.key && 'End' !== event.key ) {
					return;
				}
				event.preventDefault();
				var next = index;
				if ( 'Home' === event.key ) {
					next = 0;
				} else if ( 'End' === event.key ) {
					next = tabs.length - 1;
				} else {
					var direction = 'ArrowRight' === event.key ? 1 : -1;
					next = ( index + direction + tabs.length ) % tabs.length;
				}
				activate( tabs[ next ].getAttribute( 'data-npe-tab' ), true, true );
			} );
		} );

		var params = new URLSearchParams( window.location.search );
		var hashKey = window.location.hash.indexOf( '#npe-settings-' ) === 0 ? window.location.hash.replace( '#npe-settings-', '' ) : '';
		activate( hashKey || params.get( 'section' ) || 'general', false, false );
	} );
}() );
