( function () {
	'use strict';

	if ( typeof window.npeDomProgress !== 'object' ) {
		return;
	}

	var config = window.npeDomProgress;
	var progress = document.getElementById( 'npe-dom-progress' );
	if ( ! progress ) {
		return;
	}

	function text( id, value ) {
		var element = document.getElementById( id );
		if ( element ) {
			element.textContent = String( value );
		}
	}

	function duration( seconds ) {
		if ( seconds <= 0 ) {
			return config.completed;
		}
		if ( seconds < 60 ) {
			return config.seconds.replace( '%d', seconds );
		}
		return config.minutes.replace( '%d', Math.ceil( seconds / 60 ) );
	}

	function update( data ) {
		progress.value = data.progress || 0;
		progress.textContent = ( data.progress || 0 ) + '%';
		text( 'npe-dom-pending', data.pending || 0 );
		text( 'npe-dom-running', data.running || 0 );
		text( 'npe-dom-failed', data.failed || 0 );
		text( 'npe-dom-completed', data.completed || 0 );
		text( 'npe-dom-eta', duration( data.estimated_seconds || 0 ) );
		text( 'npe-dom-last-completed', data.last_completed_at ? new Date( data.last_completed_at * 1000 ).toLocaleString() : config.notRun );
		var value = document.querySelector( '.npe-progress-value' );
		if ( value ) {
			value.textContent = ( data.progress || 0 ) + '%';
		}
	}

	function poll() {
		var body = new URLSearchParams( { action: config.action, nonce: config.nonce } );
		fetch( config.url, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( response ) { if ( response.success ) { update( response.data ); } } )
			.catch( function () {} );
	}

	window.setInterval( poll, 5000 );
	poll();
}() );
