<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSCM_Rest {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( 'wscm/v1', '/consent', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'log_consent' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'consent' => [
					'required'          => true,
					'type'              => 'object',
					'validate_callback' => [ $this, 'validate_consent' ],
				],
				'action_type' => [
					'required'          => false,
					'type'              => 'string',
					'default'           => 'custom',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => [ $this, 'validate_action_type' ],
				],
				'page_url' => [
					'required'          => false,
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'esc_url_raw',
				],
			],
		] );

		register_rest_route( 'wscm/v1', '/stats', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_stats' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => [
				'days' => [
					'required'          => false,
					'type'              => 'integer',
					'default'           => 30,
					'sanitize_callback' => 'absint',
					'validate_callback' => function ( $value ) {
						$v = absint( $value );
						return $v >= 1 && $v <= 730;
					},
				],
			],
		] );
	}

	public function validate_consent( $value ) {
		if ( ! is_array( $value ) && ! is_object( $value ) ) {
			return new WP_Error( 'invalid_consent', 'Consent must be an object.', [ 'status' => 400 ] );
		}

		$allowed_keys = [ 'necessary', 'analytics', 'marketing', 'functional' ];
		$data         = (array) $value;

		foreach ( array_keys( $data ) as $key ) {
			if ( ! in_array( $key, $allowed_keys, true ) ) {
				return new WP_Error( 'invalid_consent_key', 'Unknown consent category: ' . $key, [ 'status' => 400 ] );
			}
		}

		return true;
	}

	public function validate_action_type( $value ) {
		$allowed = [ 'accept_all', 'reject_all', 'save_preferences', 'custom' ];
		return in_array( $value, $allowed, true );
	}

	public function log_consent( $request ) {
		if ( $this->is_rate_limited() ) {
			return new WP_Error( 'rate_limited', 'Too many requests.', [ 'status' => 429 ] );
		}

		$consent     = (array) $request->get_param( 'consent' );
		$action_type = $request->get_param( 'action_type' );
		$page_url    = $request->get_param( 'page_url' );

		WSCM_DB::log_consent( [
			'action_type' => $action_type,
			'analytics'   => ! empty( $consent['analytics'] ),
			'marketing'   => ! empty( $consent['marketing'] ),
			'functional'  => ! empty( $consent['functional'] ),
			'page_url'    => $page_url,
		] );

		do_action( 'wscm_consent_given', $consent, $action_type );

		return rest_ensure_response( [ 'success' => true ] );
	}

	public function get_stats( $request ) {
		$days  = min( absint( $request->get_param( 'days' ) ), 730 );
		$stats = WSCM_DB::get_stats( max( $days, 1 ) );
		return rest_ensure_response( $stats );
	}

	/**
	 * Simple IP-based rate limiting via transients: max 10 consent logs
	 * per IP per minute to prevent spam/DoS attacks on the public endpoint.
	 */
	private function get_client_ip() {
		// Cloudflare
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return $_SERVER['HTTP_CF_CONNECTING_IP'];
		}
		// Standard reverse-proxy header (single IP only — ignore lists to prevent spoofing)
		if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			return $_SERVER['HTTP_X_REAL_IP'];
		}
		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	private function is_rate_limited() {
		$ip  = $this->get_client_ip();
		$key = 'wscm_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 10 ) {
			return true;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return false;
	}
}
