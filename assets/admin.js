/**
 * Previews privacy locks and guards unsaved changes on task-specific forms.
 * Server-side settings enforce the same limits independently of this script.
 * @since 0.2.0
 * @since 0.4.0 Warns before leaving an actually changed configuration form.
 */
( function () {
	'use strict';
	/** @type {Map<HTMLFormElement, string>} Initial successful controls; never persisted or transmitted here. */
	const snapshots = new Map();
	/** @type {boolean} Native submit navigation should proceed without a leave warning. */
	let submitting = false;

	/**
	 * Compares native successful controls, including disabled-field semantics.
	 *
	 * No field contents leave this page or enter browser storage. Reading only at
	 * navigation avoids repeated serialization while an administrator is typing.
	 *
	 * @since 0.4.0
	 * @param {HTMLFormElement} form Owned configuration form.
	 * @return {string} In-memory comparison value in stable DOM order.
	 */
	function snapshot( form ) {
		return JSON.stringify( Array.from( new FormData( form ).entries() ) );
	}

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
		if ( form.hasAttribute( 'data-rk-settings-form' ) ) {
			snapshots.set( form, snapshot( form ) );
		}
		// Saving this configuration may navigate; other actions must not hide its edits.
		form.addEventListener( 'submit', () => {
			submitting = snapshots.has( form );
		} );
	} );
	if ( snapshots.size === 0 ) {
		return;
	}
	window.addEventListener( 'beforeunload', ( event ) => {
		if ( submitting ) {
			return;
		}
		for ( const [ form, initial ] of snapshots ) {
			if ( snapshot( form ) !== initial ) {
				event.preventDefault();
				event.returnValue = '';
				return;
			}
		}
	} );
}() );
