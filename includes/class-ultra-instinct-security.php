<?php
/**
 * Ultra Instinct Security Class
 *
 * Enhanced security with agent authentication and request validation
 *
 * @package UltraInstinct
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ultra Instinct Security class
 */
class Ultra_Instinct_Security {

    /**
     * Single instance
     *
     * @var Ultra_Instinct_Security
     */
    private static $instance = null;

    /**
     * Encryption key
     *
     * @var string
     */
    private $encryption_key;

    /**
     * Get instance
     *
     * @return Ultra_Instinct_Security
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->encryption_key = $this->get_encryption_key();
    }

    /**
     * Generate a secure API key
     *
     * @return string
     */
    public function generate_api_key() {
        // Generate a 64-character random string
        $api_key = bin2hex( random_bytes( 32 ) );
        
        // Add timestamp and site-specific data for uniqueness
        $site_data = get_site_url() . time();
        $api_key .= hash( 'sha256', $site_data );
        
        return substr( $api_key, 0, 64 );
    }

    /**
     * Hash API key for storage
     *
     * @param string $api_key The API key to hash.
     * @return string
     */
    public function hash_api_key( $api_key ) {
        return hash( 'sha256', $api_key . $this->get_salt() );
    }

    /**
     * Verify API key
     *
     * @param string $provided_key The API key to verify.
     * @return bool
     */
    public function verify_api_key( $provided_key ) {
        $stored_hash = get_option( 'ultra_instinct_api_key_hash' );
        
        if ( empty( $stored_hash ) || empty( $provided_key ) ) {
            return false;
        }

        $provided_hash = $this->hash_api_key( $provided_key );
        
        return hash_equals( $stored_hash, $provided_hash );
    }

    /**
     * Store API key securely
     *
     * @param string $api_key The API key to store.
     * @return bool
     */
    public function store_api_key( $api_key ) {
        $hashed_key = $this->hash_api_key( $api_key );
        
        // Store hashed version
        $stored = update_option( 'ultra_instinct_api_key_hash', $hashed_key );
        
        // Update connection status
        if ( $stored ) {
            update_option( 'ultra_instinct_connection_status', 'connected' );
            if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
                Ultra_Instinct_Logger::log( 'API key generated and stored securely', 'info' );
            }
        }
        
        return $stored;
    }

    /**
     * Revoke API key
     *
     * @return bool
     */
    public function revoke_api_key() {
        $deleted = delete_option( 'ultra_instinct_api_key_hash' );
        
        if ( $deleted ) {
            update_option( 'ultra_instinct_connection_status', 'disconnected' );
            if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
                Ultra_Instinct_Logger::log( 'API key revoked', 'info' );
            }
        }
        
        return $deleted;
    }

    /**
     * Check if API key exists
     *
     * @return bool
     */
    public function has_api_key() {
        $stored_hash = get_option( 'ultra_instinct_api_key_hash' );
        return ! empty( $stored_hash );
    }

    /**
     * Authenticate REST request with enhanced validation
     *
     * @param WP_REST_Request $request The REST request.
     * @return bool|WP_Error
     */
    public function authenticate_request( $request ) {
        // Get API key from header
        $api_key = $request->get_header( 'X-Ultra-Instinct-Key' );
        
        if ( empty( $api_key ) ) {
            // Try alternative header
            $api_key = $request->get_header( 'Authorization' );
            if ( ! empty( $api_key ) && strpos( $api_key, 'Bearer ' ) === 0 ) {
                $api_key = substr( $api_key, 7 );
            }
        }

        if ( empty( $api_key ) ) {
            return new WP_Error(
                'ultra_instinct_no_key',
                __( 'API key is required', 'ultra-instinct' ),
                array( 'status' => 401 )
            );
        }

        if ( ! $this->verify_api_key( $api_key ) ) {
            if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
                Ultra_Instinct_Logger::log( 
                    'Invalid API key attempt from IP: ' . $this->get_client_ip(), 
                    'warning',
                    array( 'ip' => $this->get_client_ip(), 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '' )
                );
            }
            
            return new WP_Error(
                'ultra_instinct_invalid_key',
                __( 'Invalid API key', 'ultra-instinct' ),
                array( 'status' => 401 )
            );
        }

        // Additional security checks
        if ( ! $this->validate_request_signature( $request ) ) {
            if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
                Ultra_Instinct_Logger::log( 
                    'Request signature validation failed', 
                    'warning',
                    array( 'ip' => $this->get_client_ip() )
                );
            }
        }

        return true;
    }

    /**
     * Validate request signature for enhanced security
     *
     * @param WP_REST_Request $request The REST request.
     * @return bool
     */
    private function validate_request_signature( $request ) {
        $signature = $request->get_header( 'X-Ultra-Instinct-Signature' );
        
        if ( empty( $signature ) ) {
            return true; // Signature is optional for backward compatibility
        }

        $body = $request->get_body();
        $timestamp = $request->get_header( 'X-Ultra-Instinct-Timestamp' );
        
        if ( empty( $timestamp ) ) {
            return false;
        }

        // Check timestamp (prevent replay attacks)
        $current_time = time();
        if ( abs( $current_time - intval( $timestamp ) ) > 300 ) { // 5 minutes tolerance
            return false;
        }

        $settings = get_option( 'ultra_instinct_settings', array() );
        $secret = $settings['webhook_secret'] ?? '';
        
        if ( empty( $secret ) ) {
            return false;
        }

        $expected_signature = hash_hmac( 'sha256', $timestamp . $body, $secret );
        
        return hash_equals( $expected_signature, $signature );
    }

    /**
     * Generate request signature for outgoing requests
     *
     * @param string $body Request body.
     * @param int    $timestamp Timestamp.
     * @return string
     */
    public function generate_request_signature( $body, $timestamp = null ) {
        if ( null === $timestamp ) {
            $timestamp = time();
        }

        $settings = get_option( 'ultra_instinct_settings', array() );
        $secret = $settings['webhook_secret'] ?? '';
        
        return hash_hmac( 'sha256', $timestamp . $body, $secret );
    }

    /**
     * Get encryption key
     *
     * @return string
     */
    private function get_encryption_key() {
        // Use WordPress salts for encryption key
        $key_data = SECURE_AUTH_KEY . LOGGED_IN_KEY . NONCE_KEY;
        return hash( 'sha256', $key_data );
    }

    /**
     * Get salt for hashing
     *
     * @return string
     */
    private function get_salt() {
        return SECURE_AUTH_SALT . LOGGED_IN_SALT . NONCE_SALT;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
        
        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) === true ) {
                foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
                    $ip = trim( $ip );
                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Enhanced rate limit check with agent tracking
     *
     * @param string $ip The IP address.
     * @param string $agent_id Optional agent ID for tracking.
     * @return bool
     */
    public function check_rate_limit( $ip, $agent_id = null ) {
        $settings = get_option( 'ultra_instinct_settings', array() );
        $max_requests = $settings['rate_limit_requests'] ?? 100;
        $window = $settings['rate_limit_window'] ?? 3600;

        $transient_key = 'ultra_instinct_rate_limit_' . md5( $ip );
        if ( $agent_id ) {
            $transient_key .= '_' . md5( $agent_id );
        }

        $attempts = get_transient( $transient_key );
        
        if ( false === $attempts ) {
            $attempts = 0;
        }
        
        $attempts++;
        
        if ( $attempts > $max_requests ) {
            if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
                Ultra_Instinct_Logger::log( 
                    "Rate limit exceeded for IP: {$ip}" . ( $agent_id ? " (Agent: {$agent_id})" : '' ), 
                    'warning',
                    array( 'ip' => $ip, 'agent_id' => $agent_id, 'attempts' => $attempts )
                );
            }
            return false;
        }
        
        set_transient( $transient_key, $attempts, $window );
        
        return true;
    }

    /**
     * Validate agent capabilities
     *
     * @param string $agent_id Agent ID.
     * @param string $capability Required capability.
     * @return bool
     */
    public function validate_agent_capability( $agent_id, $capability ) {
        if ( ! class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            return false;
        }
        
        $agent_connector = Ultra_Instinct_Agent_Connector::get_instance();
        $agent = $agent_connector->get_agent_by_id( $agent_id );
        
        if ( ! $agent ) {
            return false;
        }

        $capabilities = $agent['capabilities'] ?? array();
        
        return in_array( $capability, $capabilities, true );
    }

    /**
     * Log security event
     *
     * @param string $event Event description.
     * @param string $level Log level.
     * @param array  $context Additional context.
     */
    public function log_security_event( $event, $level = 'warning', $context = array() ) {
        if ( ! class_exists( 'Ultra_Instinct_Logger' ) ) {
            return;
        }
        
        $context['security_event'] = true;
        $context['ip'] = $this->get_client_ip();
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        Ultra_Instinct_Logger::log( $event, $level, $context );
    }
}
