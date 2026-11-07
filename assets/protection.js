/**
 * Obtains local challenges without cookies, storage or fingerprinting.
 *
 * Provider adapters own validation and delivery. A consumed challenge is replaced
 * before a retry; the challenge request carries no account or form content.
 *
 * @since 0.1.0
 */
( function () {
	'use strict';

	/** @type {WeakMap<Element, Object>} Protection state belongs to DOM controls. */
	const states = new WeakMap();
	/** @type {string} Persistent descriptors survive native form-child replacement. */
	const hosts = 'form[data-rk-antispam-provider]';

	/**
	 * Resolves a control, restoring our hidden container after native rerenders.
	 *
	 * @since 0.3.0
	 * @since 0.3.1 Copies optional display-only target and page metadata.
	 * @param {Element} element Existing control or native host form.
	 * @return {Element|null} Inside-form control with a signed server descriptor.
	 */
	function resolve( element ) {
		if ( ! element || ! element.matches ) {
			return null;
		}
		let control = element.matches( '[data-rk-antispam]' ) ? element : element.querySelector( '[data-rk-antispam]' );
		if ( ! control && element.matches( hosts ) ) {
			control = document.createElement( 'span' );
			control.hidden = true;
			control.setAttribute( 'data-rk-antispam', '' );
			[ 'provider', 'form', 'intent', 'endpoint' ].forEach( ( key ) => {
				control.dataset[ key ] = element.getAttribute( 'data-rk-antispam-' + key );
			} );
			const token = document.createElement( 'input' );
			token.type = 'hidden';
			token.name = 'rktsky_antispam_token';
			token.autocomplete = 'off';
			control.append( token );
			element.append( control );
		}
		if ( control ) {
			// Display metadata restores native error selection before the main query
			// exists. It carries no token authority or submitted form content.
			const host = control.closest( hosts );
			if ( host ) {
				[ 'target', 'page' ].forEach( ( key ) => {
					const value = host.getAttribute( 'data-rk-antispam-' + key );
					if ( value === null || value.length > 256 ) {
						return;
					}
					const name = 'rktsky_antispam_' + key;
					let input = control.querySelector( '[name="' + name + '"]' );
					if ( ! input ) {
						input = document.createElement( 'input' );
						input.type = 'hidden';
						input.name = name;
						control.append( input );
					}
					input.value = value;
				} );
			}
		}
		if ( control && ! states.has( control ) ) {
			states.set( control, { ready: false, used: false, issued: 0, pending: null, submitting: false, trap: '', attempted: false } );
		}
		return control;
	}

	/**
	 * Refreshes protection only; arbitrary endpoint origins are refused.
	 *
	 * @since 0.1.0
	 * @param {Element} element Control or descriptor-bearing native form.
	 * @return {Promise<boolean>} Whether a fresh challenge is ready.
	 */
	async function refresh( element ) {
		const control = resolve( element );
		if ( ! control ) {
			return false;
		}
		const state = states.get( control );
		if ( state.pending ) {
			return state.pending;
		}
		state.ready = false;
		state.attempted = true;
		state.pending = ( async function () {
			// Set pending before even an immediate origin rejection reaches finally.
			await Promise.resolve();
			const abort = new AbortController();
			const timeout = window.setTimeout( () => abort.abort(), 10000 );
			try {
				const endpoint = new URL( control.dataset.endpoint, window.location.href );
				if ( endpoint.origin !== window.location.origin ) {
					return false;
				}
				const response = await fetch( endpoint.href, {
					method: 'POST', credentials: 'omit', cache: 'no-store', referrerPolicy: 'no-referrer',
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
				state.trap = proof.trap;
				// Each newly issued proof must satisfy the configured timing bound.
				const minimum = Number.isInteger( proof.minimum ) ? Math.max( 0, Math.min( 10, proof.minimum ) ) : 0;
				if ( minimum > 0 ) {
					await new Promise( ( done ) => window.setTimeout( done, minimum * 1000 ) );
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
	 * Ensures unused evidence before a native or provider-owned transport sends.
	 *
	 * @since 0.3.0
	 * @param {Element} element Control or owning form.
	 * @return {Promise<boolean>} Whether usable evidence is available.
	 */
	function ensure( element ) {
		const control = resolve( element );
		const state = control && states.get( control );
		return state && state.ready && ! state.used && Date.now() - state.issued < 6900000 ? Promise.resolve( true ) : refresh( element );
	}

	/**
	 * Marks evidence used immediately before its native transport starts.
	 *
	 * @since 0.3.0
	 * @param {Element} element Control or owning form.
	 * @return {void}
	 */
	function consume( element ) {
		const control = resolve( element );
		if ( control ) {
			states.get( control ).used = true;
		}
	}

	/**
	 * Exposes only our controls to providers using a structured request body.
	 *
	 * @since 0.3.0
	 * @param {Element} element Control or owning form.
	 * @return {Object} Token and its exact dynamic trap, with no provider values.
	 */
	function serialized( element ) {
		const control = resolve( element );
		const state = control && states.get( control );
		if ( ! state || ! state.ready || ! state.trap ) {
			return {};
		}
		const trap = control.querySelector( '[data-rk-trap]' );
		return { rktsky_antispam_token: control.querySelector( '[name="rktsky_antispam_token"]' ).value, [ state.trap ]: trap ? trap.value : null };
	}

	/**
	 * Initializes controls and persistent hosts after native AJAX replacement.
	 *
	 * @since 0.1.0
	 * @param {ParentNode} root Inserted subtree.
	 * @return {void}
	 */
	function initialize( root ) {
		const controls = Array.from( root.querySelectorAll( '[data-rk-antispam], ' + hosts ) );
		if ( root.matches && root.matches( '[data-rk-antispam], ' + hosts ) ) {
			controls.unshift( root );
		}
		controls.forEach( ( element ) => {
			const control = resolve( element );
			const state = control && states.get( control );
			if ( state && ! state.attempted ) {
				refresh( control );
			}
		} );
	}

	/**
	 * Resumes native validation after an async refresh and preserves the submitter.
	 *
	 * Failed issuance proceeds with empty evidence so native errors stay visible.
	 * WooCommerce owns a separate gate at its final checkout transport.
	 *
	 * @since 0.1.0
	 * @param {SubmitEvent} event Native form submission.
	 * @return {void}
	 */
	function submit( event ) {
		const form = event.target;
		if ( ! ( form instanceof HTMLFormElement ) || form.querySelector( '[data-rk-woocommerce-checkout]' ) ) {
			return;
		}
		const control = resolve( form );
		if ( ! control ) {
			return;
		}
		const state = states.get( control );
		if ( state.submitting ) {
			state.submitting = false;
			consume( control );
			return;
		}
		if ( state.waiting ) {
			event.preventDefault();
			event.stopImmediatePropagation();
			return;
		}
		if ( state.ready && ! state.used && Date.now() - state.issued < 6900000 ) {
			consume( control );
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		state.waiting = true;
		form.setAttribute( 'aria-busy', 'true' );
		const submitter = event.submitter;
		ensure( control ).then( ( ready ) => {
			// A new task avoids reentering a native submit still being dispatched
			// when issuance failed immediately, for example on an unsafe origin.
			window.setTimeout( () => {
				state.waiting = false;
				form.removeAttribute( 'aria-busy' );
				if ( ! form.isConnected ) {
					return;
				}
				if ( ! ready ) {
					control.querySelector( '[name="rktsky_antispam_token"]' ).value = '';
				}
				state.submitting = true;
				try {
					HTMLFormElement.prototype.requestSubmit.call( form, submitter && submitter.form === form ? submitter : undefined );
				} finally {
					// Native constraint validation may prevent a submit event entirely.
					state.submitting = false;
				}
			}, 0 );
		} );
	}

	// Small explicit transport API; integrations do not patch global fetch or XHR.
	window.rktskyAntispamBridge = Object.freeze( { ensure, consume, refresh, serialized } );
	window.addEventListener( 'submit', submit, true );
	initialize( document );
	new MutationObserver( ( changes ) => {
		changes.forEach( ( change ) => {
			change.addedNodes.forEach( ( node ) => {
				if ( node.nodeType === Node.ELEMENT_NODE ) {
					initialize( node );
				}
			} );
			const host = change.target.closest && change.target.closest( hosts );
			if ( host ) {
				initialize( host );
			}
		} );
	} ).observe( document.documentElement, { childList: true, subtree: true } );
}() );
