/**
 * Obtains local per-render challenges without cookies, storage or fingerprinting.
 *
 * Native providers retain ownership of validation and delivery. Every browser
 * attempt gets fresh evidence after the previous attempt, including AJAX retries.
 *
 * @since 0.1.0
 */
( function () {
	'use strict';

	/** @type {WeakMap<Element, Object>} State belongs to actual controls, never visitors. */
	const states = new WeakMap();

	/**
	 * Refreshes only protection fields; form content never enters this request.
	 *
	 * @since 0.1.0
	 * @param {Element} control Owning inside-form control container.
	 * @return {Promise<boolean>} Whether a fresh same-site challenge is ready.
	 */
	async function refresh( control ) {
		const state = states.get( control );
		if ( state.pending ) {
			return state.pending;
		}
		state.ready = false;
		state.pending = ( async function () {
			const abort = new AbortController();
			const timeout = window.setTimeout( () => abort.abort(), 10000 );
			try {
				const endpoint = new URL( control.dataset.endpoint, window.location.href );
				// A rewritten or misconfigured REST origin must not receive browser data.
				if ( endpoint.origin !== window.location.origin ) {
					return false;
				}
				const response = await fetch( endpoint.href, {
					method: 'POST',
					credentials: 'omit',
					cache: 'no-store',
					referrerPolicy: 'no-referrer',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( { provider: control.dataset.provider, form: control.dataset.form, intent: control.dataset.intent } ),
					signal: abort.signal
				} );
				if ( ! response.ok ) {
					return false;
				}
				const proof = await response.json();
				if ( typeof proof.token !== 'string' || proof.token.length > 400 || ! /^rktsky_as_[a-f0-9]{20}$/.test( proof.trap ) ) {
					return false;
				}
				control.querySelectorAll( '[data-rk-trap]' ).forEach( ( input ) => input.remove() );
				const trap = document.createElement( 'input' );
				trap.type = 'text';
				trap.name = proof.trap;
				trap.value = '';
				trap.tabIndex = -1;
				trap.autocomplete = 'off';
				trap.setAttribute( 'aria-hidden', 'true' );
				trap.setAttribute( 'data-rk-trap', '' );
				control.append( trap );
				control.querySelector( '[name="rktsky_antispam_token"]' ).value = proof.token;
				// Retried submissions must respect the age of their newly issued token.
				const minimum = Number.isInteger( proof.minimum ) ? Math.max( 0, Math.min( 10, proof.minimum ) ) : 0;
				if ( minimum > 0 ) {
					await new Promise( ( resolve ) => window.setTimeout( resolve, minimum * 1000 ) );
				}
				state.ready = true;
				state.used = false;
				state.issued = Date.now();
				return true;
			} catch ( error ) {
				return false;
			} finally {
				window.clearTimeout( timeout );
				state.pending = null;
			}
		}() );
		return state.pending;
	}

	/**
	 * Initializes newly rendered controls, including provider AJAX replacements.
	 *
	 * @since 0.1.0
	 * @param {ParentNode} root Newly inserted document subtree.
	 * @return {void}
	 */
	function initialize( root ) {
		const controls = Array.from( root.querySelectorAll( '[data-rk-antispam]' ) );
		if ( root.matches && root.matches( '[data-rk-antispam]' ) ) {
			controls.unshift( root );
		}
		controls.forEach( ( control ) => {
			if ( ! states.has( control ) ) {
				states.set( control, { ready: false, used: false, issued: 0, pending: null, submitting: false } );
				refresh( control );
			}
		} );
	}

	/**
	 * Resumes an intercepted attempt through native validation and provider handlers.
	 *
	 * A failed refresh is submitted once with empty evidence so the native provider
 	 * displays the translated server error rather than silently swallowing a click.
	 *
	 * @since 0.1.0
	 * @param {SubmitEvent} event Native form submission.
	 * @return {void}
	 */
	function submit( event ) {
		const form = event.target;
		if ( ! ( form instanceof HTMLFormElement ) ) {
			return;
		}
		const control = form.querySelector( '[data-rk-antispam]' );
		if ( ! control || ! states.has( control ) ) {
			return;
		}
		const state = states.get( control );
		if ( state.submitting ) {
			state.submitting = false;
			state.used = true;
			return;
		}
		if ( state.ready && ! state.used && Date.now() - state.issued < 6900000 ) {
			state.used = true;
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		const submitter = event.submitter;
		if ( state.waiting ) {
			return;
		}
		state.waiting = true;
		form.setAttribute( 'aria-busy', 'true' );
		refresh( control ).then( ( ready ) => {
			state.waiting = false;
			form.removeAttribute( 'aria-busy' );
			if ( ! form.isConnected ) {
				return;
			}
			if ( ! ready ) {
				control.querySelector( '[name="rktsky_antispam_token"]' ).value = '';
			}
			state.submitting = true;
			HTMLFormElement.prototype.requestSubmit.call( form, submitter && submitter.form === form ? submitter : undefined );
		} );
	}

	// Capture before provider listeners, while preserving their ordinary submit path.
	window.addEventListener( 'submit', submit, true );
	initialize( document );
	new MutationObserver( ( changes ) => {
		changes.forEach( ( change ) => {
			change.addedNodes.forEach( ( node ) => {
				if ( node.nodeType === Node.ELEMENT_NODE ) {
					initialize( node );
				}
			} );
		} );
	} ).observe( document.documentElement, { childList: true, subtree: true } );
}() );
