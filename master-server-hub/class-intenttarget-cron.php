<?php
namespace IntentTarget\MasterHub;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cron {
    public function init() {
        add_action( 'init', [ $this, 'schedule_events' ] );
        add_action( 'itp_hub_daily_expiration_check', [ $this, 'process_expirations' ] );
    }

    public function schedule_events() {
        if ( ! wp_next_scheduled( 'itp_hub_daily_expiration_check' ) ) {
            wp_schedule_event( time(), 'daily', 'itp_hub_daily_expiration_check' );
        }
    }

    public function process_expirations() {
        global $wpdb;
        $table_name = Config::get_table_name();
        $now        = current_time( 'mysql' );
        
        $wpdb->query( $wpdb->prepare(
            "UPDATE $table_name 
             SET status = 'deactivated', 
                 deactivated_at = %s, 
                 feedback_reason = 'expired', 
                 feedback_text = 'Licence reached 1 year expiration date.' 
             WHERE status = 'active' 
               AND expires_at IS NOT NULL 
               AND expires_at <= %s",
            $now, $now
        ) );
    }
}
