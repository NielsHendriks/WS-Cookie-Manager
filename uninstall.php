<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wscm_settings' );
delete_option( 'wscm_db_version' );

global $wpdb;
$table = $wpdb->prefix . 'wscm_consent_log';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
