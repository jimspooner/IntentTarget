<?php
namespace IntentTarget\Core;
if ( ! defined( 'ABSPATH' ) ) exit;
class Propensity {
	public static function get_global_priority_order(): array {
		$stored = get_option( 'itp_priority_order', array() );
		return is_array( $stored ) ? $stored : array( 'driving_sales', 'educating_audiences', 'generating_leads', 'customer_support' );
	}
	public static function get_valid_site_intents(): array {
		return apply_filters( 'lee_dev_valid_site_intents_6048', array(
			'driving_sales'      => 'Driving Sales',
			'educating_audiences'=> 'Educating Audiences',
			'generating_leads'   => 'Generating Leads',
			'customer_support'   => 'Customer Support',
		) );
	}
	public static function calculate_group_propensity( string $group_key, int $user_id ): int {
		$user_id = absint( $user_id ); if ( $user_id <= 0 ) return 0;
		$map = get_user_meta( $user_id, 'user_interest_map', true );
		if ( ! is_array( $map ) || ! isset( $map[ $group_key ] ) || ! is_array( $map[ $group_key ] ) ) return 0;
		$score = isset( $map[ $group_key ]['score'] ) ? (int) $map[ $group_key ]['score'] : 0;
		return max( 0, $score );
	}
	public static function get_user_propensity_summary( int $user_id ): array {
		$user_id = absint( $user_id ); if ( $user_id <= 0 ) return array();
		$map = get_user_meta( $user_id, 'user_interest_map', true );
		if ( ! is_array( $map ) ) return array();
		$summary = array(); foreach ( $map as $group => $data ) { $summary[ $group ] = isset( $data['score'] ) ? (int) $data['score'] : 0; }
		return $summary;
	}
}
