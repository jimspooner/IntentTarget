<?php
namespace IntentTarget\Core\Telemetry;
if ( ! defined( 'ABSPATH' ) ) exit;
class Ajax {
	public static function init(): void {
		$legacy = LEE_DEV_PLUGIN_DIR . 'telemetry/class-lee-dev-ajax.php';
		if ( file_exists( $legacy ) ) require_once $legacy;
	}
}
