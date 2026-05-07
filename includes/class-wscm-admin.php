<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSCM_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_wscm_save_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_wscm_run_scan', [ $this, 'ajax_run_scan' ] );
		add_action( 'wp_ajax_wscm_get_stats', [ $this, 'ajax_get_stats' ] );
		add_action( 'wp_ajax_wscm_purge_logs', [ $this, 'ajax_purge_logs' ] );
		add_action( 'wp_ajax_wscm_check_scan', [ $this, 'ajax_check_scan' ] );
	}

	public function add_menu() {
		add_options_page(
			__( 'Toestemmingsbeheer', 'webshake-consent-manager' ),
			__( 'Toestemmingsbeheer', 'webshake-consent-manager' ),
			'manage_options',
			'wscm-settings',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== 'settings_page_wscm-settings' ) {
			return;
		}

		wp_enqueue_style( 'wscm-admin', WSCM_PLUGIN_URL . 'admin/css/admin.css', [], WSCM_VERSION );
		wp_enqueue_script( 'wscm-admin', WSCM_PLUGIN_URL . 'admin/js/admin.js', [ 'jquery' ], WSCM_VERSION, true );

		$stats = WSCM_DB::get_stats( 30 );
		wp_localize_script( 'wscm-admin', 'wscm_admin', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wscm_admin_nonce' ),
			'settings' => get_option( 'wscm_settings', [] ),
			'stats'    => $stats,
			'strings'  => [
				'saved'    => __( 'Instellingen opgeslagen.', 'webshake-consent-manager' ),
				'scanning' => __( 'Scannen…', 'webshake-consent-manager' ),
				'scanned'  => __( 'Scan voltooid!', 'webshake-consent-manager' ),
				'error'    => __( 'Er is een fout opgetreden.', 'webshake-consent-manager' ),
			],
		] );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = get_option( 'wscm_settings', [] );
		?>
		<div class="wrap wscm-admin-wrap">
			<h1><?php esc_html_e( 'Webshake Toestemmingsbeheer', 'webshake-consent-manager' ); ?></h1>

			<div class="wscm-admin-header">
				<p class="description"><?php esc_html_e( 'Detecteer en beheer automatisch toestemming voor trackingscripts op je site.', 'webshake-consent-manager' ); ?></p>
			</div>

			<div class="wscm-tabs">
				<button class="wscm-tab active" data-tab="stats"><?php esc_html_e( 'Statistieken', 'webshake-consent-manager' ); ?></button>
				<button class="wscm-tab" data-tab="detected"><?php esc_html_e( 'Gedetecteerde scripts', 'webshake-consent-manager' ); ?></button>
				<button class="wscm-tab" data-tab="banner"><?php esc_html_e( 'Banner instellingen', 'webshake-consent-manager' ); ?></button>
				<button class="wscm-tab" data-tab="appearance"><?php esc_html_e( 'Uiterlijk', 'webshake-consent-manager' ); ?></button>
				<button class="wscm-tab" data-tab="advanced"><?php esc_html_e( 'Geavanceerd', 'webshake-consent-manager' ); ?></button>
				<button class="wscm-tab" data-tab="privacy"><?php esc_html_e( 'Privacybeleid', 'webshake-consent-manager' ); ?></button>
			</div>

			<!-- Stats Tab (outside the form) -->
			<div class="wscm-tab-content active" id="tab-stats">
				<?php $this->render_stats_tab(); ?>
			</div>

			<form id="wscm-settings-form">
				<?php wp_nonce_field( 'wscm_admin_nonce', 'wscm_nonce' ); ?>

				<!-- Detected Scripts Tab -->
				<div class="wscm-tab-content" id="tab-detected">
					<div class="wscm-card">
						<div class="wscm-card-header">
							<h2><?php esc_html_e( 'Gedetecteerde trackingscripts', 'webshake-consent-manager' ); ?></h2>
							<button type="button" id="wscm-scan-btn" class="button button-secondary">
								<span class="dashicons dashicons-search"></span>
								<?php esc_html_e( 'Site opnieuw scannen', 'webshake-consent-manager' ); ?>
							</button>
						</div>
						<?php if ( ! empty( $settings['last_scan'] ) ) : ?>
							<p class="wscm-last-scan"><?php printf( esc_html__( 'Laatste scan: %s', 'webshake-consent-manager' ), esc_html( $settings['last_scan'] ) ); ?></p>
						<?php endif; ?>

						<div id="wscm-scripts-list">
							<?php $this->render_scripts_list( $settings ); ?>
						</div>
					</div>

					<!-- Manual / Custom Scripts -->
					<div class="wscm-card" style="margin-top: 20px;">
						<div class="wscm-card-header">
							<h2><?php esc_html_e( 'Handmatig toevoegen', 'webshake-consent-manager' ); ?></h2>
							<button type="button" id="wscm-add-custom" class="button button-secondary">
								<span class="dashicons dashicons-plus-alt2"></span>
								<?php esc_html_e( 'Script toevoegen', 'webshake-consent-manager' ); ?>
							</button>
						</div>
						<p class="description" style="margin-bottom: 16px;">
							<?php esc_html_e( 'Voeg handmatig scripts toe die niet automatisch gevonden zijn. Vul een herkenbaar patroon in uit de URL of de inline code van het script.', 'webshake-consent-manager' ); ?>
						</p>
						<div id="wscm-custom-scripts">
							<?php
							$custom_scripts = $settings['custom_scripts'] ?? [];
							if ( ! empty( $custom_scripts ) ) :
								foreach ( $custom_scripts as $idx => $cs ) :
							?>
							<div class="wscm-custom-row" data-index="<?php echo esc_attr( $idx ); ?>">
								<input type="text" name="custom_scripts[<?php echo esc_attr( $idx ); ?>][name]" value="<?php echo esc_attr( $cs['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Naam (bijv. Google Maps)', 'webshake-consent-manager' ); ?>" class="wscm-custom-name">
								<input type="text" name="custom_scripts[<?php echo esc_attr( $idx ); ?>][pattern]" value="<?php echo esc_attr( $cs['pattern'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Patroon (bijv. maps.googleapis.com)', 'webshake-consent-manager' ); ?>" class="wscm-custom-pattern">
								<select name="custom_scripts[<?php echo esc_attr( $idx ); ?>][category]" class="wscm-custom-category">
									<option value="analytics" <?php selected( $cs['category'] ?? '', 'analytics' ); ?>><?php esc_html_e( 'Analytisch', 'webshake-consent-manager' ); ?></option>
									<option value="marketing" <?php selected( $cs['category'] ?? '', 'marketing' ); ?>><?php esc_html_e( 'Marketing', 'webshake-consent-manager' ); ?></option>
									<option value="functional" <?php selected( $cs['category'] ?? '', 'functional' ); ?>><?php esc_html_e( 'Functioneel', 'webshake-consent-manager' ); ?></option>
								</select>
								<label class="wscm-toggle wscm-custom-toggle">
									<input type="checkbox" name="custom_scripts[<?php echo esc_attr( $idx ); ?>][blocked]" value="1" <?php checked( ! empty( $cs['blocked'] ) ); ?>>
									<span class="wscm-toggle-slider"></span>
								</label>
								<button type="button" class="button wscm-remove-custom" title="<?php esc_attr_e( 'Verwijderen', 'webshake-consent-manager' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
							<?php
								endforeach;
							endif;
							?>
						</div>
					</div>
				</div>

				<!-- Banner Settings Tab -->
				<div class="wscm-tab-content" id="tab-banner">
					<div class="wscm-card">
						<h2><?php esc_html_e( 'Banner tekst', 'webshake-consent-manager' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><label for="wscm-banner-title"><?php esc_html_e( 'Titel', 'webshake-consent-manager' ); ?></label></th>
								<td><input type="text" id="wscm-banner-title" name="banner_title" class="large-text" value="<?php echo esc_attr( $settings['banner_title'] ?? '' ); ?>"></td>
							</tr>
							<tr>
								<th><label for="wscm-banner-desc"><?php esc_html_e( 'Beschrijving', 'webshake-consent-manager' ); ?></label></th>
								<td><textarea id="wscm-banner-desc" name="banner_description" class="large-text" rows="3"><?php echo esc_textarea( $settings['banner_description'] ?? '' ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="wscm-accept-label"><?php esc_html_e( 'Alles accepteren knop', 'webshake-consent-manager' ); ?></label></th>
								<td><input type="text" id="wscm-accept-label" name="accept_all_label" value="<?php echo esc_attr( $settings['accept_all_label'] ?? 'Alles accepteren' ); ?>"></td>
							</tr>
							<tr>
								<th><label for="wscm-reject-label"><?php esc_html_e( 'Alles weigeren knop', 'webshake-consent-manager' ); ?></label></th>
								<td><input type="text" id="wscm-reject-label" name="reject_all_label" value="<?php echo esc_attr( $settings['reject_all_label'] ?? 'Alles weigeren' ); ?>"></td>
							</tr>
							<tr>
								<th><label for="wscm-save-label"><?php esc_html_e( 'Voorkeuren opslaan knop', 'webshake-consent-manager' ); ?></label></th>
								<td><input type="text" id="wscm-save-label" name="save_preferences_label" value="<?php echo esc_attr( $settings['save_preferences_label'] ?? 'Voorkeuren opslaan' ); ?>"></td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Appearance Tab -->
				<div class="wscm-tab-content" id="tab-appearance">
					<div class="wscm-card">
						<h2><?php esc_html_e( 'Uiterlijk banner', 'webshake-consent-manager' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><label for="wscm-position"><?php esc_html_e( 'Positie', 'webshake-consent-manager' ); ?></label></th>
								<td>
									<select id="wscm-position" name="banner_position">
										<option value="bottom" <?php selected( $settings['banner_position'] ?? 'bottom', 'bottom' ); ?>><?php esc_html_e( 'Onderbalk', 'webshake-consent-manager' ); ?></option>
										<option value="top" <?php selected( $settings['banner_position'] ?? '', 'top' ); ?>><?php esc_html_e( 'Bovenbalk', 'webshake-consent-manager' ); ?></option>
										<option value="center" <?php selected( $settings['banner_position'] ?? '', 'center' ); ?>><?php esc_html_e( 'Centraal venster', 'webshake-consent-manager' ); ?></option>
										<option value="bottom-left" <?php selected( $settings['banner_position'] ?? '', 'bottom-left' ); ?>><?php esc_html_e( 'Linksonder popup', 'webshake-consent-manager' ); ?></option>
										<option value="bottom-right" <?php selected( $settings['banner_position'] ?? '', 'bottom-right' ); ?>><?php esc_html_e( 'Rechtsonder popup', 'webshake-consent-manager' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><label for="wscm-primary-color"><?php esc_html_e( 'Hoofdkleur', 'webshake-consent-manager' ); ?></label></th>
								<td><input type="color" id="wscm-primary-color" name="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ?? '#2563eb' ); ?>"></td>
							</tr>
							<tr>
								<th><label for="wscm-dark-mode"><?php esc_html_e( 'Dark mode', 'webshake-consent-manager' ); ?></label></th>
								<td>
									<select id="wscm-dark-mode" name="dark_mode">
										<option value="off" <?php selected( $settings['dark_mode'] ?? 'off', 'off' ); ?>><?php esc_html_e( 'Uit (licht)', 'webshake-consent-manager' ); ?></option>
										<option value="on" <?php selected( $settings['dark_mode'] ?? 'off', 'on' ); ?>><?php esc_html_e( 'Aan (donker)', 'webshake-consent-manager' ); ?></option>
										<option value="auto" <?php selected( $settings['dark_mode'] ?? 'off', 'auto' ); ?>><?php esc_html_e( 'Automatisch (volgt systeemvoorkeur)', 'webshake-consent-manager' ); ?></option>
									</select>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Advanced Tab -->
				<div class="wscm-tab-content" id="tab-advanced">
					<div class="wscm-card">
						<h2><?php esc_html_e( 'Geavanceerde instellingen', 'webshake-consent-manager' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><label for="wscm-expiry"><?php esc_html_e( 'Toestemming verloopt na (dagen)', 'webshake-consent-manager' ); ?></label></th>
								<td><input type="number" id="wscm-expiry" name="consent_expiry_days" min="1" max="730" value="<?php echo esc_attr( $settings['consent_expiry_days'] ?? 365 ); ?>"></td>
							</tr>
							<tr>
								<th><label for="wscm-geo"><?php esc_html_e( 'Banner tonen aan', 'webshake-consent-manager' ); ?></label></th>
								<td>
									<select id="wscm-geo" name="geo_targeting">
										<option value="all" <?php selected( $settings['geo_targeting'] ?? 'all', 'all' ); ?>><?php esc_html_e( 'Alle bezoekers', 'webshake-consent-manager' ); ?></option>
										<option value="eu" <?php selected( $settings['geo_targeting'] ?? '', 'eu' ); ?>><?php esc_html_e( 'Alleen EU-bezoekers', 'webshake-consent-manager' ); ?></option>
										<option value="california" <?php selected( $settings['geo_targeting'] ?? '', 'california' ); ?>><?php esc_html_e( 'Californië + EU', 'webshake-consent-manager' ); ?></option>
									</select>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary button-hero" id="wscm-save-btn">
						<?php esc_html_e( 'Instellingen opslaan', 'webshake-consent-manager' ); ?>
					</button>
					<span id="wscm-save-notice" class="wscm-notice"></span>
				</p>
			</form>

			<!-- Privacy Policy Tab (outside form) -->
			<div class="wscm-tab-content" id="tab-privacy">
				<?php $this->render_privacy_tab( $settings ); ?>
			</div>
		</div>
		<?php
	}

	private function render_scripts_list( $settings ) {
		$detected = $settings['detected_scripts'] ?? [];

		if ( empty( $detected ) ) {
			echo '<div class="wscm-empty-state">';
			echo '<span class="dashicons dashicons-yes-alt"></span>';
			echo '<p>' . esc_html__( 'Nog geen trackingscripts gedetecteerd. Klik op "Site opnieuw scannen" om te controleren.', 'webshake-consent-manager' ) . '</p>';
			echo '</div>';
			return;
		}

		$categories = [
			'analytics'  => __( 'Analytisch', 'webshake-consent-manager' ),
			'marketing'  => __( 'Marketing', 'webshake-consent-manager' ),
			'functional' => __( 'Functioneel', 'webshake-consent-manager' ),
		];

		foreach ( $categories as $cat_key => $cat_label ) {
			$scripts_in_cat = array_filter( $detected, fn( $s ) => ( $s['category'] ?? '' ) === $cat_key );
			if ( empty( $scripts_in_cat ) ) {
				continue;
			}

			echo '<div class="wscm-category-group">';
			echo '<h3 class="wscm-category-title">';
			echo '<span class="wscm-cat-badge wscm-cat-' . esc_attr( $cat_key ) . '">' . esc_html( $cat_label ) . '</span>';
			echo '</h3>';
			echo '<div class="wscm-scripts-grid">';

			foreach ( $scripts_in_cat as $slug => $script ) {
				$blocked = ! empty( $script['blocked'] );
				echo '<div class="wscm-script-card">';
				echo '<div class="wscm-script-info">';
				echo '<strong>' . esc_html( $script['name'] ) . '</strong>';
				echo '<span class="wscm-script-desc">' . esc_html( $script['description'] ) . '</span>';
				echo '</div>';
				echo '<label class="wscm-toggle">';
				echo '<input type="checkbox" name="detected_scripts[' . esc_attr( $slug ) . '][blocked]" value="1"' . checked( $blocked, true, false ) . '>';
				echo '<span class="wscm-toggle-slider"></span>';
				echo '<span class="wscm-toggle-label">' . esc_html__( 'Blokkeren tot toestemming', 'webshake-consent-manager' ) . '</span>';
				echo '</label>';
				echo '<input type="hidden" name="detected_scripts[' . esc_attr( $slug ) . '][name]" value="' . esc_attr( $script['name'] ) . '">';
				echo '<input type="hidden" name="detected_scripts[' . esc_attr( $slug ) . '][category]" value="' . esc_attr( $script['category'] ) . '">';
				echo '<input type="hidden" name="detected_scripts[' . esc_attr( $slug ) . '][description]" value="' . esc_attr( $script['description'] ) . '">';
				echo '</div>';
			}

			echo '</div></div>';
		}
	}

	public function ajax_save_settings() {
		check_ajax_referer( 'wscm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$settings = get_option( 'wscm_settings', [] );
		$data     = wp_unslash( $_POST['settings'] ?? [] );

		$text_fields = [ 'banner_title', 'banner_description', 'accept_all_label', 'reject_all_label', 'save_preferences_label' ];
		foreach ( $text_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$settings[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		if ( isset( $data['banner_position'] ) ) {
			$allowed_positions = [ 'bottom', 'top', 'center', 'bottom-left', 'bottom-right' ];
			$pos = sanitize_text_field( $data['banner_position'] );
			$settings['banner_position'] = in_array( $pos, $allowed_positions, true ) ? $pos : 'bottom';
		}
		if ( isset( $data['primary_color'] ) ) {
			$settings['primary_color'] = sanitize_hex_color( $data['primary_color'] ) ?: '#2563eb';
		}
		if ( isset( $data['consent_expiry_days'] ) ) {
			$settings['consent_expiry_days'] = absint( $data['consent_expiry_days'] );
		}
		if ( isset( $data['geo_targeting'] ) ) {
			$allowed_geo = [ 'all', 'eu', 'california' ];
			$geo         = sanitize_text_field( $data['geo_targeting'] );
			$settings['geo_targeting'] = in_array( $geo, $allowed_geo, true ) ? $geo : 'all';
		}
		if ( isset( $data['dark_mode'] ) ) {
			$allowed_modes = [ 'off', 'on', 'auto' ];
			$mode = sanitize_text_field( $data['dark_mode'] );
			$settings['dark_mode'] = in_array( $mode, $allowed_modes, true ) ? $mode : 'off';
		}

		if ( isset( $data['detected_scripts'] ) && is_array( $data['detected_scripts'] ) ) {
			foreach ( $data['detected_scripts'] as $slug => $script_data ) {
				$slug = sanitize_title( $slug );
				if ( isset( $settings['detected_scripts'][ $slug ] ) ) {
					$settings['detected_scripts'][ $slug ]['blocked'] = ! empty( $script_data['blocked'] );
				}
			}
		}

		$custom = [];
		if ( isset( $data['custom_scripts'] ) && is_array( $data['custom_scripts'] ) ) {
			$allowed_cats = [ 'analytics', 'marketing', 'functional' ];
			foreach ( $data['custom_scripts'] as $cs ) {
				$name    = sanitize_text_field( $cs['name'] ?? '' );
				$pattern = sanitize_text_field( $cs['pattern'] ?? '' );
				$cat     = sanitize_text_field( $cs['category'] ?? 'marketing' );
				if ( empty( $name ) || empty( $pattern ) ) {
					continue;
				}
				$custom[] = [
					'name'     => $name,
					'pattern'  => $pattern,
					'category' => in_array( $cat, $allowed_cats, true ) ? $cat : 'marketing',
					'blocked'  => ! empty( $cs['blocked'] ),
				];
			}
		}
		$settings['custom_scripts'] = $custom;

		update_option( 'wscm_settings', $settings );
		wp_send_json_success( [ 'settings' => $settings ] );
	}

	public function ajax_run_scan() {
		check_ajax_referer( 'wscm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$settings  = get_option( 'wscm_settings', [] );
		$old_stamp = $settings['last_scan'] ?? '';

		WSCM_Scanner::request_scan();

		wp_send_json_success( [
			'status'     => 'scanning',
			'last_scan'  => $old_stamp,
		] );
	}

	public function ajax_check_scan() {
		check_ajax_referer( 'wscm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$settings = get_option( 'wscm_settings', [] );
		$pending  = WSCM_Scanner::is_scan_pending();

		wp_send_json_success( [
			'status'    => $pending ? 'scanning' : 'done',
			'detected'  => $settings['detected_scripts'] ?? [],
			'last_scan' => $settings['last_scan'] ?? '',
		] );
	}

	public function ajax_get_stats() {
		check_ajax_referer( 'wscm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$days  = absint( $_GET['days'] ?? 30 );
		$stats = WSCM_DB::get_stats( $days );
		wp_send_json_success( $stats );
	}

	public function ajax_purge_logs() {
		check_ajax_referer( 'wscm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$older_than = absint( $_POST['older_than'] ?? 90 );
		$deleted    = WSCM_DB::purge( $older_than );
		wp_send_json_success( [ 'deleted' => $deleted ] );
	}

	private function render_stats_tab() {
		$stats = WSCM_DB::get_stats( 30 );

		$accept_all  = 0;
		$reject_all  = 0;
		$save_prefs  = 0;
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
		?>
		<div class="wscm-stats-header">
			<h2><?php esc_html_e( 'Toestemmingsstatistieken', 'webshake-consent-manager' ); ?></h2>
			<div class="wscm-stats-controls">
				<select id="wscm-stats-range">
					<option value="7"><?php esc_html_e( 'Laatste 7 dagen', 'webshake-consent-manager' ); ?></option>
					<option value="30" selected><?php esc_html_e( 'Laatste 30 dagen', 'webshake-consent-manager' ); ?></option>
					<option value="90"><?php esc_html_e( 'Laatste 90 dagen', 'webshake-consent-manager' ); ?></option>
					<option value="365"><?php esc_html_e( 'Laatste jaar', 'webshake-consent-manager' ); ?></option>
				</select>
				<button type="button" id="wscm-purge-btn" class="button button-link-delete" title="<?php esc_attr_e( 'Oude logs verwijderen', 'webshake-consent-manager' ); ?>">
					<?php esc_html_e( 'Oude logs opschonen', 'webshake-consent-manager' ); ?>
				</button>
			</div>
		</div>

		<!-- KPI Cards -->
		<div class="wscm-kpi-grid" id="wscm-kpi-grid">
			<div class="wscm-kpi-card">
				<div class="wscm-kpi-icon wscm-kpi-total">
					<span class="dashicons dashicons-groups"></span>
				</div>
				<div class="wscm-kpi-body">
					<span class="wscm-kpi-value" id="kpi-total"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
					<span class="wscm-kpi-label"><?php esc_html_e( 'Totaal toestemmingen', 'webshake-consent-manager' ); ?></span>
				</div>
			</div>
			<div class="wscm-kpi-card">
				<div class="wscm-kpi-icon wscm-kpi-accept">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="wscm-kpi-body">
					<span class="wscm-kpi-value" id="kpi-accept"><?php echo esc_html( $accept_rate . '%' ); ?></span>
					<span class="wscm-kpi-label"><?php esc_html_e( 'Acceptatiegraad', 'webshake-consent-manager' ); ?></span>
				</div>
			</div>
			<div class="wscm-kpi-card">
				<div class="wscm-kpi-icon wscm-kpi-reject">
					<span class="dashicons dashicons-dismiss"></span>
				</div>
				<div class="wscm-kpi-body">
					<span class="wscm-kpi-value" id="kpi-reject"><?php echo esc_html( $reject_rate . '%' ); ?></span>
					<span class="wscm-kpi-label"><?php esc_html_e( 'Weigeringsgraad', 'webshake-consent-manager' ); ?></span>
				</div>
			</div>
			<div class="wscm-kpi-card">
				<div class="wscm-kpi-icon wscm-kpi-custom">
					<span class="dashicons dashicons-admin-generic"></span>
				</div>
				<div class="wscm-kpi-body">
					<span class="wscm-kpi-value" id="kpi-custom"><?php echo esc_html( $custom_rate . '%' ); ?></span>
					<span class="wscm-kpi-label"><?php esc_html_e( 'Aangepaste voorkeuren', 'webshake-consent-manager' ); ?></span>
				</div>
			</div>
			<div class="wscm-kpi-card">
				<div class="wscm-kpi-icon wscm-kpi-visitors">
					<span class="dashicons dashicons-admin-users"></span>
				</div>
				<div class="wscm-kpi-body">
					<span class="wscm-kpi-value" id="kpi-visitors"><?php echo esc_html( number_format_i18n( $stats['unique_visitors'] ) ); ?></span>
					<span class="wscm-kpi-label"><?php esc_html_e( 'Unieke bezoekers', 'webshake-consent-manager' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Charts Row -->
		<div class="wscm-charts-row">
			<div class="wscm-card wscm-chart-card wscm-chart-wide">
				<h3><?php esc_html_e( 'Dagelijkse toestemmingstrend', 'webshake-consent-manager' ); ?></h3>
				<div class="wscm-chart-container">
					<canvas id="wscm-chart-daily" height="260"></canvas>
				</div>
			</div>
			<div class="wscm-card wscm-chart-card wscm-chart-narrow">
				<h3><?php esc_html_e( 'Toestemming verdeling', 'webshake-consent-manager' ); ?></h3>
				<div class="wscm-chart-container wscm-chart-donut-wrap">
					<canvas id="wscm-chart-donut" height="220"></canvas>
					<div class="wscm-donut-legend" id="wscm-donut-legend">
						<div class="wscm-legend-item"><span class="wscm-legend-dot" style="background:#22c55e;"></span> <?php esc_html_e( 'Alles accepteren', 'webshake-consent-manager' ); ?> <strong id="legend-accept"><?php echo esc_html( $accept_all ); ?></strong></div>
						<div class="wscm-legend-item"><span class="wscm-legend-dot" style="background:#ef4444;"></span> <?php esc_html_e( 'Alles weigeren', 'webshake-consent-manager' ); ?> <strong id="legend-reject"><?php echo esc_html( $reject_all ); ?></strong></div>
						<div class="wscm-legend-item"><span class="wscm-legend-dot" style="background:#3b82f6;"></span> <?php esc_html_e( 'Aangepast', 'webshake-consent-manager' ); ?> <strong id="legend-custom"><?php echo esc_html( $save_prefs ); ?></strong></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Category Acceptance -->
		<?php
		$settings_data = get_option( 'wscm_settings', [] );
		$detected_scripts = $settings_data['detected_scripts'] ?? [];
		$active_cats = [];
		foreach ( $detected_scripts as $script ) {
			if ( ! empty( $script['blocked'] ) && ! empty( $script['category'] ) ) {
				$active_cats[ $script['category'] ] = true;
			}
		}

		$cats = [
			'analytics'  => [ __( 'Analytisch', 'webshake-consent-manager' ),  '#3b82f6' ],
			'marketing'  => [ __( 'Marketing', 'webshake-consent-manager' ),  '#ec4899' ],
			'functional' => [ __( 'Functioneel', 'webshake-consent-manager' ), '#10b981' ],
		];

		$visible_cats = array_intersect_key( $cats, $active_cats );

		if ( ! empty( $visible_cats ) ) :
		?>
		<div class="wscm-card">
			<h3><?php esc_html_e( 'Acceptatie per categorie', 'webshake-consent-manager' ); ?></h3>
			<div class="wscm-category-bars" id="wscm-category-bars">
				<?php
				foreach ( $visible_cats as $key => $meta ) :
					$count = (int) ( $stats['category_accepts'][ $key ] ?? 0 );
					$pct   = $stats['total'] > 0 ? round( ( $count / $stats['total'] ) * 100, 1 ) : 0;
				?>
				<div class="wscm-bar-row">
					<span class="wscm-bar-label"><?php echo esc_html( $meta[0] ); ?></span>
					<div class="wscm-bar-track">
						<div class="wscm-bar-fill" style="width: <?php echo esc_attr( $pct ); ?>%; background: <?php echo esc_attr( $meta[1] ); ?>;"></div>
					</div>
					<span class="wscm-bar-value" data-cat="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $pct . '%' ); ?></span>
					<span class="wscm-bar-count">(<?php echo esc_html( number_format_i18n( $count ) ); ?>)</span>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		<?php
	}

	private function render_privacy_tab( $settings ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();
		$detected  = $settings['detected_scripts'] ?? [];
		$custom    = $settings['custom_scripts'] ?? [];

		$analytics_list  = [];
		$marketing_list  = [];
		$functional_list = [];

		foreach ( $detected as $script ) {
			if ( empty( $script['blocked'] ) ) {
				continue;
			}
			switch ( $script['category'] ?? '' ) {
				case 'analytics':
					$analytics_list[] = $script['name'];
					break;
				case 'marketing':
					$marketing_list[] = $script['name'];
					break;
				case 'functional':
					$functional_list[] = $script['name'];
					break;
			}
		}

		foreach ( $custom as $script ) {
			if ( empty( $script['blocked'] ) || empty( $script['name'] ) ) {
				continue;
			}
			switch ( $script['category'] ?? '' ) {
				case 'analytics':
					$analytics_list[] = $script['name'];
					break;
				case 'marketing':
					$marketing_list[] = $script['name'];
					break;
				case 'functional':
					$functional_list[] = $script['name'];
					break;
			}
		}

		$analytics_list  = array_unique( $analytics_list );
		$marketing_list  = array_unique( $marketing_list );
		$functional_list = array_unique( $functional_list );

		$analytics_str  = ! empty( $analytics_list )  ? implode( ', ', $analytics_list )  : '—';
		$marketing_str  = ! empty( $marketing_list )  ? implode( ', ', $marketing_list )  : '—';
		$functional_str = ! empty( $functional_list ) ? implode( ', ', $functional_list ) : '—';

		$today = wp_date( 'j F Y' );

		ob_start();
		?>
<h2>Privacybeleid van <?php echo esc_html( $site_name ); ?></h2>

<p><strong>Laatst bijgewerkt:</strong> <?php echo esc_html( $today ); ?></p>

<p><?php echo esc_html( $site_name ); ?> (hierna: "wij", "ons", "onze"), bereikbaar via <a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_url ); ?></a>, is verantwoordelijk voor de verwerking van persoonsgegevens zoals weergegeven in dit privacybeleid.</p>

<h3>1. Persoonsgegevens die wij verwerken</h3>

<p>Wij verwerken persoonsgegevens doordat je gebruik maakt van onze diensten en/of omdat je deze gegevens zelf aan ons verstrekt. Hieronder vind je een overzicht van de persoonsgegevens die wij verwerken:</p>

<ul>
<li>IP-adres (geanonimiseerd)</li>
<li>Gegevens over jouw activiteiten op onze website</li>
<li>Internetbrowser en apparaattype</li>
<li>Overige persoonsgegevens die je actief verstrekt, bijvoorbeeld door een formulier in te vullen</li>
</ul>

<h3>2. Waarom wij gegevens verwerken</h3>

<p>Wij verwerken jouw persoonsgegevens voor de volgende doeleinden:</p>

<ul>
<li>Het verbeteren van onze website en dienstverlening</li>
<li>Het analyseren van websitegebruik om de gebruikerservaring te optimaliseren</li>
<li>Het tonen van relevante advertenties en het meten van campagneresultaten</li>
<li>Het voldoen aan wettelijke verplichtingen</li>
</ul>

<h3>3. Cookies en vergelijkbare technologieën</h3>

<p>Onze website maakt gebruik van cookies en vergelijkbare technologieën. Een cookie is een klein tekstbestand dat bij het eerste bezoek aan deze website wordt opgeslagen in de browser van je computer, tablet of smartphone.</p>

<p>Bij je eerste bezoek aan <?php echo esc_html( $site_name ); ?> (<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_url ); ?></a>) tonen wij een banner waarmee we je informeren over het gebruik van cookies. Je kunt je voorkeuren op elk moment aanpassen via het cookie-instellingen icoon linksonder op de website.</p>

<h4>Noodzakelijke cookies</h4>
<p>Deze cookies zijn essentieel voor het functioneren van de website. Ze kunnen niet worden uitgeschakeld. Ze worden bijvoorbeeld gebruikt om je cookievoorkeuren op te slaan.</p>

<h4>Analytische cookies</h4>
<p>Met deze cookies kunnen wij het gebruik van de website analyseren, zodat wij de prestaties kunnen meten en verbeteren.</p>
<p><strong>Diensten:</strong> <?php echo esc_html( $analytics_str ); ?></p>

<h4>Marketing cookies</h4>
<p>Deze cookies worden gebruikt om advertenties af te stemmen op jouw interesses en om de effectiviteit van campagnes te meten. Ze kunnen ook worden gebruikt om het aantal keren dat je een advertentie te zien krijgt te beperken.</p>
<p><strong>Diensten:</strong> <?php echo esc_html( $marketing_str ); ?></p>

<h4>Functionele cookies</h4>
<p>Deze cookies maken uitgebreide functionaliteit en personalisatie mogelijk, zoals livechat, ingesloten video's en kaarten.</p>
<p><strong>Diensten:</strong> <?php echo esc_html( $functional_str ); ?></p>

<h3>4. Bewaartermijn</h3>

<p>Wij bewaren jouw persoonsgegevens niet langer dan strikt nodig is om de doelen te realiseren waarvoor je gegevens worden verzameld. Cookievoorkeuren worden maximaal <?php echo esc_html( $settings['consent_expiry_days'] ?? 365 ); ?> dagen bewaard.</p>

<h3>5. Delen van persoonsgegevens met derden</h3>

<p>Wij delen jouw persoonsgegevens alleen met derden als dit noodzakelijk is voor het uitvoeren van een overeenkomst met jou, of om te voldoen aan een wettelijke verplichting. Met bedrijven die jouw gegevens verwerken in onze opdracht sluiten wij een verwerkersovereenkomst om te zorgen voor eenzelfde niveau van beveiliging en vertrouwelijkheid van jouw gegevens.</p>

<h3>6. Gegevens inzien, aanpassen of verwijderen</h3>

<p>Je hebt het recht om je persoonsgegevens in te zien, te corrigeren of te verwijderen. Daarnaast heb je het recht om je eventuele toestemming voor de gegevensverwerking in te trekken of bezwaar te maken tegen de verwerking van jouw persoonsgegevens. Je kunt een verzoek tot inzage, correctie, verwijdering of gegevensoverdraging sturen naar [e-mailadres]. Om er zeker van te zijn dat het verzoek door jou is gedaan, vragen wij je een kopie van je identiteitsbewijs mee te sturen. <?php echo esc_html( $site_name ); ?> reageert zo snel mogelijk, maar uiterlijk binnen vier weken, op jouw verzoek.</p>

<h3>7. Beveiliging</h3>

<p>Wij nemen de bescherming van jouw gegevens serieus en nemen passende maatregelen om misbruik, verlies, onbevoegde toegang, ongewenste openbaarmaking en ongeoorloofde wijziging tegen te gaan. IP-adressen worden geanonimiseerd opgeslagen (SHA-256 hash) en zijn niet herleidbaar tot individuele personen.</p>

<h3>8. Autoriteit Persoonsgegevens</h3>

<p>Natuurlijk helpen wij je graag als je klachten hebt over de verwerking van je persoonsgegevens. Op grond van de privacywetgeving heb je ook het recht om een klacht in te dienen bij de Autoriteit Persoonsgegevens. Dat kan via de website <a href="https://autoriteitpersoonsgegevens.nl" target="_blank" rel="noopener">autoriteitpersoonsgegevens.nl</a>.</p>

<h3>9. Wijzigingen</h3>

<p>Wij behouden ons het recht voor om wijzigingen aan te brengen in dit privacybeleid. Het is daarom raadzaam om dit privacybeleid regelmatig te raadplegen, zodat je van eventuele wijzigingen op de hoogte bent.</p>
		<?php
		$policy_html = ob_get_clean();
		?>
		<div class="wscm-card">
			<div class="wscm-card-header">
				<h2><?php esc_html_e( 'Voorbeeld privacybeleid', 'webshake-consent-manager' ); ?></h2>
				<button type="button" id="wscm-copy-policy" class="button button-secondary">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Kopieer naar klembord', 'webshake-consent-manager' ); ?>
				</button>
			</div>
			<p class="description" style="margin-bottom: 16px;">
				<?php esc_html_e( 'Onderstaand privacybeleid is automatisch gegenereerd op basis van je gedetecteerde scripts. Kopieer de tekst en plak deze op een nieuwe pagina. Pas de tekst aan waar nodig (zoek naar "[e-mailadres]").', 'webshake-consent-manager' ); ?>
			</p>
			<div class="wscm-policy-preview" id="wscm-policy-preview">
				<?php echo wp_kses_post( $policy_html ); ?>
			</div>
		</div>
		<?php
	}
}
