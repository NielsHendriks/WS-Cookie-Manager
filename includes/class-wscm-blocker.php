<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSCM_Blocker {

	public function __construct() {
		add_action( 'template_redirect', [ $this, 'start_buffer' ], 1 );
		add_action( 'wp_head', [ $this, 'inject_early_blocker' ], 1 );
	}

	/**
	 * Inject an inline script at the very top of <head> that prevents
	 * blocked scripts from executing before PHP output buffering kicks in.
	 * This handles scripts injected by other plugins via wp_head.
	 */
	public function inject_early_blocker() {
		$blocked  = WSCM_Scanner::get_blocked_patterns();
		if ( empty( $blocked ) ) {
			return;
		}

		$categories = [];
		foreach ( $blocked as $pattern => $cat ) {
			$categories[ $cat ][] = $pattern;
		}

		$json = wp_json_encode( $categories );
		?>
		<script data-wscm-early>
		(function(){
			window.__wscm_blocked = <?php echo $json; ?>;
			window.__wscm_consent = (function(){
				try {
					var c = document.cookie.match(/wscm_consent=([^;]+)/);
					return c ? JSON.parse(decodeURIComponent(c[1])) : null;
				} catch(e) { return null; }
			})();
		})();
		</script>
		<?php
	}

	/**
	 * Start output buffering to process the full HTML before sending to browser.
	 */
	public function start_buffer() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		ob_start( [ $this, 'process_buffer' ] );
	}

	/**
	 * Process the complete HTML output:
	 * - Convert blocked <script> tags to <script type="text/plain" data-wscm-category="...">
	 * - Convert blocked <iframe> tags similarly
	 * - Convert blocked <img> pixels
	 */
	public function process_buffer( $html ) {
		if ( empty( $html ) || strlen( $html ) < 100 ) {
			return $html;
		}

		if ( WSCM_Scanner::is_scan_pending() ) {
			WSCM_Scanner::scan_html( $html );
		}

		$blocked = WSCM_Scanner::get_blocked_patterns();
		if ( empty( $blocked ) ) {
			return $html;
		}

		$html = $this->block_script_tags( $html, $blocked );
		$html = $this->block_iframe_tags( $html, $blocked );
		$html = $this->block_img_tags( $html, $blocked );
		$html = $this->block_inline_scripts( $html, $blocked );

		return $html;
	}

	private function block_script_tags( $html, $blocked ) {
		return preg_replace_callback(
			'/<script\b([^>]*)>(.*?)<\/script>/is',
			function ( $match ) use ( $blocked ) {
				$attrs   = $match[1];
				$content = $match[2];
				$full    = $match[0];

				if ( strpos( $attrs, 'data-wscm-early' ) !== false ) {
					return $full;
				}
				if ( strpos( $attrs, 'data-wscm-consent' ) !== false ) {
					return $full;
				}

				foreach ( $blocked as $pattern => $category ) {
					if ( stripos( $attrs, $pattern ) !== false || stripos( $content, $pattern ) !== false ) {
						$attrs = preg_replace( '/type\s*=\s*["\'][^"\']*["\']/i', '', $attrs );
						return '<script type="text/plain" data-wscm-category="' . esc_attr( $category ) . '"' . $attrs . '>' . $content . '</script>';
					}
				}

				return $full;
			},
			$html
		);
	}

	private function block_iframe_tags( $html, $blocked ) {
		return preg_replace_callback(
			'/<iframe\b([^>]*)>/is',
			function ( $match ) use ( $blocked ) {
				$attrs = $match[1];

				foreach ( $blocked as $pattern => $category ) {
					if ( stripos( $attrs, $pattern ) !== false ) {
						$attrs = preg_replace( '/\bsrc\s*=/i', 'data-wscm-src=', $attrs );
						return '<iframe data-wscm-category="' . esc_attr( $category ) . '"' . $attrs . '>';
					}
				}

				return $match[0];
			},
			$html
		);
	}

	private function block_img_tags( $html, $blocked ) {
		return preg_replace_callback(
			'/<img\b([^>]*)>/is',
			function ( $match ) use ( $blocked ) {
				$attrs = $match[1];

				foreach ( $blocked as $pattern => $category ) {
					if ( stripos( $attrs, $pattern ) !== false ) {
						$attrs = preg_replace( '/\bsrc\s*=/i', 'data-wscm-src=', $attrs );
						return '<img data-wscm-category="' . esc_attr( $category ) . '"' . $attrs . '>';
					}
				}

				return $match[0];
			},
			$html
		);
	}

	/**
	 * Block inline scripts that match patterns (e.g. fbq(, _paq.push, ttq.load).
	 * These don't have src attributes so we match on content only.
	 */
	private function block_inline_scripts( $html, $blocked ) {
		return preg_replace_callback(
			'/<script\b([^>]*)>([\s\S]*?)<\/script>/i',
			function ( $match ) use ( $blocked ) {
				if ( strpos( $match[1], 'data-wscm-category' ) !== false ) {
					return $match[0];
				}
				if ( strpos( $match[1], 'data-wscm-early' ) !== false ) {
					return $match[0];
				}
				if ( strpos( $match[1], 'data-wscm-consent' ) !== false ) {
					return $match[0];
				}

				$content = $match[2];
				if ( empty( trim( $content ) ) ) {
					return $match[0];
				}

				foreach ( $blocked as $pattern => $category ) {
					if ( stripos( $content, $pattern ) !== false ) {
						$attrs = preg_replace( '/type\s*=\s*["\'][^"\']*["\']/i', '', $match[1] );
						return '<script type="text/plain" data-wscm-category="' . esc_attr( $category ) . '"' . $attrs . '>' . $content . '</script>';
					}
				}

				return $match[0];
			},
			$html
		);
	}
}
