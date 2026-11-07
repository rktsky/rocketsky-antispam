/**
 * Previews the effect of the explicit privacy mode on optional admin controls.
 * Server-side settings enforce the same limits independently of this script.
 * @since 0.2.0
 */
( function () {
	'use strict';

	/**
	 * Enables optional fieldsets only while the owning form's mode is unchecked.
	 * @since 0.2.0
	 * @param {HTMLFormElement} form Owning settings form.
	 * @return {void}
	 */
	function update( form ) {
		const mode = form.querySelector( '[data-rk-privacy-mode]' );
		if ( ! mode ) {
			return;
		}
		form.querySelectorAll( '[data-rk-optional-processing]' ).forEach( ( group ) => {
			group.disabled = mode.checked;
			group.classList.toggle( 'is-locked', mode.checked );
		} );
		form.querySelectorAll( '[data-rk-mode-hint]' ).forEach( ( hint ) => {
			hint.hidden = ! mode.checked;
		} );
	}

	document.querySelectorAll( '.rktsky-antispam form' ).forEach( ( form ) => {
		const mode = form.querySelector( '[data-rk-privacy-mode]' );
		if ( mode ) {
			mode.addEventListener( 'change', () => update( form ) );
			update( form );
		}
	} );
}() );
