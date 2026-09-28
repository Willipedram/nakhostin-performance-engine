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
		var failed = Number( data.failed || 0 );
		var active = Number( data.pending || 0 ) + Number( data.running || 0 ) > 0;
		progress.value = data.progress || 0;
		progress.textContent = ( data.progress || 0 ) + '%';
		text( 'npe-dom-pending', data.pending || 0 );
		text( 'npe-dom-running', data.running || 0 );
		text( 'npe-dom-failed', failed );
		text( 'npe-dom-completed', data.completed || 0 );
		text( 'npe-dom-eta', duration( data.estimated_seconds || 0 ) );
		text( 'npe-dom-last-completed', data.last_completed_at ? new Date( data.last_completed_at * 1000 ).toLocaleString() : config.notRun );
		text( 'npe-dom-next-run', data.next_run_at ? new Date( data.next_run_at * 1000 ).toLocaleString() : config.notScheduled );
		text( 'npe-dom-last-error', data.last_error || ( failed > 0 ? config.unknownError : config.none ) );
		var queue = document.querySelector( '.npe-dom-queue' );
		if ( queue ) {
			queue.classList.remove( 'has-issues', 'is-running', 'is-healthy' );
			queue.classList.add( failed > 0 ? 'has-issues' : ( active ? 'is-running' : 'is-healthy' ) );
		}
		var state = document.querySelector( '#npe-dom-state .npe-dom-state-label' );
		if ( state ) {
			state.textContent = failed > 0 ? config.issues : ( active ? config.processing : config.healthy );
		}
		var value = document.querySelector( '.npe-progress-value' );
		if ( value ) {
			value.textContent = ( data.progress || 0 ) + '%';
		}
		text( 'npe-dom-worker-status', data.feature_enabled === false ? config.disabled : '' );
	}

	function poll() {
		var body = new URLSearchParams( { action: config.action, nonce: config.nonce } );
		fetch( config.url, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( function ( response ) {
				if ( response.success ) {
					update( response.data );
					return;
				}
				throw new Error( 'Invalid response' );
			} )
			.catch( function () { text( 'npe-dom-worker-status', config.pollError ); } );
	}

	window.setInterval( poll, 5000 );
	poll();
}() );
