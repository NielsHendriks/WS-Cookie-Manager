<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSCM_DB {

	const TABLE_NAME = 'wscm_consent_log';
	const DB_VERSION = '1.0';

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	public static function create_table() {
		global $wpdb;
		$table   = self::get_table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			action_type VARCHAR(20) NOT NULL DEFAULT 'accept_all',
			analytics TINYINT(1) NOT NULL DEFAULT 0,
			marketing TINYINT(1) NOT NULL DEFAULT 0,
			functional TINYINT(1) NOT NULL DEFAULT 0,
			ip_hash VARCHAR(64) DEFAULT '',
			user_agent VARCHAR(255) DEFAULT '',
			page_url VARCHAR(512) DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_created (created_at),
			KEY idx_action (action_type)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wscm_db_version', self::DB_VERSION );
	}

	public static function drop_table() {
		global $wpdb;
		$table = self::get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( 'wscm_db_version' );
	}

	public static function log_consent( $data ) {
		global $wpdb;
		$table = self::get_table_name();

		$ip_raw  = $_SERVER['REMOTE_ADDR'] ?? '';
		$ip_hash = $ip_raw ? hash( 'sha256', $ip_raw . wp_salt( 'auth' ) ) : '';

		$wpdb->insert( $table, [
			'action_type' => sanitize_text_field( $data['action_type'] ?? 'custom' ),
			'analytics'   => ! empty( $data['analytics'] ) ? 1 : 0,
			'marketing'   => ! empty( $data['marketing'] ) ? 1 : 0,
			'functional'  => ! empty( $data['functional'] ) ? 1 : 0,
			'ip_hash'     => $ip_hash,
			'user_agent'  => sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255 ) ),
			'page_url'    => esc_url_raw( substr( $data['page_url'] ?? '', 0, 512 ) ),
			'created_at'  => current_time( 'mysql' ),
		], [ '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ] );
	}

	/**
	 * Aggregate stats for the dashboard.
	 */
	public static function get_stats( $days = 30 ) {
		global $wpdb;
		$table = self::get_table_name();
		$days  = max( 1, min( absint( $days ), 730 ) );
		$since = wp_date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
			$since
		) );

		$by_action = $wpdb->get_results( $wpdb->prepare(
			"SELECT action_type, COUNT(*) as cnt FROM {$table} WHERE created_at >= %s GROUP BY action_type ORDER BY cnt DESC",
			$since
		), ARRAY_A );

		$category_accepts = $wpdb->get_row( $wpdb->prepare(
			"SELECT SUM(analytics) as analytics, SUM(marketing) as marketing, SUM(functional) as functional FROM {$table} WHERE created_at >= %s",
			$since
		), ARRAY_A );

		$daily = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(created_at) as day, action_type, COUNT(*) as cnt FROM {$table} WHERE created_at >= %s GROUP BY day, action_type ORDER BY day ASC",
			$since
		), ARRAY_A );

		$unique_visitors = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT ip_hash) FROM {$table} WHERE created_at >= %s AND ip_hash != ''",
			$since
		) );

		return [
			'total'             => $total,
			'unique_visitors'   => $unique_visitors,
			'by_action'         => $by_action,
			'category_accepts'  => $category_accepts ?: [ 'analytics' => 0, 'marketing' => 0, 'functional' => 0 ],
			'daily'             => $daily,
			'days'              => $days,
		];
	}

	/**
	 * Purge logs older than N days.
	 */
	public static function purge( $older_than_days = 365 ) {
		global $wpdb;
		$table           = self::get_table_name();
		$older_than_days = max( 1, min( absint( $older_than_days ), 3650 ) );
		$cutoff          = wp_date( 'Y-m-d H:i:s', strtotime( "-{$older_than_days} days" ) );
		$result          = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE created_at < %s",
			$cutoff
		) );
		return $result !== false ? (int) $result : 0;
	}
}
