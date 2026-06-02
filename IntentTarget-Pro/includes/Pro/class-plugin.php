<?php
namespace IntentTarget\Pro;
if ( ! defined( 'ABSPATH' ) ) exit;
class Plugin {
	private static ?Plugin $instance = null;
	public static function instance(): Plugin {
		if ( null === self::$instance ) self::$instance = new self();
		return self::$instance;
	}
	private function __construct() {
		if ( ! defined( 'LEE_DEV_PRO_PLUGIN_SLUG' ) ) define( 'LEE_DEV_PRO_PLUGIN_SLUG', 'pro' );
		if ( ! defined( 'LEE_DEV_PRO_LICENCE_PREFIX' ) ) define( 'LEE_DEV_PRO_LICENCE_PREFIX', 'ITPP-' );
		if ( ! defined( 'LEE_DEV_PRO_ACTIVATION_ENDPOINT' ) ) define( 'LEE_DEV_PRO_ACTIVATION_ENDPOINT', 'https://intenttargetpro.com/wp-json/intenttarget-hub/v1/activate' );
		if ( ! defined( 'LEE_DEV_PRO_VERIFICATION_ENDPOINT' ) ) define( 'LEE_DEV_PRO_VERIFICATION_ENDPOINT', 'https://intenttargetpro.com/wp-json/intenttarget/v1/verify' );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}
	public function init(): void {
		if ( ! function_exists( 'lee_dev_render_intent_popup_2841' ) ) return;
		add_action( 'itp_core_dashboard_tabs', array( $this, 'inject_design_tab' ) );
		add_action( 'itp_core_dashboard_tab_content_design_pro', array( $this, 'render_design_tab' ) );
		if ( function_exists( 'lee_dev_is_addon_active_3812' ) && lee_dev_is_addon_active_3812( LEE_DEV_PRO_PLUGIN_SLUG ) ) {
			add_filter( 'itp_show_popup_branding', '__return_false' );
			add_filter( 'itp_core_design_colors', array( Styling::class, 'apply_design_overrides' ) );
		}
	}
	public function inject_design_tab( string $active_tab ): void {
		$is_active = ( $active_tab === 'design_pro' ) ? 'nav-tab-active' : '';
		echo '<a href="?page=itp-search-dashboard&tab=design_pro" class="nav-tab ' . esc_attr( $is_active ) . '">' . esc_html__( 'Advert Styling (Pro)', 'intenttarget-pro' ) . '</a>';
	}
	public function render_design_tab(): void {
		Licence::render_licence_panel();
		if ( ! Licence::has_authorised() ) {
			?>
			<div style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #d63638;padding:18px 22px;margin-top:20px;border-radius:4px;max-width:800px;">
				<h3 style="margin-top:0;"><?php echo esc_html__( 'IntentTarget Pro Add-On Licence Required', 'intenttarget-pro' ); ?></h3>
				<p><?php echo esc_html__( 'The advert styling controls are disabled until a valid IntentTarget Pro licence code (prefix ITPP-) is activated above.', 'intenttarget-pro' ); ?></p>
				<p><a href="https://intenttargetpro.co.uk/pro" target="_blank" rel="noopener" class="button button-primary"><?php echo esc_html__( 'Purchase IntentTarget Pro Add-On', 'intenttarget-pro' ); ?></a></p>
			</div>
			<?php
			return;
		}
		Styling::render_design_form();
	}
	public static function activate(): void {
		if ( ! wp_next_scheduled( 'itp_pro_daily_licence_verification_event' ) ) {
			wp_schedule_event( time(), 'daily', 'itp_pro_daily_licence_verification_event' );
		}
		if ( ! get_option( 'lee_dev_pro_licence_status' ) ) {
			add_option( 'lee_dev_pro_licence_status', 'unauthorised' );
		}
		if ( function_exists( 'lee_dev_pro_install_roi_table_3956' ) ) {
			lee_dev_pro_install_roi_table_3956();
		}
	}
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'itp_pro_daily_licence_verification_event' );
		if ( $timestamp ) wp_unschedule_event( $timestamp, 'itp_pro_daily_licence_verification_event' );
	}
}
