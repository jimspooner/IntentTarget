<?php
namespace IntentTarget\Core\Telemetry;
if ( ! defined( 'ABSPATH' ) ) exit;
class Tracker {
	public static function init(): void {
		$legacy = LEE_DEV_PLUGIN_DIR . 'telemetry/class-lee-dev-tracker.php';
		if ( file_exists( $legacy ) ) require_once $legacy;
	}
}
