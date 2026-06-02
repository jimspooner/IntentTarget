<?php
/**
 * IntentTarget Core — Main Plugin Class
 *
 * Bootstraps all sub-modules, registers activation/deactivation hooks,
 * and provides the central singleton instance.
 *
 * @package IntentTarget\Core
 */

namespace IntentTarget\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Base plugin directory.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Base plugin URL.
	 *
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * UI script version (auto-bumped via filemtime).
	 *
	 * @var string
	 */
	private string $ui_version;

	/**
	 * Sub-module instances.
	 *
	 * @var array<string,object>
	 */
	private array $modules = array();

	/**
	 * Private constructor — use ::instance().
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	private function __construct( string $plugin_file ) {
		$this->plugin_dir  = plugin_dir_path( $plugin_file );
		$this->plugin_url  = plugin_dir_url( $plugin_file );
		$this->ui_version  = $this->resolve_ui_version();

		$this->define_constants();
		$this->load_modules();
		$this->register_hooks();
	}

	/**
	 * Get or create the singleton instance.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @return Plugin
	 */
	public static function instance( string $plugin_file = '' ): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self( $plugin_file );
		}
		return self::$instance;
	}

	/**
	 * Resolve the frontend UI script version from filemtime.
	 *
	 * @return string
	 */
	private function resolve_ui_version(): string {
		$path = $this->plugin_dir . 'assets/js/itp-frontend-ui.js';
		return file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0';
	}

	/**
	 * Define plugin-wide constants.
	 *
	 * @return void
	 */
	private function define_constants(): void {
		if ( ! defined( 'LEE_DEV_PLUGIN_DIR' ) ) {
			define( 'LEE_DEV_PLUGIN_DIR', $this->plugin_dir );
		}
		if ( ! defined( 'LEE_DEV_PLUGIN_URL' ) ) {
			define( 'LEE_DEV_PLUGIN_URL', $this->plugin_url );
		}
		if ( ! defined( 'LEE_DEV_FRONTEND_UI_VERSION' ) ) {
			define( 'LEE_DEV_FRONTEND_UI_VERSION', $this->ui_version );
		}
	}

	/**
	 * Load sub-modules based on context.
	 *
	 * @return void
	 */
	private function load_modules(): void {
		// Core modules — always loaded.
		$this->modules['access']     = new Access(); Access::init();
		$this->modules['cron']       = new Cron();   Cron::init();
		$this->modules['hooks']      = new Hooks();  Hooks::init();
		$this->modules['intents']    = new Intents();
		$this->modules['parser']     = new Parser();
		$this->modules['propensity'] = new Propensity();
		$this->modules['transient']  = new Transient();

		// Admin modules — dashboard only.
		if ( is_admin() ) {
			$this->modules['admin_menu']     = new Admin\Menu();     Admin\Menu::init();
			$this->modules['admin_sidebar']  = new Admin\Sidebar();  Admin\Sidebar::init();
			$this->modules['admin_feedback'] = new Admin\Feedback(); Admin\Feedback::init();
		}

		// AJAX module.
		if ( wp_doing_ajax() ) {
			$this->modules['ajax'] = new Telemetry\Ajax(); Telemetry\Ajax::init();
		}

		// Telemetry modules — always loaded.
		$this->modules['advertising'] = new Telemetry\Advertising(); Telemetry\Advertising::init();
		$this->modules['tracker']     = new Telemetry\Tracker();     Telemetry\Tracker::init();
	}

	/**
	 * Register activation, deactivation, and core hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_runtime' ) );
		add_filter( 'script_loader_tag', array( $this, 'protect_runtime_script_tag' ), 10, 3 );
		add_shortcode( 'itp_preferences_dashboard', array( $this, 'render_preferences_shortcode' ) );
		add_shortcode( 'itp_user_preferences', array( $this, 'render_preferences_shortcode' ) );
		add_action( 'plugins_loaded', array( $this, 'init_woocommerce_integration' ) );
	}

	/**
	 * Enqueue the cache-safe frontend runtime.
	 *
	 * @return void
	 */
	public function enqueue_frontend_runtime(): void {
		if ( ! Access::has_authorised_licence() ) {
			return;
		}

		$handle = 'itp-frontend-ui';
		wp_register_script(
			$handle,
			$this->plugin_url . 'assets/js/itp-frontend-ui.js',
			array(),
			$this->ui_version,
			true
		);
		wp_localize_script( $handle, 'itpFrontendBootstrap', array(
			'restUrl' => esc_url_raw( rest_url( 'intenttarget/v1/bootstrap' ) ),
			'ajaxUrl' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'version' => $this->ui_version,
		) );
		wp_enqueue_script( $handle );
	}

	/**
	 * Protect the runtime script tag from third-party JS optimisers.
	 *
	 * @param string $tag    The HTML script tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script source URL.
	 * @return string
	 */
	public function protect_runtime_script_tag( string $tag, string $handle, string $src ): string {
		if ( $handle !== 'itp-frontend-ui' ) {
			return $tag;
		}
		if ( strpos( $tag, 'data-no-optimize' ) !== false ) {
			return $tag;
		}
		return str_replace(
			'<script ',
			'<script data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-cfasync="false" ',
			$tag
		);
	}

	/**
	 * Shortcode handler for the preferences dashboard.
	 *
	 * @return string
	 */
	public function render_preferences_shortcode(): string {
		// Delegate to the advertising module’s preferences renderer.
		if ( isset( $this->modules['advertising'] ) && method_exists( $this->modules['advertising'], 'render_preferences' ) ) {
			return $this->modules['advertising']->render_preferences();
		}
		return '';
	}

	/**
	 * Initialise WooCommerce integration.
	 *
	 * @return void
	 */
	public function init_woocommerce_integration(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_wc_interests_menu_item' ) );
		add_action( 'init', array( $this, 'add_wc_interests_endpoint' ) );
		add_action( 'woocommerce_account_interests_endpoint', array( $this, 'render_wc_interests_tab' ) );
	}

	/**
	 * Add "My Interests" to the WooCommerce account menu.
	 *
	 * @param array $items Existing menu items.
	 * @return array
	 */
	public function add_wc_interests_menu_item( array $items ): array {
		if ( ! Access::is_ready() ) {
			return $items;
		}
		$new_items = array();
		foreach ( $items as $key => $item ) {
			if ( $key === 'customer-logout' ) {
				$new_items['interests'] = __( 'My Interests', 'intenttarget-pro' );
			}
			$new_items[ $key ] = $item;
		}
		return $new_items;
	}

	/**
	 * Register the WooCommerce account endpoint.
	 *
	 * @return void
	 */
	public function add_wc_interests_endpoint(): void {
		add_rewrite_endpoint( 'interests', EP_PAGES );
	}

	/**
	 * Render the WooCommerce "My Interests" tab content.
	 *
	 * @return void
	 */
	public function render_wc_interests_tab(): void {
		if ( shortcode_exists( 'itp_preferences_dashboard' ) ) {
			echo do_shortcode( '[itp_preferences_dashboard]' );
		} elseif ( shortcode_exists( 'itp_user_preferences' ) ) {
			echo do_shortcode( '[itp_user_preferences]' );
		}
	}

	/**
	 * Plugin activation routine.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( method_exists( Hooks::class, 'setup_search_insights_table' ) ) {
			Hooks::setup_search_insights_table();
		}

		if ( ! wp_next_scheduled( 'lee_dev_cron_batch_scan_event_9201' ) ) {
			wp_schedule_event( time(), 'hourly', 'lee_dev_cron_batch_scan_event_9201' );
		}
		if ( ! wp_next_scheduled( 'itp_hourly_tracking_batch_event' ) ) {
			wp_schedule_event( time(), 'hourly', 'itp_hourly_tracking_batch_event' );
		}
	}

	/**
	 * Plugin deactivation routine.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'lee_dev_cron_batch_scan_event_9201' );
		wp_clear_scheduled_hook( 'itp_hourly_tracking_batch_event' );
	}

	/**
	 * Get a loaded module instance.
	 *
	 * @param string $name Module key.
	 * @return object|null
	 */
	public function get_module( string $name ): ?object {
		return $this->modules[ $name ] ?? null;
	}
}
