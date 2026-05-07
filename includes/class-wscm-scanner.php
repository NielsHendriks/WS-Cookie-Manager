<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSCM_Scanner {

	/**
	 * Known tracking script signatures mapped to service metadata.
	 * Each entry: pattern => [ name, category, description ]
	 *
	 * Categories:
	 *   analytics  — traffic measurement & reporting
	 *   marketing  — ad targeting, retargeting, conversion tracking
	 *   functional — enhanced UX features (chat widgets, A/B testing)
	 */
	const SIGNATURES = [
		// Google Analytics 4
		'gtag/js?id=G-'                   => [ 'Google Analytics 4',   'analytics',  'Google Analytics 4 measurement' ],
		'google-analytics.com/g/collect'  => [ 'Google Analytics 4',   'analytics',  'Google Analytics 4 measurement' ],
		'googletagmanager.com/gtag'       => [ 'Google Analytics 4',   'analytics',  'Google Analytics 4 measurement' ],

		// Google Tag Manager
		'googletagmanager.com/gtm.js'     => [ 'Google Tag Manager',   'marketing',  'Google Tag Manager container' ],
		'gtm.js'                          => [ 'Google Tag Manager',   'marketing',  'Google Tag Manager container' ],
		'GTM-'                            => [ 'Google Tag Manager',   'marketing',  'Google Tag Manager container' ],

		// Universal Analytics (legacy)
		'google-analytics.com/analytics.js' => [ 'Google Analytics (UA)', 'analytics', 'Universal Analytics (legacy)' ],
		'ga.js'                              => [ 'Google Analytics (UA)', 'analytics', 'Universal Analytics (legacy)' ],

		// Facebook / Meta Pixel
		'connect.facebook.net'            => [ 'Meta Pixel',           'marketing',  'Facebook / Meta conversion tracking' ],
		'fbevents.js'                     => [ 'Meta Pixel',           'marketing',  'Facebook / Meta conversion tracking' ],
		'facebook.com/tr'                 => [ 'Meta Pixel',           'marketing',  'Facebook / Meta conversion tracking' ],
		'fbq('                            => [ 'Meta Pixel',           'marketing',  'Facebook / Meta conversion tracking' ],

		// Matomo / Piwik
		'matomo.js'                       => [ 'Matomo',               'analytics',  'Matomo (formerly Piwik) analytics' ],
		'piwik.js'                        => [ 'Matomo',               'analytics',  'Matomo (formerly Piwik) analytics' ],
		'matomo.php'                      => [ 'Matomo',               'analytics',  'Matomo (formerly Piwik) analytics' ],
		'_paq.push'                       => [ 'Matomo',               'analytics',  'Matomo (formerly Piwik) analytics' ],

		// Hotjar
		'static.hotjar.com'              => [ 'Hotjar',               'analytics',  'Heatmaps, recordings & feedback' ],
		'hotjar.com/c/hotjar-'            => [ 'Hotjar',               'analytics',  'Heatmaps, recordings & feedback' ],

		// LinkedIn Insight Tag
		'snap.licdn.com/li.lms-analytics' => [ 'LinkedIn Insight',    'marketing',  'LinkedIn conversion tracking' ],
		'linkedin.com/insight'            => [ 'LinkedIn Insight',     'marketing',  'LinkedIn conversion tracking' ],
		'_linkedin_partner_id'            => [ 'LinkedIn Insight',     'marketing',  'LinkedIn conversion tracking' ],

		// TikTok Pixel
		'analytics.tiktok.com'           => [ 'TikTok Pixel',         'marketing',  'TikTok conversion tracking' ],
		'ttq.load'                       => [ 'TikTok Pixel',         'marketing',  'TikTok conversion tracking' ],

		// Pinterest Tag
		'pintrk('                        => [ 'Pinterest Tag',         'marketing',  'Pinterest conversion tracking' ],
		's.pinimg.com/ct/core.js'        => [ 'Pinterest Tag',         'marketing',  'Pinterest conversion tracking' ],

		// Twitter / X Pixel
		'static.ads-twitter.com'         => [ 'X (Twitter) Pixel',    'marketing',  'Twitter/X conversion tracking' ],
		'twq('                           => [ 'X (Twitter) Pixel',    'marketing',  'Twitter/X conversion tracking' ],

		// Snap Pixel
		'sc-static.net/scevent.min.js'   => [ 'Snapchat Pixel',       'marketing',  'Snapchat conversion tracking' ],
		'snaptr('                        => [ 'Snapchat Pixel',       'marketing',  'Snapchat conversion tracking' ],

		// Microsoft Clarity
		'clarity.ms/tag/'                => [ 'Microsoft Clarity',     'analytics',  'Session recording & heatmaps' ],

		// Microsoft / Bing UET
		'bat.bing.com/bat.js'            => [ 'Microsoft UET',        'marketing',  'Bing Ads conversion tracking' ],

		// HubSpot
		'js.hs-scripts.com'             => [ 'HubSpot',               'marketing',  'HubSpot tracking & forms' ],
		'js.hs-analytics.net'           => [ 'HubSpot',               'marketing',  'HubSpot tracking & forms' ],
		'hs-banner.com'                 => [ 'HubSpot',               'marketing',  'HubSpot tracking & forms' ],

		// Google Ads Remarketing
		'googleads.g.doubleclick.net'    => [ 'Google Ads',            'marketing',  'Google Ads remarketing & conversion' ],
		'google_conversion_id'           => [ 'Google Ads',            'marketing',  'Google Ads remarketing & conversion' ],
		'gtag/js?id=AW-'                => [ 'Google Ads',            'marketing',  'Google Ads remarketing & conversion' ],

		// Intercom
		'widget.intercom.io'            => [ 'Intercom',              'functional', 'Live chat widget' ],
		'Intercom('                      => [ 'Intercom',             'functional', 'Live chat widget' ],

		// Drift
		'js.driftt.com'                 => [ 'Drift',                 'functional', 'Live chat widget' ],

		// Crisp
		'client.crisp.chat'             => [ 'Crisp',                 'functional', 'Live chat widget' ],

		// Freshchat
		'wchat.freshchat.com'           => [ 'Freshchat',             'functional', 'Live chat widget' ],

		// Optimizely
		'cdn.optimizely.com'            => [ 'Optimizely',            'functional', 'A/B testing & experimentation' ],

		// VWO
		'dev.visualwebsiteoptimizer.com' => [ 'VWO',                  'functional', 'A/B testing & experimentation' ],

		// Segment
		'cdn.segment.com/analytics.js'  => [ 'Segment',               'analytics',  'Customer data platform' ],

		// Mixpanel
		'cdn.mxpnl.com'                 => [ 'Mixpanel',              'analytics',  'Product analytics' ],
		'mixpanel.com/libs'             => [ 'Mixpanel',              'analytics',  'Product analytics' ],

		// Amplitude
		'cdn.amplitude.com'             => [ 'Amplitude',             'analytics',  'Product analytics' ],

		// Heap
		'cdn.heapanalytics.com'         => [ 'Heap',                  'analytics',  'Product analytics' ],

		// Plausible
		'plausible.io/js/'              => [ 'Plausible',             'analytics',  'Privacy-friendly analytics' ],

		// Cookiebot (meta — another consent tool)
		'consent.cookiebot.com'         => [ 'Cookiebot',             'functional', 'Consent management (3rd-party)' ],

		// YouTube embeds (sets cookies)
		'youtube.com/embed'             => [ 'YouTube',               'marketing',  'Embedded video (sets tracking cookies)' ],
		'youtube-nocookie.com/embed'    => [ 'YouTube',               'marketing',  'Embedded video (sets tracking cookies)' ],

		// Vimeo embeds
		'player.vimeo.com'              => [ 'Vimeo',                 'marketing',  'Embedded video' ],

		// Google Maps embeds
		'maps.googleapis.com'           => [ 'Google Maps',           'functional', 'Embedded maps' ],
		'maps.google.com'               => [ 'Google Maps',           'functional', 'Embedded maps' ],
		'google.com/maps'               => [ 'Google Maps',           'functional', 'Embedded maps' ],
		'maps/embed'                    => [ 'Google Maps',           'functional', 'Embedded maps' ],
		'maps/api/js'                   => [ 'Google Maps',           'functional', 'Embedded maps' ],

		// reCAPTCHA
		'google.com/recaptcha'          => [ 'Google reCAPTCHA',      'functional', 'Spam protection' ],
	];

	/**
	 * Request a scan. Sets a pending flag and fires a non-blocking
	 * request to the homepage so the scan runs inside process_buffer.
	 */
	public static function request_scan() {
		set_transient( 'wscm_scan_pending', 1, 120 );

		wp_remote_get( home_url( '/' ), [
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => false,
			'headers'   => [
				'Cache-Control' => 'no-cache, no-store, must-revalidate',
				'Pragma'        => 'no-cache',
			],
		] );
	}

	/**
	 * Check whether a scan is pending.
	 */
	public static function is_scan_pending() {
		return (bool) get_transient( 'wscm_scan_pending' );
	}

	/**
	 * Scan raw HTML for known tracking signatures and save results.
	 * Called from the Blocker's output buffer with the unmodified HTML.
	 */
	public static function scan_html( $html ) {
		delete_transient( 'wscm_scan_pending' );

		if ( empty( $html ) || strlen( $html ) < 200 ) {
			return;
		}

		$settings = get_option( 'wscm_settings', [] );
		$detected = [];

		foreach ( self::SIGNATURES as $pattern => $meta ) {
			if ( stripos( $html, $pattern ) !== false ) {
				$slug = sanitize_title( $meta[0] );
				if ( ! isset( $detected[ $slug ] ) ) {
					$detected[ $slug ] = [
						'name'        => $meta[0],
						'category'    => $meta[1],
						'description' => $meta[2],
						'pattern'     => $pattern,
						'blocked'     => true,
						'detected_at' => current_time( 'mysql' ),
					];
				}
			}
		}

		$settings['detected_scripts'] = $detected;
		$settings['last_scan']        = current_time( 'mysql' );
		update_option( 'wscm_settings', $settings );
	}

	/**
	 * Legacy entry point — used on plugin activation.
	 * Triggers a deferred scan via a non-blocking request.
	 */
	public static function run_scan() {
		self::request_scan();
	}

	/**
	 * Get all known signature patterns grouped by service slug.
	 */
	public static function get_patterns_for_service( $service_name ) {
		$patterns = [];
		foreach ( self::SIGNATURES as $pattern => $meta ) {
			if ( $meta[0] === $service_name ) {
				$patterns[] = $pattern;
			}
		}
		return $patterns;
	}

	/**
	 * Get all blocked patterns from detected + custom scripts.
	 */
	public static function get_blocked_patterns() {
		$settings = get_option( 'wscm_settings', [] );
		$blocked  = [];

		$detected = $settings['detected_scripts'] ?? [];
		foreach ( $detected as $script ) {
			if ( ! empty( $script['blocked'] ) ) {
				$patterns = self::get_patterns_for_service( $script['name'] );
				foreach ( $patterns as $p ) {
					$blocked[ $p ] = $script['category'];
				}
			}
		}

		$custom = $settings['custom_scripts'] ?? [];
		foreach ( $custom as $script ) {
			if ( ! empty( $script['blocked'] ) && ! empty( $script['pattern'] ) ) {
				$blocked[ $script['pattern'] ] = $script['category'] ?? 'marketing';
			}
		}

		return $blocked;
	}
}
