<?php
namespace IntentTarget\Core\Admin;
if ( ! defined( 'ABSPATH' ) ) exit;
class Feedback {
	public static function init(): void {
		$legacy = LEE_DEV_PLUGIN_DIR . 'admin/class-lee-dev-feedback.php';
		if ( file_exists( $legacy ) ) require_once $legacy;
	}
}
