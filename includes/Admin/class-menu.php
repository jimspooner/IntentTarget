<?php
namespace IntentTarget\Core\Admin;
if ( ! defined( 'ABSPATH' ) ) exit;
class Menu {
	public static function init(): void {
		// Load legacy procedural file immediately so its add_action calls register in time.
		$legacy = LEE_DEV_PLUGIN_DIR . 'admin/class-lee-dev-menu.php';
		if ( file_exists( $legacy ) ) require_once $legacy;
	}
}
