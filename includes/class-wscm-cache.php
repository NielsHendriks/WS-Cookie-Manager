<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache compatibility layer.
 *
 * Purges full-page caches from popular caching plugins whenever
 * consent settings change, so visitors always get the correct
 * blocking state in the HTML.
 */
class WSCM_Cache {

	public function __construct() {
		add_action( 'update_option_wscm_settings', [ $this, 'purge_all_caches' ], 10, 0 );
	}

	/**
	 * Attempt to purge page caches from all known caching plugins.
	 * Each call is wrapped in a function_exists / class_exists check
	 * so it safely no-ops when a given plugin is not active.
	 */
	public function purge_all_caches() {

		// WP Fastest Cache
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true );
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// WP Super Cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// LiteSpeed Cache
		if ( class_exists( 'LiteSpeed\Purge' ) ) {
			do_action( 'litespeed_purge_all' );
		}

		// WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// Autoptimize
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall();
		}

		// Comet Cache / ZenCache
		if ( class_exists( 'comet_cache' ) && method_exists( 'comet_cache', 'clear' ) ) {
			comet_cache::clear();
		}

		// SG Optimizer (SiteGround)
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		// Breeze (Cloudways)
		if ( class_exists( 'Breeze_PurgeCache' ) && method_exists( 'Breeze_PurgeCache', 'breeze_cache_flush' ) ) {
			Breeze_PurgeCache::breeze_cache_flush();
		}

		// Hummingbird
		if ( class_exists( '\Hummingbird\Core\Utils' ) ) {
			do_action( 'wphb_clear_page_cache' );
		}

		// Cache Enabler
		if ( class_exists( 'Cache_Enabler' ) && method_exists( 'Cache_Enabler', 'clear_total_cache' ) ) {
			Cache_Enabler::clear_total_cache();
		}

		// Nginx Helper
		if ( class_exists( 'Jesin\NginxHelper\Admin' ) || function_exists( 'rt_nginx_helper_purge_all' ) ) {
			do_action( 'rt_nginx_helper_purge_all' );
		}

		// Swift Performance
		if ( class_exists( 'Swift_Performance_Cache' ) && method_exists( 'Swift_Performance_Cache', 'clear_all_cache' ) ) {
			Swift_Performance_Cache::clear_all_cache();
		}

		// Generic: WordPress object cache (Memcached, Redis, etc.)
		wp_cache_flush();
	}
}
