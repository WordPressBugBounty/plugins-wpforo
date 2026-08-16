/**
 * gVectors News Module — admin notices JS.
 * Handles: per-item news notice dismissal + the first-activation opt-in notice.
 *
 * The module is a shared singleton across all gVectors plugins, so this file
 * loads once and reads one shared config object: window.gvectorsNews.
 */
( function( $ ) {
	'use strict';

	function getConfig() {
		return window.gvectorsNews && window.gvectorsNews.ajaxUrl ? window.gvectorsNews : null;
	}

	// ── News notice dismiss: only the clicked item, only for the current admin ──
	$( document ).on( 'click', '.gvectors-news-notice .gvectors-news-dismiss', function() {
		var $notice = $( this ).closest( '.gvectors-news-notice' );
		var config  = getConfig();
		var newsId  = $notice.data( 'news-id' );

		if ( config && newsId ) {
			$.post( config.ajaxUrl, {
				action:  config.ajaxPrefix + 'dismiss_news',
				nonce:   config.nonce,
				news_id: newsId
			} );
		}

		$notice.slideUp( 150, function() { $notice.remove(); } );
	} );

	// ── Opt-in notice: [Enable] / [Not now] ──
	function optin( $btn, enable ) {
		var $notice = $btn.closest( '.gvectors-news-optin' );
		var config  = getConfig();
		if ( ! config ) {
			$notice.slideUp( 150, function() { $notice.remove(); } );
			return;
		}

		$btn.prop( 'disabled', true );
		$.post( config.ajaxUrl, {
			action: config.ajaxPrefix + 'news_optin',
			nonce:  config.nonce,
			enable: enable ? 1 : 0
		} ).always( function() {
			$notice.slideUp( 150, function() { $notice.remove(); } );
		} );
	}

	$( document ).on( 'click', '.gvectors-news-optin-enable', function() {
		optin( $( this ), true );
	} );

	$( document ).on( 'click', '.gvectors-news-optin-later', function() {
		optin( $( this ), false );
	} );

} )( jQuery );
