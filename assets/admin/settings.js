( function () {
	'use strict';

	var tabs = Array.prototype.slice.call( document.querySelectorAll( '.npe-settings-tabs [data-npe-tab]' ) );
	var panels = Array.prototype.slice.call( document.querySelectorAll( '.npe-settings-panel[data-npe-section]' ) );
	if ( ! tabs.length || ! panels.length ) {
		return;
	}

	function activate( key, moveFocus ) {
		var matched = false;
		tabs.forEach( function ( tab ) {
			var active = tab.getAttribute( 'data-npe-tab' ) === key;
			tab.classList.toggle( 'nav-tab-active', active );
			tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			tab.setAttribute( 'tabindex', active ? '0' : '-1' );
			if ( active ) {
				tab.setAttribute( 'aria-current', 'page' );
				matched = true;
				if ( moveFocus ) {
					tab.focus();
				}
			} else {
				tab.removeAttribute( 'aria-current' );
			}
		} );
		if ( ! matched ) {
			key = 'general';
			return activate( key, moveFocus );
		}
		panels.forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-npe-section' ) !== key;
		} );
		window.history.replaceState( null, '', '#npe-settings-' + key );
	}

	tabs.forEach( function ( tab, index ) {
		tab.setAttribute( 'role', 'tab' );
		tab.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			activate( tab.getAttribute( 'data-npe-tab' ), false );
		} );
		tab.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowLeft' !== event.key && 'ArrowRight' !== event.key ) {
				return;
			}
			event.preventDefault();
			var direction = 'ArrowRight' === event.key ? 1 : -1;
			var next = ( index + direction + tabs.length ) % tabs.length;
			activate( tabs[ next ].getAttribute( 'data-npe-tab' ), true );
		} );
	} );

	var requested = window.location.hash.replace( '#npe-settings-', '' );
	activate( requested || 'general', false );
}() );
