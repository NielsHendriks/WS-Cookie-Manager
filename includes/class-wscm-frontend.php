<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSCM_Frontend {

	private $settings;

	public function __construct() {
		$this->settings = get_option( 'wscm_settings', [] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer', [ $this, 'render_banner' ], 999 );
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'wscm-frontend', WSCM_PLUGIN_URL . 'frontend/css/banner.css', [], WSCM_VERSION );

		wp_enqueue_script( 'wscm-frontend', WSCM_PLUGIN_URL . 'frontend/js/consent.js', [], WSCM_VERSION, true );

		$categories = $this->get_active_categories();
		wp_localize_script( 'wscm-frontend', 'wscm_config', [
			'expiry_days'   => intval( $this->settings['consent_expiry_days'] ?? 365 ),
			'categories'    => $categories,
			'position'      => $this->settings['banner_position'] ?? 'bottom',
			'primary_color' => $this->settings['primary_color'] ?? '#2563eb',
			'geo_targeting' => $this->settings['geo_targeting'] ?? 'all',
			'rest_url'      => rest_url( 'wscm/v1/consent' ),
		] );
	}

	private function get_active_categories() {
		$detected = $this->settings['detected_scripts'] ?? [];
		$cats     = [ 'necessary' => true ];

		foreach ( $detected as $script ) {
			if ( ! empty( $script['blocked'] ) && ! empty( $script['category'] ) ) {
				$cats[ $script['category'] ] = true;
			}
		}

		return array_keys( $cats );
	}

	public function render_banner() {
		if ( is_admin() ) {
			return;
		}

		$s = $this->settings;
		$detected = $s['detected_scripts'] ?? [];
		$has_analytics  = false;
		$has_marketing  = false;
		$has_functional = false;

		$analytics_services  = [];
		$marketing_services  = [];
		$functional_services = [];

		foreach ( $detected as $script ) {
			if ( empty( $script['blocked'] ) ) {
				continue;
			}
			switch ( $script['category'] ) {
				case 'analytics':
					$has_analytics = true;
					$analytics_services[] = $script['name'];
					break;
				case 'marketing':
					$has_marketing = true;
					$marketing_services[] = $script['name'];
					break;
				case 'functional':
					$has_functional = true;
					$functional_services[] = $script['name'];
					break;
			}
		}

		$position_class = 'wscm-pos-' . sanitize_html_class( $s['banner_position'] ?? 'bottom' );
		$primary_color  = esc_attr( $s['primary_color'] ?? '#2563eb' );
		$dark_mode      = $s['dark_mode'] ?? 'off';
		$dark_class     = $dark_mode === 'on' ? ' wscm-dark' : '';
		$dark_auto_attr = $dark_mode === 'auto' ? ' data-wscm-dark="auto"' : '';
		?>
		<div id="wscm-banner" class="wscm-banner <?php echo $position_class . $dark_class; ?>" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Cookie toestemming', 'webshake-consent-manager' ); ?>"<?php echo $dark_auto_attr; ?> style="display:none; --wscm-primary: <?php echo $primary_color; ?>;">
			<div class="wscm-banner-inner">
				<div class="wscm-banner-body">
					<div class="wscm-banner-content">
						<div class="wscm-banner-header">
							<div class="wscm-banner-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><circle cx="8.5" cy="8.5" r="1"/><circle cx="7" cy="13" r="1"/><circle cx="12" cy="16" r="1"/></svg>
							</div>
							<span class="wscm-banner-title"><?php echo esc_html( $s['banner_title'] ?? '' ); ?></span>
							<button type="button" id="wscm-close-btn" class="wscm-close-btn" aria-label="<?php esc_attr_e( 'Sluiten', 'webshake-consent-manager' ); ?>" style="display:none;">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
							</button>
						</div>
						<p class="wscm-banner-desc"><?php echo esc_html( $s['banner_description'] ?? '' ); ?></p>

						<div class="wscm-categories" id="wscm-categories" style="display:none;">
							<div class="wscm-category-item wscm-category-necessary">
								<label>
									<span class="wscm-cat-name"><?php esc_html_e( 'Noodzakelijk', 'webshake-consent-manager' ); ?><span class="wscm-cat-badge"><?php esc_html_e( 'Altijd aan', 'webshake-consent-manager' ); ?></span></span>
									<input type="checkbox" checked disabled value="necessary">
								</label>
								<p class="wscm-cat-desc"><?php esc_html_e( 'Essentiële cookies die nodig zijn voor het functioneren van de website.', 'webshake-consent-manager' ); ?></p>
							</div>

							<?php if ( $has_analytics ) : ?>
							<div class="wscm-category-item">
								<label>
									<span class="wscm-cat-name"><?php esc_html_e( 'Analytisch', 'webshake-consent-manager' ); ?></span>
									<input type="checkbox" value="analytics" class="wscm-cat-toggle">
								</label>
								<p class="wscm-cat-desc">
									<?php esc_html_e( 'Helpen ons te begrijpen hoe bezoekers de website gebruiken.', 'webshake-consent-manager' ); ?>
									<span class="wscm-services"><?php echo esc_html( implode( ', ', array_unique( $analytics_services ) ) ); ?></span>
								</p>
							</div>
							<?php endif; ?>

							<?php if ( $has_marketing ) : ?>
							<div class="wscm-category-item">
								<label>
									<span class="wscm-cat-name"><?php esc_html_e( 'Marketing', 'webshake-consent-manager' ); ?></span>
									<input type="checkbox" value="marketing" class="wscm-cat-toggle">
								</label>
								<p class="wscm-cat-desc">
									<?php esc_html_e( 'Worden gebruikt voor gepersonaliseerde advertenties en het meten van conversies.', 'webshake-consent-manager' ); ?>
									<span class="wscm-services"><?php echo esc_html( implode( ', ', array_unique( $marketing_services ) ) ); ?></span>
								</p>
							</div>
							<?php endif; ?>

							<?php if ( $has_functional ) : ?>
							<div class="wscm-category-item">
								<label>
									<span class="wscm-cat-name"><?php esc_html_e( 'Functioneel', 'webshake-consent-manager' ); ?></span>
									<input type="checkbox" value="functional" class="wscm-cat-toggle">
								</label>
								<p class="wscm-cat-desc">
									<?php esc_html_e( 'Voor uitgebreide functionaliteit zoals live chat en ingesloten content.', 'webshake-consent-manager' ); ?>
									<span class="wscm-services"><?php echo esc_html( implode( ', ', array_unique( $functional_services ) ) ); ?></span>
								</p>
							</div>
							<?php endif; ?>
						</div>
					</div>

					<div class="wscm-banner-actions">
						<button type="button" class="wscm-btn wscm-btn-manage" id="wscm-manage-btn">
							<?php esc_html_e( 'Voorkeuren beheren', 'webshake-consent-manager' ); ?>
						</button>
						<button type="button" class="wscm-btn wscm-btn-reject" id="wscm-reject-btn">
							<?php echo esc_html( $s['reject_all_label'] ?? 'Alles weigeren' ); ?>
						</button>
						<button type="button" class="wscm-btn wscm-btn-accept" id="wscm-accept-btn">
							<?php echo esc_html( $s['accept_all_label'] ?? 'Alles accepteren' ); ?>
						</button>
						<button type="button" class="wscm-btn wscm-btn-save" id="wscm-save-btn" style="display:none;">
							<?php echo esc_html( $s['save_preferences_label'] ?? 'Voorkeuren opslaan' ); ?>
						</button>
					</div>
				</div>
				<div class="wscm-banner-footer">
					<span><?php esc_html_e( 'Mogelijk gemaakt door', 'webshake-consent-manager' ); ?></span>
					<a href="https://webshake.nl" target="_blank" rel="noopener">Webshake</a>
				</div>
			</div>
		</div>
		<div id="wscm-overlay" class="wscm-overlay" style="display:none;"></div>

		<button type="button" id="wscm-reopen" class="wscm-reopen<?php echo $dark_class; ?>" aria-label="<?php esc_attr_e( 'Cookie instellingen', 'webshake-consent-manager' ); ?>"<?php echo $dark_auto_attr; ?> style="display:none; --wscm-primary: <?php echo $primary_color; ?>;">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><circle cx="8.5" cy="8.5" r="1"/><circle cx="7" cy="13" r="1"/><circle cx="12" cy="16" r="1"/></svg>
		</button>
		<?php
	}
}
