( function () {
	'use strict';

	var navigation = document.querySelector( '.npe-shell-sidebar' );
	if ( ! navigation || ! window.matchMedia ) {
		return;
	}

	var compact = window.matchMedia( '(max-width: 960px)' );
	function sync( event ) {
		navigation.open = ! event.matches;
	}

	sync( compact );
	if ( compact.addEventListener ) {
		compact.addEventListener( 'change', sync );
	}

	var shell = navigation.closest( '.npe-shell' );
	if ( ! window.ResizeObserver ) {
		return;
	}
	var observer = new ResizeObserver( function ( entries ) {
		entries.forEach( function ( entry ) {
			var scroller = entry.target;
			if ( scroller.scrollWidth > scroller.clientWidth + 1 ) {
				scroller.tabIndex = 0;
				scroller.setAttribute( 'role', 'region' );
				scroller.setAttribute( 'aria-label', scroller.dataset.label );
			} else {
				scroller.removeAttribute( 'tabindex' );
				scroller.removeAttribute( 'role' );
				scroller.removeAttribute( 'aria-label' );
			}
		} );
	} );
	shell.querySelectorAll( '.npe-shell-content table.widefat' ).forEach( function ( table ) {
		var parent = table.parentElement;
		var heading = table.previousElementSibling;
		var scroller = document.createElement( 'div' );
		scroller.className = 'npe-table-scroll';
		scroller.dataset.label = ( table.caption || ( heading && /^H[2-6]$/.test( heading.tagName ) && heading ) || {} ).textContent || shell.dataset.tableLabel;
		parent.insertBefore( scroller, table );
		scroller.appendChild( table );
		observer.observe( scroller );
	} );
}() );
