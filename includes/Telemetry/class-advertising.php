<?php
namespace IntentTarget\Core\Telemetry;
if ( ! defined( 'ABSPATH' ) ) exit;
class Advertising {
	public static function init(): void {
		$legacy = LEE_DEV_PLUGIN_DIR . 'telemetry/class-lee-dev-advertising.php';
		if ( file_exists( $legacy ) ) require_once $legacy;
	}
	public function render_preferences(): string {
		if ( function_exists( 'lee_dev_render_preferences_panel_6382' ) ) {
			return lee_dev_render_preferences_panel_6382();
		}
		return '';
	}
}
