<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSCM_Dashboard {

	public function __construct() {
		add_action( 'wp_dashboard_setup', [ $this, 'register_widget' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wscm_dashboard_widget',
			'Webshake Consent Manager',
			[ $this, 'render_widget' ]
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'index.php' !== $hook ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style( 'wscm-dashboard', WSCM_PLUGIN_URL . 'admin/css/dashboard.css', [], WSCM_VERSION );
	}

	public function render_widget() {
		$stats    = WSCM_DB::get_stats( 30 );
		$settings = get_option( 'wscm_settings', [] );
		$detected = $settings['detected_scripts'] ?? [];

		$accept_all = 0;
		$reject_all = 0;
		$save_prefs = 0;

		foreach ( $stats['by_action'] as $row ) {
			switch ( $row['action_type'] ) {
				case 'accept_all':
					$accept_all = (int) $row['cnt'];
					break;
				case 'reject_all':
					$reject_all = (int) $row['cnt'];
					break;
				case 'save_preferences':
					$save_prefs = (int) $row['cnt'];
					break;
			}
		}

		$accept_rate = $stats['total'] > 0 ? round( ( $accept_all / $stats['total'] ) * 100, 1 ) : 0;
		$reject_rate = $stats['total'] > 0 ? round( ( $reject_all / $stats['total'] ) * 100, 1 ) : 0;
		$custom_rate = $stats['total'] > 0 ? round( ( $save_prefs / $stats['total'] ) * 100, 1 ) : 0;

		$script_count   = count( $detected );
		$blocked_count  = count( array_filter( $detected, fn( $s ) => ! empty( $s['blocked'] ) ) );

		$active_cats = [];
		foreach ( $detected as $script ) {
			if ( ! empty( $script['blocked'] ) && ! empty( $script['category'] ) ) {
				$active_cats[ $script['category'] ] = true;
			}
		}

		$cat_bars = [];
		$cat_map  = [
			'analytics'  => [ 'label' => __( 'Analytisch', 'webshake-consent-manager' ),  'css' => 'analytics' ],
			'marketing'  => [ 'label' => __( 'Marketing', 'webshake-consent-manager' ),   'css' => 'marketing' ],
			'functional' => [ 'label' => __( 'Functioneel', 'webshake-consent-manager' ), 'css' => 'functional' ],
		];
		foreach ( $cat_map as $key => $meta ) {
			if ( ! isset( $active_cats[ $key ] ) ) {
				continue;
			}
			$count = (int) ( $stats['category_accepts'][ $key ] ?? 0 );
			$pct   = $stats['total'] > 0 ? round( ( $count / $stats['total'] ) * 100 ) : 0;
			$cat_bars[] = [ 'label' => $meta['label'], 'css' => $meta['css'], 'pct' => $pct ];
		}

		$daily = $stats['daily'] ?? [];
		$last7 = $this->get_sparkline_data( $daily );
		?>
		<div class="wscm-dw">
			<div class="wscm-dw-period">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
				<?php esc_html_e( 'Laatste 30 dagen', 'webshake-consent-manager' ); ?>
			</div>

			<!-- KPI row -->
			<div class="wscm-dw-kpis">
				<div class="wscm-dw-kpi">
					<span class="wscm-dw-kpi-val"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
					<span class="wscm-dw-kpi-lbl"><?php esc_html_e( 'Totaal', 'webshake-consent-manager' ); ?></span>
				</div>
				<div class="wscm-dw-kpi wscm-dw-kpi--accept">
					<span class="wscm-dw-kpi-val"><?php echo esc_html( $accept_rate . '%' ); ?></span>
					<span class="wscm-dw-kpi-lbl"><?php esc_html_e( 'Geaccepteerd', 'webshake-consent-manager' ); ?></span>
				</div>
				<div class="wscm-dw-kpi wscm-dw-kpi--reject">
					<span class="wscm-dw-kpi-val"><?php echo esc_html( $reject_rate . '%' ); ?></span>
					<span class="wscm-dw-kpi-lbl"><?php esc_html_e( 'Geweigerd', 'webshake-consent-manager' ); ?></span>
				</div>
				<div class="wscm-dw-kpi wscm-dw-kpi--custom">
					<span class="wscm-dw-kpi-val"><?php echo esc_html( $custom_rate . '%' ); ?></span>
					<span class="wscm-dw-kpi-lbl"><?php esc_html_e( 'Aangepast', 'webshake-consent-manager' ); ?></span>
				</div>
			</div>

			<!-- Sparkline -->
			<?php if ( ! empty( $last7 ) ) : ?>
			<div class="wscm-dw-spark">
				<span class="wscm-dw-spark-title"><?php esc_html_e( 'Trend (7 dagen)', 'webshake-consent-manager' ); ?></span>
				<div class="wscm-dw-spark-bars">
					<?php
					$max_val = max( 1, max( $last7 ) );
					foreach ( $last7 as $day_label => $count ) :
						$pct = round( ( $count / $max_val ) * 100 );
					?>
					<div class="wscm-dw-spark-col" title="<?php echo esc_attr( $day_label . ': ' . $count ); ?>">
						<div class="wscm-dw-spark-bar" style="height: <?php echo esc_attr( max( $pct, 4 ) ); ?>%;"></div>
						<span class="wscm-dw-spark-day"><?php echo esc_html( substr( $day_label, -2 ) ); ?></span>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Category bars -->
			<?php if ( ! empty( $cat_bars ) ) : ?>
			<div class="wscm-dw-cats">
				<span class="wscm-dw-cats-title"><?php esc_html_e( 'Acceptatie per categorie', 'webshake-consent-manager' ); ?></span>
				<?php foreach ( $cat_bars as $bar ) : ?>
				<div class="wscm-dw-cat-row">
					<span class="wscm-dw-cat-name"><?php echo esc_html( $bar['label'] ); ?></span>
					<div class="wscm-dw-cat-track">
						<div class="wscm-dw-cat-fill wscm-dw-cat-fill--<?php echo esc_attr( $bar['css'] ); ?>" style="width: <?php echo esc_attr( $bar['pct'] ); ?>%;"></div>
					</div>
					<span class="wscm-dw-cat-pct"><?php echo esc_html( $bar['pct'] . '%' ); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<!-- Scripts info -->
			<div class="wscm-dw-scripts">
				<div class="wscm-dw-script-stat">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
					<span>
						<?php
						printf(
							esc_html__( '%1$d scripts gedetecteerd, %2$d geblokkeerd', 'webshake-consent-manager' ),
							$script_count,
							$blocked_count
						);
						?>
					</span>
				</div>
				<?php if ( ! empty( $settings['last_scan'] ) ) : ?>
				<span class="wscm-dw-scan-date">
					<?php printf( esc_html__( 'Laatste scan: %s', 'webshake-consent-manager' ), esc_html( $settings['last_scan'] ) ); ?>
				</span>
				<?php endif; ?>
			</div>

			<!-- Footer link -->
			<div class="wscm-dw-footer">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wscm-settings' ) ); ?>" class="wscm-dw-link">
					<?php esc_html_e( 'Alle statistieken bekijken', 'webshake-consent-manager' ); ?>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wscm-settings' ) ); ?>" class="wscm-dw-link wscm-dw-link--secondary">
					<?php esc_html_e( 'Instellingen', 'webshake-consent-manager' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Extract the last 7 days of consent counts from the daily breakdown.
	 */
	private function get_sparkline_data( array $daily ): array {
		$by_day = [];
		foreach ( $daily as $row ) {
			$day = $row['day'];
			$by_day[ $day ] = ( $by_day[ $day ] ?? 0 ) + (int) $row['cnt'];
		}

		$result = [];
		for ( $i = 6; $i >= 0; $i-- ) {
			$date = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
			$result[ $date ] = $by_day[ $date ] ?? 0;
		}

		return $result;
	}
}
