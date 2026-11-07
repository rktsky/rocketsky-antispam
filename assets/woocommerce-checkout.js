/**
 * Connects WooCommerce's native checkout transports to local technical evidence.
 *
 * No customer, payment or address field is read. Cart updates and payment-provider
 * callbacks retain their native behaviour; only final checkout attempts carry proof.
 *
 * @since 0.3.0
 */
( function ( $ ) {
	'use strict';

	/** @type {WeakMap<HTMLFormElement, Object>} Pending native classic attempts. */
	const classicStates = new WeakMap();
	/** @type {WeakMap<HTMLFormElement, Object>} Pending existing-order payment attempts. */
	const payStates = new WeakMap();

	/**
	 * Returns the shared protocol bridge when its owning script is ready.
	 *
	 * @since 0.3.0
	 * @return {Object|undefined} Protocol methods, without any visitor identifier.
	 */
	function bridge() {
		return window.rktskyAntispamBridge;
	}

	/**
	 * Finds the descriptor belonging to this exact checkout/payment scope.
	 *
	 * @since 0.3.0
	 * @param {string} scope Fixed checkout or order-payment scope.
	 * @return {Element|undefined} Original local protocol container.
	 */
	function findControl( scope ) {
		return Array.from( document.querySelectorAll( '[data-rk-woocommerce-checkout] [data-rk-antispam]' ) ).find( ( control ) => {
			return ( control.dataset.form || control.dataset.rkAntispamForm ) === scope;
		} );
	}

	/**
	 * Copies only the token and dynamic trap into a fixed extension-data schema.
	 *
	 * @since 0.3.0
	 * @param {Element} control Owning protocol container.
	 * @return {Object} Bounded protection fields; no native checkout payload.
	 */
	function extension( control ) {
		const values = bridge().serialized( control );
		const trap = Object.keys( values ).find( ( key ) => /^rktsky_as_[a-f0-9]{20}$/.test( key ) );
		return {
			token: typeof values.rktsky_antispam_token === 'string' ? values.rktsky_antispam_token : '',
			trap: trap && typeof values[ trap ] === 'string' ? trap : '',
			value: trap && typeof values[ trap ] === 'string' ? values[ trap ].slice( 0, 4096 ) : ''
		};
	}

	/**
	 * Mirrors technical fields through WooCommerce's documented checkout data action.
	 *
	 * This runs outside checkout event observers and changes no native context state.
	 * The request middleware separately supplies the exact evidence used by that POST.
	 *
	 * @since 0.3.0
	 * @param {Object} proof Fixed local extension fields.
	 * @return {void}
	 */
	function syncExtension( proof ) {
		if ( window.wp && window.wp.data && window.wc && window.wc.wcBlocksData ) {
			const checkout = window.wp.data.dispatch( 'wc/store/checkout' );
			if ( checkout && typeof checkout.setExtensionData === 'function' ) {
				checkout.setExtensionData( 'rktsky-antispam', proof );
			}
		}
	}

	/**
	 * Resolves only the same-site Store API route understood by WordPress apiFetch.
	 *
	 * Both pretty REST paths and the native rest_route query transport are supported.
	 * An explicit URL to another origin must never receive this site's signed proof.
	 *
	 * @since 0.3.0
	 * @param {Object} options Native WordPress apiFetch options.
	 * @param {Element} control Local REST descriptor used to discover the REST base.
	 * @return {string} Scoped REST route, or empty for an unrelated request.
	 */
	function requestRoute( options, control ) {
		if ( typeof options.path === 'string' && options.path.startsWith( '/' ) && ! options.path.startsWith( '//' ) ) {
			return options.path.split( '?' )[ 0 ];
		}
		if ( typeof options.url !== 'string' ) {
			return '';
		}
		try {
			const target = new URL( options.url, window.location.href );
			const endpoint = new URL( control.dataset.endpoint || control.dataset.rkAntispamEndpoint, window.location.href );
			if ( target.origin !== window.location.origin || endpoint.origin !== target.origin ) {
				return '';
			}
			if ( endpoint.searchParams.has( 'rest_route' ) ) {
				return target.pathname === endpoint.pathname ? target.searchParams.get( 'rest_route' ) || '' : '';
			}
			const suffix = '/rktsky-antispam/v1/challenge';
			const base = endpoint.pathname.endsWith( suffix ) ? endpoint.pathname.slice( 0, -suffix.length ) : '';
			return base && target.pathname.startsWith( base + '/' ) ? target.pathname.slice( base.length ) : '';
		} catch ( error ) {
			return '';
		}
	}

	/**
	 * Supplies a fresh proof immediately before each final Store API network request.
	 *
	 * Supported apiFetch middleware is used instead of replacing fetch or WooCommerce
	 * code. Each retry obtains fresh evidence, including retries after native errors.
	 * Concurrent duplicate requests still compete for one atomic server-side claim.
	 *
	 * @since 0.3.0
	 * @param {Object} options Native request options, which remain independently owned.
	 * @param {Function} next Next registered apiFetch middleware.
	 * @return {Promise|*} Original transport result with only our extension added.
	 */
	async function storeRequest( options, next ) {
		const anyControl = document.querySelector( '[data-rk-woocommerce-checkout] [data-rk-antispam]' );
		if ( ! anyControl || ! bridge() || String( options.method || 'GET' ).toUpperCase() !== 'POST' ) {
			return next( options );
		}
		const route = requestRoute( options, anyControl );
		const match = route.match( /^\/wc\/store(?:\/v[1-9][0-9]*)?\/checkout(?:\/([0-9]+))?\/?$/i );
		if ( ! match ) {
			return next( options );
		}
		const control = findControl( match[ 1 ] ? 'pay_' + Number( match[ 1 ] ) : 'checkout' );
		if ( ! control ) {
			// The server rejects a missing descriptor; no gateway is silently exempted.
			return next( options );
		}
		await bridge().ensure( control );
		const proof = extension( control );
		bridge().consume( control );
		let data = options.data;
		if ( ! data && typeof options.body === 'string' ) {
			try {
				data = JSON.parse( options.body );
			} catch ( error ) {
				return next( options );
			}
		}
		if ( ! data || typeof data !== 'object' || Array.isArray( data ) ) {
			data = {};
		}
		const request = Object.assign( {}, options, {
			data: Object.assign( {}, data, { extensions: Object.assign( {}, data.extensions, { 'rktsky-antispam': proof } ) } )
		} );
		delete request.body;
		return next( request );
	}

	/**
	 * Defers a classic checkout only until fresh evidence exists, then resumes natively.
	 *
	 * A failed challenge is passed to native server validation with empty evidence so
	 * WooCommerce displays its normal retry error instead of swallowing the attempt.
	 * Other gateway observers run only on the resumed native checkout attempt.
	 *
	 * @since 0.3.0
	 * @param {jQuery.Event} event Native checkout_place_order event.
	 * @return {boolean} Whether native final checkout may proceed now.
	 */
	function classicCheckout( event ) {
		const form = event.currentTarget;
		const control = form.querySelector( '[data-rk-woocommerce-checkout="classic"] [data-rk-antispam]' );
		if ( ! control || ! bridge() ) {
			return true;
		}
		const state = classicStates.get( form );
		if ( state.resume ) {
			state.resume = false;
			bridge().consume( control );
			return true;
		}
		event.stopImmediatePropagation();
		if ( ! state.waiting ) {
			state.waiting = true;
			bridge().ensure( control ).then( ( ready ) => {
				if ( ! ready ) {
					control.querySelector( '[name="rktsky_antispam_token"]' ).value = '';
				}
				window.setTimeout( () => {
					state.waiting = false;
					if ( form.isConnected ) {
						state.resume = true;
						try {
							$( form ).trigger( 'submit' );
						} finally {
							state.resume = false;
						}
					}
				}, 0 );
			} );
		}
		return false;
	}

	/**
	 * Installs classic checkout hooks on native forms without changing provider scripts.
	 *
	 * @since 0.3.0
	 * @return {void}
	 */
	function initializeClassic() {
		$( 'form.checkout' ).each( function () {
			if ( ! classicStates.has( this ) ) {
				classicStates.set( this, { waiting: false, resume: false } );
				$( this ).on( 'checkout_place_order.rktskyAntispam', classicCheckout );
			}
		} );
	}

	/**
	 * Resumes a native order-payment form through ordinary browser validation.
	 *
	 * @since 0.3.0
	 * @param {SubmitEvent} event Original existing-order payment attempt.
	 * @return {void}
	 */
	function paySubmit( event ) {
		const form = event.target;
		if ( ! ( form instanceof HTMLFormElement ) || ! bridge() ) {
			return;
		}
		const control = form.querySelector( '[data-rk-woocommerce-checkout="pay"] [data-rk-antispam]' );
		if ( ! control ) {
			return;
		}
		const state = payStates.get( form ) || { waiting: false, resume: false };
		payStates.set( form, state );
		if ( state.resume ) {
			state.resume = false;
			bridge().consume( control );
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		if ( state.waiting ) {
			return;
		}
		state.waiting = true;
		const submitter = event.submitter;
		bridge().ensure( control ).then( ( ready ) => {
			if ( ! ready ) {
				control.querySelector( '[name="rktsky_antispam_token"]' ).value = '';
			}
			window.setTimeout( () => {
				state.waiting = false;
				if ( form.isConnected ) {
					state.resume = true;
					try {
						HTMLFormElement.prototype.requestSubmit.call( form, submitter && submitter.form === form ? submitter : undefined );
					} finally {
						state.resume = false;
					}
				}
			}, 0 );
		} );
	}

	initializeClassic();
	$( initializeClassic );
	$( document.body ).on( 'updated_checkout.rktskyAntispam', initializeClassic );
	window.addEventListener( 'submit', paySubmit, true );
	if ( window.wp && window.wp.apiFetch ) {
		window.wp.apiFetch.use( storeRequest );
		const control = findControl( 'checkout' );
		if ( control && bridge() ) {
			bridge().ensure( control ).then( () => syncExtension( extension( control ) ) );
		}
	}
}( window.jQuery ) );
