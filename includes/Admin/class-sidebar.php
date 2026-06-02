<?php
namespace IntentTarget\Core\Admin;
if ( ! defined( 'ABSPATH' ) ) exit;
class Sidebar {
	public static function init(): void {
		$legacy = LEE_DEV_PLUGIN_DIR . 'admin/view-sidebar-box.php';
		if ( file_exists( $legacy ) ) require_once $legacy;
	}
}
