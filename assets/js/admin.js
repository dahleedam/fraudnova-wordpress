/**
 * FraudNova admin: connection test.
 */
( function () {
	'use strict';

	function init() {
		var button = document.getElementById( 'fraudnova-test-connection' );
		var result = document.getElementById( 'fraudnova-test-result' );
		var config = window.fraudnovaAdmin;

		if ( ! button || ! result || ! config ) {
			return;
		}

		function show( message, ok ) {
			result.textContent = message;
			result.classList.remove( 'is-ok', 'is-error' );
			result.classList.add( ok ? 'is-ok' : 'is-error' );
		}

		button.addEventListener( 'click', function () {
			var body = new FormData();
			body.append( 'action', 'fraudnova_test_connection' );
			body.append( 'nonce', config.nonce );

			button.disabled = true;
			result.classList.remove( 'is-ok', 'is-error' );
			result.textContent = config.i18n.testing;

			fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					var message = data && data.data && data.data.message ? data.data.message : config.i18n.failed;
					show( message, !! ( data && data.success ) );
				} )
				.catch( function () {
					show( config.i18n.failed, false );
				} )
				.then( function () {
					button.disabled = false;
				} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
