/*!
 * IntentTarget Pro — Front-End UI Runtime
 *
 * Cache-safe hydration layer. The cached HTML contains only empty shells with no per-visitor
 * data. On page load this script fetches a fresh per-visitor payload from the REST endpoint
 * intenttarget/v1/bootstrap (Cache-Control: no-store) and hydrates the slide-in popup, the
 * My Account dashboard recommendation, the preferences nonce, and the Pro ROI click tracker.
 *
 * Designed to survive WP Rocket / LiteSpeed / Autoptimize / Cloudflare Rocket Loader: the only
 * inline data this file relies on (restUrl, ajaxUrl) is identical for every visitor and therefore
 * safe to cache. All nonces and user-specific values come from the live REST call.
 */
( function() {
	'use strict';

	if ( typeof window === 'undefined' || ! window.itpFrontendBootstrap ) {
		return;
	}

	var BOOT = window.itpFrontendBootstrap;
	if ( ! BOOT.restUrl || ! BOOT.ajaxUrl ) {
		return;
	}

	var REVEAL_DELAY_MS    = 1500;
	var SHOWN_COOKIE_MAX   = 86400;       // 1 day — soft suppression after a manual dismiss
	var INTERACT_COOKIE_MAX = 1209600;    // 14 days — long suppression after a real click

	var state = {
		payload: null,
		popupHydrated: false,
		dashHydrated: false,
		prefsHydrated: false
	};

	// =====================================================================
	// Utility helpers
	// =====================================================================
	function readCookies() {
		return ( typeof document !== 'undefined' && document.cookie ) ? document.cookie : '';
	}
	function hasSuppressionCookie() {
		var c = readCookies();
		return c.indexOf( 'itp_popup_shown=' ) !== -1 || c.indexOf( 'itp_popup_interacted=' ) !== -1;
	}
	function setCookie( name, value, maxAgeSeconds ) {
		document.cookie = name + '=' + encodeURIComponent( value ) +
			'; max-age=' + maxAgeSeconds +
			'; path=/; samesite=strict' +
			( window.location.protocol === 'https:' ? '; secure' : '' );
	}
	function escapeHtml( str ) {
		return String( str === null || typeof str === 'undefined' ? '' : str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}
	function safeUrl( url ) {
		var raw = String( url || '' );
		if ( /^javascript:/i.test( raw ) ) { return '#'; }
		return raw;
	}
	function postForm( url, fields, opts ) {
		opts = opts || {};
		var body = new URLSearchParams();
		Object.keys( fields ).forEach( function( key ) {
			if ( typeof fields[ key ] !== 'undefined' && fields[ key ] !== null ) {
				body.set( key, String( fields[ key ] ) );
			}
		} );

		var init = {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		};
		if ( opts.keepalive ) {
			init.keepalive = true;
		}

		try {
			return fetch( url, init );
		} catch ( e ) {
			if ( opts.keepalive && navigator && typeof navigator.sendBeacon === 'function' ) {
				try { navigator.sendBeacon( url, body.toString() ); } catch ( ignored ) {}
			}
			return Promise.reject( e );
		}
	}

	// =====================================================================
	// Bootstrap fetch — single network call, response is uncacheable
	// =====================================================================
	function fetchBootstrap() {
		return fetch( BOOT.restUrl, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { 'Accept': 'application/json' },
			cache: 'no-store'
		} ).then( function( response ) {
			if ( ! response.ok ) {
				throw new Error( 'Bootstrap fetch failed: ' + response.status );
			}
			return response.json();
		} );
	}

	// =====================================================================
	// Slide-in popup hydration
	// =====================================================================
	function buildPopupMarkup( payload ) {
		var popup    = payload.popup || {};
		var nonces   = payload.nonces || {};
		var branding = payload.show_branding ? (
			'<div class="itp-branding-link">' +
				'<a href="https://intenttargetpro.com" target="_blank" rel="noopener noreferrer">Powered by IntentTargetPro</a>' +
			'</div>'
		) : '';

		var inner = '<button type="button" class="itp-popup-close" aria-label="Close">&times;</button>';

		if ( popup.is_search_page ) {
			inner += '<span class="itp-popup-label">Search Feedback</span>';
			inner += '<h4 class="itp-popup-title">Did you find what you were looking for?</h4>';
			inner += '<form class="itp-search-feedback-form" method="post" action="" novalidate>';
			inner +=   '<input type="hidden" name="search_query" value="' + escapeHtml( popup.search_query || '' ) + '">';
			inner +=   '<input type="hidden" name="security" value="' + escapeHtml( nonces.search_feedback || '' ) + '">';
			inner +=   '<div class="itp-popup-btn-row">';
			inner +=     '<button type="submit" name="found_result" value="yes" class="itp-popup-btn">Yes</button>';
			inner +=     '<button type="button" class="itp-popup-btn itp-popup-btn-secondary" data-itp-feedback-toggle="1">No</button>';
			inner +=   '</div>';
			inner +=   '<div class="itp-popup-feedback-extra" style="display:none;">';
			inner +=     '<label class="itp-popup-feedback-label" for="itp-feedback-notes">Tell us what you were looking for (optional)</label>';
			inner +=     '<textarea id="itp-feedback-notes" name="feedback_notes" rows="3"></textarea>';
			inner +=     '<button type="submit" name="found_result" value="no" class="itp-popup-btn" style="margin-top:8px;">Submit Details</button>';
			inner +=   '</div>';
			inner += '</form>';
		} else if ( popup.advert ) {
			var advert = popup.advert;
			inner += '<span class="itp-popup-label">Recommended</span>';
			if ( popup.greeting ) {
				inner += '<p class="itp-popup-greet">Hi, ' + escapeHtml( popup.greeting ) + '</p>';
			}
			var intentAttr = escapeHtml( advert.intent || '' );
			var urlAttr    = escapeHtml( safeUrl( advert.url ) );
			inner += '<a href="' + urlAttr + '" class="itp-popup-title" data-itp-track="1" data-itp-intent="' + intentAttr + '" data-itp-source="slidein">' + escapeHtml( advert.title ) + '</a>';
			inner += '<p class="itp-popup-desc">' + escapeHtml( advert.desc ) + '</p>';
			inner += '<a href="' + urlAttr + '" class="itp-popup-btn itp-button" data-itp-track="1" data-itp-intent="' + intentAttr + '" data-itp-source="slidein">' + escapeHtml( advert.btn ) + '</a>';
		} else {
			return null; // Nothing relevant to show
		}

		inner += branding;
		return inner;
	}

	function bindPopupBehaviour( popup, payload ) {
		var closeBtn = popup.querySelector( '.itp-popup-close' );
		var actionBtn = popup.querySelector( 'a.itp-button' );
		var feedbackToggle = popup.querySelector( '[data-itp-feedback-toggle]' );
		var feedbackForm = popup.querySelector( '.itp-search-feedback-form' );

		function hide() {
			popup.classList.remove( 'itp-popup-visible' );
			window.setTimeout( function() { popup.classList.add( 'itp-popup-hidden' ); }, 450 );
		}
		function reveal() {
			popup.classList.remove( 'itp-popup-hidden' );
			popup.removeAttribute( 'hidden' );
			void popup.offsetWidth; // force reflow so the transition triggers
			popup.classList.add( 'itp-popup-visible' );
		}

		if ( ! hasSuppressionCookie() ) {
			window.setTimeout( reveal, REVEAL_DELAY_MS );
		}

		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function( ev ) {
				ev.preventDefault();
				setCookie( 'itp_popup_shown', 'true', SHOWN_COOKIE_MAX );
				hide();
			} );
		}

		if ( actionBtn ) {
			actionBtn.addEventListener( 'click', function() {
				var href = actionBtn.getAttribute( 'href' );
				setCookie( 'itp_popup_interacted', 'true', INTERACT_COOKIE_MAX );

				var fields = {
					action: 'itp_mark_high_engagement',
					nonce: ( payload.nonces && payload.nonces.high_engagement ) || ''
				};
				// Fire and forget — keepalive lets the request complete after navigation.
				try {
					postForm( BOOT.ajaxUrl, fields, { keepalive: true } );
				} catch ( e ) {}
				// Do not preventDefault; we let the browser navigate naturally.
				if ( ! href ) { return; }
			} );
		}

		if ( feedbackToggle ) {
			feedbackToggle.addEventListener( 'click', function( ev ) {
				ev.preventDefault();
				var extra = popup.querySelector( '.itp-popup-feedback-extra' );
				if ( extra ) { extra.style.display = 'block'; }
			} );
		}

		if ( feedbackForm ) {
			feedbackForm.addEventListener( 'submit', function( ev ) {
				ev.preventDefault();
				var formData = new FormData( feedbackForm );
				var submitter = ev.submitter || feedbackForm.querySelector( 'button[type="submit"]' );
				var foundResult = submitter && submitter.value ? submitter.value : 'yes';
				var fields = {
					action: 'itp_submit_search_feedback',
					security: formData.get( 'security' ) || '',
					search_query: formData.get( 'search_query' ) || '',
					found_result: foundResult,
					feedback_notes: formData.get( 'feedback_notes' ) || ''
				};

				postForm( BOOT.ajaxUrl, fields ).then( function() {
					setCookie( 'itp_popup_shown', 'true', INTERACT_COOKIE_MAX );
					feedbackForm.innerHTML = '<p class="itp-popup-thanks">Thank you for your feedback!</p>';
					window.setTimeout( hide, 2000 );
				} ).catch( function() {
					feedbackForm.innerHTML = '<p class="itp-popup-thanks" style="color:#c62828;">Sorry, your feedback could not be saved. Please try again.</p>';
				} );
			} );
		}
	}

	function hydratePopup( payload ) {
		if ( state.popupHydrated ) { return; }
		var popup = document.getElementById( 'intent-slidein' );
		if ( ! popup ) { return; }

		var markup = buildPopupMarkup( payload );
		if ( markup === null ) {
			// Nothing to show — leave the empty shell hidden.
			return;
		}
		popup.innerHTML = markup;
		state.popupHydrated = true;
		bindPopupBehaviour( popup, payload );
	}

	// =====================================================================
	// My Account dashboard advert hydration
	// =====================================================================
	function buildDashboardAdvertMarkup( advert, payload, options ) {
		if ( ! advert || ! advert.title ) { return ''; }
		var intentAttr = escapeHtml( advert.intent || '' );
		var urlAttr    = escapeHtml( safeUrl( advert.url ) );
		var badge      = ( options && options.show_seasonal_badge )
			? '<span class="itp-dashboard-badge" style="background:#e67e22;color:#ffffff;">Seasonal Priority</span>'
			: '';
		var branding   = payload.show_branding ? (
			'<div class="itp-branding-link">' +
				'<a href="https://intenttargetpro.com" target="_blank" rel="noopener noreferrer">Powered by IntentTargetPro</a>' +
			'</div>'
		) : '';

		var html = '<div class="itp-dashboard-offer">';
		html += '<span class="itp-dashboard-label">Recommended</span>';
		html += '<h3 class="itp-dashboard-title">' + badge + escapeHtml( advert.title ) + '</h3>';
		html += '<p class="itp-dashboard-desc">' + escapeHtml( advert.desc ) + '</p>';
		html += '<a href="' + urlAttr + '" class="itp-dashboard-cta" data-itp-track="1" data-itp-intent="' + intentAttr + '" data-itp-source="account_dashboard">' + escapeHtml( advert.btn ) + '</a>';
		html += branding;
		html += '</div>';
		return html;
	}

	function hydrateDashboardAdverts( payload ) {
		if ( state.dashHydrated ) { return; }
		var shell = document.getElementById( 'itp-dashboard-recommendations' );
		if ( ! shell ) { return; }

		var dashboard = payload.dashboard;
		if ( ! dashboard || ! dashboard.primary ) {
			return;
		}
		var html = '<div class="itp-dashboard-grid">';
		html += buildDashboardAdvertMarkup( dashboard.primary, payload, { show_seasonal_badge: !! dashboard.show_seasonal_badge } );
		if ( dashboard.secondary ) {
			html += buildDashboardAdvertMarkup( dashboard.secondary, payload, { show_seasonal_badge: false } );
		}
		html += '</div><hr class="itp-dashboard-divider">';
		shell.innerHTML = html;
		state.dashHydrated = true;
	}

	// =====================================================================
	// Preferences nonce hydration
	// =====================================================================
	function hydratePreferencesNonce( payload ) {
		if ( state.prefsHydrated ) { return; }
		if ( ! payload.nonces || ! payload.nonces.user_interests ) {
			return;
		}
		var inputs = document.querySelectorAll( 'input[data-itp-hydrate="user_interests_nonce"]' );
		if ( ! inputs.length ) { return; }
		Array.prototype.forEach.call( inputs, function( input ) {
			input.value = payload.nonces.user_interests;
		} );
		state.prefsHydrated = true;
	}

	// =====================================================================
	// Preferences accordion + select-all behaviour
	// =====================================================================
	function initPreferencesAccordion() {
		var root = document.querySelector( '.itp-preferences-wrap' );
		if ( ! root ) { return; }

		document.addEventListener( 'click', function( e ) {
			var clickedHeader = e.target.closest && e.target.closest( '.itp-accordion-header' );
			if ( ! clickedHeader || ! root.contains( clickedHeader ) ) { return; }

			var allHeaders = root.querySelectorAll( '.itp-accordion-header' );
			Array.prototype.forEach.call( allHeaders, function( header ) {
				if ( header !== clickedHeader && header.classList.contains( 'active' ) ) {
					header.classList.remove( 'active' );
					var sibling = header.nextElementSibling;
					if ( sibling && sibling.classList.contains( 'itp-accordion-content' ) ) {
						sibling.style.maxHeight = null;
					}
				}
			} );

			clickedHeader.classList.toggle( 'active' );
			var content = clickedHeader.nextElementSibling;
			if ( content && content.classList.contains( 'itp-accordion-content' ) ) {
				if ( content.style.maxHeight ) {
					content.style.maxHeight = null;
				} else {
					content.style.maxHeight = content.scrollHeight + 'px';
				}
			}
		} );

		function updateSelectAllState( container, selectAllBox ) {
			var checkboxes = container.querySelectorAll( 'input[type="checkbox"]:not(.itp-select-all)' );
			var checkedCount = Array.prototype.filter.call( checkboxes, function( cb ) { return cb.checked; } ).length;
			var parent = selectAllBox.closest( '.itp-interest-item' );

			if ( checkedCount === 0 ) {
				selectAllBox.checked = false;
				selectAllBox.indeterminate = false;
				if ( parent ) { parent.classList.remove( 'itp-partial' ); }
			} else if ( checkedCount === checkboxes.length ) {
				selectAllBox.checked = true;
				selectAllBox.indeterminate = false;
				if ( parent ) { parent.classList.remove( 'itp-partial' ); }
			} else {
				selectAllBox.checked = false;
				selectAllBox.indeterminate = true;
				if ( parent ) { parent.classList.add( 'itp-partial' ); }
			}
		}

		Array.prototype.forEach.call( root.querySelectorAll( '.itp-select-all' ), function( selectAllBox ) {
			var targetId = selectAllBox.getAttribute( 'data-target' );
			if ( ! targetId ) { return; }
			var container = document.getElementById( targetId );
			if ( ! container ) { return; }

			updateSelectAllState( container, selectAllBox );

			selectAllBox.addEventListener( 'change', function() {
				var checkboxes = container.querySelectorAll( 'input[type="checkbox"]:not(.itp-select-all)' );
				Array.prototype.forEach.call( checkboxes, function( cb ) { cb.checked = selectAllBox.checked; } );
				updateSelectAllState( container, selectAllBox );
			} );

			container.addEventListener( 'change', function( ev ) {
				if ( ev.target && ev.target.matches( 'input[type="checkbox"]:not(.itp-select-all)' ) ) {
					updateSelectAllState( container, selectAllBox );
				}
			} );
		} );
	}

	// =====================================================================
	// Pro ROI click tracker (delegated, gated by payload.pro_roi_enabled)
	// =====================================================================
	function initRoiClickTracker( payload ) {
		if ( ! payload.pro_roi_enabled || ! payload.nonces || ! payload.nonces.roi_click ) {
			return;
		}
		var nonce = payload.nonces.roi_click;

		document.addEventListener( 'click', function( ev ) {
			var anchor = ev.target.closest && ev.target.closest( 'a[data-itp-track="1"]' );
			if ( ! anchor ) { return; }

			var intent = anchor.getAttribute( 'data-itp-intent' ) || '';
			var source = anchor.getAttribute( 'data-itp-source' ) || '';
			var href   = anchor.getAttribute( 'href' ) || '';
			if ( ! intent || ! href ) { return; }

			var btnText = anchor.textContent ? anchor.textContent.trim() : '';

			postForm( BOOT.ajaxUrl, {
				action: 'itp_pro_track_advert_click',
				nonce: nonce,
				intent: intent,
				source: source,
				advert_url: href,
				btn_text: btnText,
				page_url: window.location.href
			}, { keepalive: true } );
		}, true );
	}

	// =====================================================================
	// Boot sequence
	// =====================================================================
	function boot() {
		// Preferences accordion is purely cosmetic and runs even without bootstrap data.
		initPreferencesAccordion();

		fetchBootstrap().then( function( payload ) {
			if ( ! payload || ! payload.licence_active ) {
				return;
			}
			state.payload = payload;
			hydratePopup( payload );
			hydrateDashboardAdverts( payload );
			hydratePreferencesNonce( payload );
			initRoiClickTracker( payload );
		} ).catch( function() {
			// Silent — no telemetry endpoint to call.
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
