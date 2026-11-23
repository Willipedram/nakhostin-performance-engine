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
		var form = document.querySelector( '.npe-settings-form' );
		if ( ! tablist || ! tabs.length || ! panels.length || ! form ) {
			return;
		}
		var status = form.querySelector( '[data-npe-save-status]' );
		var referer = form.elements.namedItem( '_wp_http_referer' );
		var rtl = tablist.closest( '[dir]' ).dir === 'rtl';

		function hasKey( key ) {
			return tabs.some( function ( tab ) { return tab.getAttribute( 'data-npe-tab' ) === key; } );
		}

		function activate( requestedKey, moveFocus, updateUrl ) {
			var key = hasKey( requestedKey ) ? requestedKey : 'general';
			tabs.forEach( function ( tab ) {
				var active = tab.getAttribute( 'data-npe-tab' ) === key;
				tab.classList.toggle( 'nav-tab-active', active );
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
			} );
			if ( referer ) {
				var returnUrl = new URL( referer.value, window.location.href );
				returnUrl.searchParams.set( 'section', key );
				referer.value = returnUrl.pathname + returnUrl.search;
			}
			if ( updateUrl && window.history && window.history.replaceState ) {
				var url = new URL( window.location.href );
				url.searchParams.set( 'section', key );
				url.hash = 'npe-settings-' + key;
				window.history.replaceState( null, '', url.toString() );
			}
		}

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function ( event ) {
				// The normal URL remains a no-JavaScript fallback.
				if ( event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ) {
					return;
				}
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
					if ( rtl ) {
						direction *= -1;
					}
					next = ( index + direction + tabs.length ) % tabs.length;
				}
				activate( tabs[ next ].getAttribute( 'data-npe-tab' ), true, true );
			} );
		} );

		var params = new URLSearchParams( window.location.search );
		var hashKey = window.location.hash.indexOf( '#npe-settings-' ) === 0 ? window.location.hash.replace( '#npe-settings-', '' ) : '';
		activate( hashKey || params.get( 'section' ) || 'general', false, false );

		function currentValues() {
			return JSON.stringify( Array.from( new FormData( form ) ).filter( function ( entry ) {
				return entry[0].indexOf( 'npe_settings[' ) === 0;
			} ) );
		}

		var initialValues = currentValues();
		var cleanLabel = status.textContent;
		var dirty = false;
		var submitting = false;
		form.addEventListener( 'input', function () {
			dirty = currentValues() !== initialValues;
			status.textContent = dirty ? status.dataset.dirtyLabel : cleanLabel;
			status.classList.toggle( 'is-dirty', dirty );
		} );
		form.addEventListener( 'submit', function () { submitting = true; } );
		form.addEventListener( 'invalid', function ( event ) {
			var panel = event.target.closest( '[data-npe-section]' );
			// Only reveal the first invalid panel in this validation pass.
			if ( panel && ! form.querySelector( '.npe-settings-panel:not([hidden]) :invalid' ) ) {
				activate( panel.dataset.npeSection, false, true );
			}
		}, true );
		form.addEventListener( 'keydown', function ( event ) {
			var details = event.target.closest( '.npe-setting-help[open]' );
			if ( 'Escape' === event.key && details ) {
				details.open = false;
				details.querySelector( 'summary' ).focus();
			}
		} );
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( dirty && ! submitting ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );
	} );
}() );
