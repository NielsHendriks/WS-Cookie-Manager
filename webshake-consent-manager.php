<?php
/**
 * Plugin Name: Webshake Consent Manager
 * Plugin URI: https://webshake.nl/consent-manager
 * Description: GDPR/CCPA consent manager that auto-detects tracking scripts (Facebook Pixel, GA4, GTM, Matomo, Hotjar, LinkedIn, TikTok, and more) and blocks them until user consent is given.
 * Version: 1.2.4
 * Author: Webshake
 * Author URI: https://webshake.nl
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: webshake-consent-manager
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSCM_VERSION', '1.2.4' );
define( 'WSCM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSCM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WSCM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$puc_path = WSCM_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $puc_path ) ) {
	require $puc_path;
	YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/NielsHendriks/WS-Cookie-Manager/',
		__FILE__,
		'webshake-consent-manager'
	)->setBranch( 'main' );
}

require_once WSCM_PLUGIN_DIR . 'includes/class-wscm-db.php';
require_once WSCM_PLUGIN_DIR . 'includes/class-wscm-scanner.php';
require_once WSCM_PLUGIN_DIR . 'includes/class-wscm-blocker.php';
require_once WSCM_PLUGIN_DIR . 'includes/class-wscm-admin.php';
require_once WSCM_PLUGIN_DIR . 'includes/class-wscm-frontend.php';
require_once WSCM_PLUGIN_DIR . 'includes/class-wscm-rest.php';
require_once WSCM_PLUGIN_DIR . 'includes/class-wscm-cache.php';
require_once WSCM_PLUGIN_DIR . 'includes/class-wscm-dashboard.php';

final class Webshake_Consent_Manager {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'plugins_loaded', [ $this, 'boot' ] );
		add_filter( 'plugin_action_links_' . WSCM_PLUGIN_BASENAME, [ $this, 'settings_link' ] );
	}

	public function activate() {
		$defaults = [
			'banner_title'          => 'Wij respecteren je privacy',
			'banner_description'    => 'We gebruiken cookies en vergelijkbare technologieën om je surfervaring te verbeteren, verkeer te analyseren en gepersonaliseerde content aan te bieden. Kies hieronder je voorkeuren.',
			'accept_all_label'      => 'Alles accepteren',
			'reject_all_label'      => 'Alles weigeren',
			'save_preferences_label'=> 'Voorkeuren opslaan',
			'banner_position'       => 'bottom',
			'primary_color'         => '#2563eb',
			'consent_expiry_days'   => 365,
			'auto_scan'             => true,
			'detected_scripts'      => [],
			'custom_scripts'        => [],
			'geo_targeting'         => 'all',
		];

		if ( false === get_option( 'wscm_settings' ) ) {
			add_option( 'wscm_settings', $defaults );
		}

		WSCM_DB::create_table();
		WSCM_Scanner::run_scan();
	}

	public function deactivate() {
		wp_clear_scheduled_hook( 'wscm_periodic_scan' );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'webshake-consent-manager', false, dirname( WSCM_PLUGIN_BASENAME ) . '/languages' );
	}

	public function boot() {
		if ( is_admin() ) {
			new WSCM_Admin();
		}
		if ( ! is_admin() || wp_doing_ajax() ) {
			new WSCM_Frontend();
			new WSCM_Blocker();
		}
		new WSCM_Rest();
		new WSCM_Cache();
		new WSCM_Dashboard();
	}

	public function settings_link( $links ) {
		$url  = admin_url( 'options-general.php?page=wscm-settings' );
		$link = '<a href="' . esc_url( $url ) . '">' . __( 'Instellingen', 'webshake-consent-manager' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}
}

Webshake_Consent_Manager::instance();
