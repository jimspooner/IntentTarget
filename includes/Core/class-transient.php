<?php
namespace IntentTarget\Core;
if ( ! defined( 'ABSPATH' ) ) exit;
class Transient {
	public static function get( string $key, $default = false ) { return get_transient( 'itp_' . $key ) ?: $default; }
	public static function set( string $key, $value, int $expiration = 3600 ): bool { return set_transient( 'itp_' . $key, $value, $expiration ); }
	public static function delete( string $key ): bool { return delete_transient( 'itp_' . $key ); }
}
