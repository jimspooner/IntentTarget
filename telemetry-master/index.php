<?php
// =========================================================================
// INTENT TARGET PRO - TELEMETRY CATCHER
// =========================================================================

// 1. Database Configuration
$db_host = 'localhost';
$db_name = 'your_database_name';
$db_user = 'your_database_user';
$db_pass = 'your_secure_password';

// 2. Set headers to strictly accept POST requests and return JSON
header( 'Content-Type: application/json' );
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    echo json_encode( [ 'status' => 'error', 'message' => 'Method not allowed.' ] );
    exit;
}

$expected_secret = 'your_code';
// Check if the header was sent (PHP automatically pre-pends HTTP_ and replaces hyphens with underscores)
$provided_secret = $_SERVER['HTTP_X_ITP_TELEMETRY_TOKEN'] ?? '';
// Use hash_equals to prevent timing attacks
if ( ! hash_equals( $expected_secret, $provided_secret ) ) {
    http_response_code( 401 ); // Unauthorised
    echo json_encode( [ 'status' => 'error', 'message' => 'Unauthorised request.' ] );
    exit; // Immediately stop the script from running
}

// 3. Capture and decode the incoming JSON payload
$raw_data = file_get_contents( 'php://input' );
$payload  = json_decode( $raw_data, true );

// 4. Validate essential data (Fail early if 'domain' is missing)
if ( ! isset( $payload['domain'] ) || empty( $payload['domain'] ) ) {
    http_response_code( 400 );
    echo json_encode( [ 'status' => 'error', 'message' => 'Missing domain parameter.' ] );
    exit;
}

// 5. Connect to the database and process the payload
try {
    $pdo = new PDO( 
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", 
        $db_user, 
        $db_pass, 
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // Prepare the upsert query
    // If the domain exists, it updates the data. If it is new, it inserts it.
    $sql = "INSERT INTO itp_telemetry_data 
            (domain, php_version, wp_version, wc_active, category_count, posts_count, licence_key, licence_status, last_ping) 
            VALUES 
            (:domain, :php_version, :wp_version, :wc_active, :category_count, :posts_count, :licence_key, :licence_status, :last_ping)
            ON DUPLICATE KEY UPDATE 
            php_version    = VALUES(php_version),
            wp_version     = VALUES(wp_version),
            wc_active      = VALUES(wc_active),
            category_count = VALUES(category_count),
            posts_count    = VALUES(posts_count),
            licence_key    = VALUES(licence_key),
            licence_status = VALUES(licence_status),
            last_ping      = VALUES(last_ping)";

    $stmt = $pdo->prepare( $sql );

    // Execute with sanitized data bindings
    $stmt->execute([
        ':domain'         => filter_var( $payload['domain'], FILTER_SANITIZE_URL ),
        ':php_version'    => sanitize_text( $payload['php_version'] ?? '' ),
        ':wp_version'     => sanitize_text( $payload['wp_version'] ?? '' ),
        ':wc_active'      => ( isset( $payload['wc_active'] ) && $payload['wc_active'] === 'yes' ) ? 'yes' : 'no',
        ':category_count' => (int) ( $payload['category_count'] ?? 0 ),
        ':posts_count'    => (int) ( $payload['posts_count'] ?? 0 ),
        ':licence_key'    => sanitize_text( $payload['licence_key'] ?? '' ),
        ':licence_status' => sanitize_text( $payload['licence_status'] ?? 'unauthorised' ),
        ':last_ping'      => (int) ( $payload['timestamp'] ?? time() )
    ]);

    // Return success to the WordPress site
    http_response_code( 200 );
    echo json_encode( [ 'status' => 'success', 'message' => 'Telemetry logged.' ] );

} catch ( PDOException $e ) {
    // Fail silently for the end user, but you could log $e->getMessage() locally here if needed
    http_response_code( 500 );
    echo json_encode( [ 'status' => 'error', 'message' => 'Database error.' ] );
}

// Simple helper function to strip bad characters
function sanitize_text( $string ) {
    return htmlspecialchars( strip_tags( $string ), ENT_QUOTES, 'UTF-8' );
}
