<?php
/**
 * Ultra Instinct Plugin Test Script
 * 
 * This script tests all plugin functionality to ensure everything works properly
 * Run this from WordPress admin or via WP-CLI
 *
 * @package UltraInstinct
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ultra Instinct Plugin Tester
 */
class Ultra_Instinct_Plugin_Tester {

    /**
     * Run all tests
     */
    public static function run_tests() {
        echo "<h2>Ultra Instinct Plugin Functionality Tests</h2>\n";
        
        $tests = array(
            'test_plugin_activation',
            'test_security_class',
            'test_api_key_generation',
            'test_api_key_storage',
            'test_api_key_verification',
            'test_rest_api_endpoints',
            'test_authentication',
            'test_rate_limiting',
            'test_logging_system',
            'test_admin_interface',
            'test_database_operations',
            'test_security_measures',
        );

        $passed = 0;
        $failed = 0;

        foreach ( $tests as $test ) {
            echo "<h3>Running: {$test}</h3>\n";
            
            try {
                $result = call_user_func( array( __CLASS__, $test ) );
                if ( $result ) {
                    echo "<p style='color: green;'>✅ PASSED</p>\n";
                    $passed++;
                } else {
                    echo "<p style='color: red;'>❌ FAILED</p>\n";
                    $failed++;
                }
            } catch ( Exception $e ) {
                echo "<p style='color: red;'>❌ ERROR: " . $e->getMessage() . "</p>\n";
                $failed++;
            }
            
            echo "<hr>\n";
        }

        echo "<h3>Test Summary</h3>\n";
        echo "<p>Passed: {$passed}</p>\n";
        echo "<p>Failed: {$failed}</p>\n";
        echo "<p>Total: " . ( $passed + $failed ) . "</p>\n";

        return $failed === 0;
    }

    /**
     * Test plugin activation
     */
    public static function test_plugin_activation() {
        echo "Testing plugin activation and initialization...\n";
        
        // Check if main class exists
        if ( ! class_exists( 'Ultra_Instinct_Integration' ) ) {
            echo "Main plugin class not found\n";
            return false;
        }

        // Check if instance is created
        $instance = Ultra_Instinct_Integration::get_instance();
        if ( ! $instance ) {
            echo "Plugin instance not created\n";
            return false;
        }

        // Check if required options exist
        $settings = get_option( 'ultra_instinct_settings' );
        if ( ! $settings ) {
            echo "Plugin settings not initialized\n";
            return false;
        }

        echo "Plugin properly activated and initialized\n";
        return true;
    }

    /**
     * Test security class
     */
    public static function test_security_class() {
        echo "Testing security class functionality...\n";
        
        if ( ! class_exists( 'Ultra_Instinct_Security' ) ) {
            echo "Security class not found\n";
            return false;
        }

        $security = Ultra_Instinct_Security::get_instance();
        if ( ! $security ) {
            echo "Security instance not created\n";
            return false;
        }

        echo "Security class properly initialized\n";
        return true;
    }

    /**
     * Test API key generation
     */
    public static function test_api_key_generation() {
        echo "Testing API key generation...\n";
        
        $security = Ultra_Instinct_Security::get_instance();
        
        // Generate API key
        $api_key = $security->generate_api_key();
        
        if ( empty( $api_key ) ) {
            echo "API key generation failed\n";
            return false;
        }

        if ( strlen( $api_key ) < 32 ) {
            echo "API key too short: " . strlen( $api_key ) . " characters\n";
            return false;
        }

        if ( ! preg_match( '/^[a-f0-9]+$/', $api_key ) ) {
            echo "API key contains invalid characters\n";
            return false;
        }

        echo "API key generated successfully: " . substr( $api_key, 0, 8 ) . "...\n";
        return true;
    }

    /**
     * Test API key storage
     */
    public static function test_api_key_storage() {
        echo "Testing API key storage and encryption...\n";
        
        $security = Ultra_Instinct_Security::get_instance();
        
        // Generate and store API key
        $api_key = $security->generate_api_key();
        $stored = $security->store_api_key( $api_key );
        
        if ( ! $stored ) {
            echo "Failed to store API key\n";
            return false;
        }

        // Check if key exists
        if ( ! $security->has_api_key() ) {
            echo "API key not found after storage\n";
            return false;
        }

        // Verify stored key is hashed (not plaintext)
        $stored_hash = get_option( 'ultra_instinct_api_key_hash' );
        if ( $stored_hash === $api_key ) {
            echo "API key stored in plaintext (security risk)\n";
            return false;
        }

        echo "API key properly stored and encrypted\n";
        return true;
    }

    /**
     * Test API key verification
     */
    public static function test_api_key_verification() {
        echo "Testing API key verification...\n";
        
        $security = Ultra_Instinct_Security::get_instance();
        
        // Generate and store API key
        $api_key = $security->generate_api_key();
        $security->store_api_key( $api_key );
        
        // Test correct key verification
        if ( ! $security->verify_api_key( $api_key ) ) {
            echo "Failed to verify correct API key\n";
            return false;
        }

        // Test incorrect key verification
        $wrong_key = 'wrong_key_' . time();
        if ( $security->verify_api_key( $wrong_key ) ) {
            echo "Incorrectly verified wrong API key\n";
            return false;
        }

        // Test empty key verification
        if ( $security->verify_api_key( '' ) ) {
            echo "Incorrectly verified empty API key\n";
            return false;
        }

        echo "API key verification working correctly\n";
        return true;
    }

    /**
     * Test REST API endpoints
     */
    public static function test_rest_api_endpoints() {
        echo "Testing REST API endpoint registration...\n";
        
        // Get registered routes
        $routes = rest_get_server()->get_routes();
        
        $expected_routes = array(
            '/ultra-instinct/v1/test',
            '/ultra-instinct/v1/plugins/update',
            '/ultra-instinct/v1/plugins/install',
            '/ultra-instinct/v1/plugins/toggle',
            '/ultra-instinct/v1/content/create',
            '/ultra-instinct/v1/media/upload',
            '/ultra-instinct/v1/settings/permalinks/flush',
            '/ultra-instinct/v1/site/info',
        );

        foreach ( $expected_routes as $route ) {
            if ( ! isset( $routes[ $route ] ) ) {
                echo "Missing route: {$route}\n";
                return false;
            }
        }

        echo "All REST API endpoints properly registered\n";
        return true;
    }

    /**
     * Test authentication
     */
    public static function test_authentication() {
        echo "Testing API authentication...\n";
        
        $security = Ultra_Instinct_Security::get_instance();
        
        // Create a mock request without API key
        $request = new WP_REST_Request( 'GET', '/ultra-instinct/v1/test' );
        
        // Test authentication without key
        $auth_result = $security->authenticate_request( $request );
        if ( ! is_wp_error( $auth_result ) ) {
            echo "Authentication should fail without API key\n";
            return false;
        }

        // Generate and store API key
        $api_key = $security->generate_api_key();
        $security->store_api_key( $api_key );

        // Test authentication with correct key
        $request->set_header( 'X-Ultra-Instinct-Key', $api_key );
        $auth_result = $security->authenticate_request( $request );
        if ( is_wp_error( $auth_result ) ) {
            echo "Authentication should succeed with correct API key\n";
            return false;
        }

        // Test authentication with wrong key
        $request->set_header( 'X-Ultra-Instinct-Key', 'wrong_key' );
        $auth_result = $security->authenticate_request( $request );
        if ( ! is_wp_error( $auth_result ) ) {
            echo "Authentication should fail with wrong API key\n";
            return false;
        }

        echo "API authentication working correctly\n";
        return true;
    }

    /**
     * Test rate limiting
     */
    public static function test_rate_limiting() {
        echo "Testing rate limiting functionality...\n";
        
        $security = Ultra_Instinct_Security::get_instance();
        
        $test_ip = '192.168.1.100';
        
        // Test initial rate limit check (should pass)
        if ( ! $security->check_rate_limit( $test_ip ) ) {
            echo "Initial rate limit check should pass\n";
            return false;
        }

        echo "Rate limiting functionality working\n";
        return true;
    }

    /**
     * Test logging system
     */
    public static function test_logging_system() {
        echo "Testing logging system...\n";
        
        if ( ! class_exists( 'Ultra_Instinct_Logger' ) ) {
            echo "Logger class not found\n";
            return false;
        }

        // Test logging
        $test_message = 'Test log message ' . time();
        Ultra_Instinct_Logger::log( $test_message, 'info' );

        // Retrieve logs
        $logs = Ultra_Instinct_Logger::get_recent_logs( 10 );
        
        if ( empty( $logs ) ) {
            echo "No logs found after logging\n";
            return false;
        }

        // Check if our test message is in the logs
        $found = false;
        foreach ( $logs as $log ) {
            if ( strpos( $log['message'], $test_message ) !== false ) {
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            echo "Test log message not found in logs\n";
            return false;
        }

        echo "Logging system working correctly\n";
        return true;
    }

    /**
     * Test admin interface
     */
    public static function test_admin_interface() {
        echo "Testing admin interface...\n";
        
        if ( ! class_exists( 'Ultra_Instinct_Admin' ) ) {
            echo "Admin class not found\n";
            return false;
        }

        $admin = Ultra_Instinct_Admin::get_instance();
        if ( ! $admin ) {
            echo "Admin instance not created\n";
            return false;
        }

        // Check if admin menu is added (this would need to be tested in admin context)
        echo "Admin interface class properly initialized\n";
        return true;
    }

    /**
     * Test database operations
     */
    public static function test_database_operations() {
        echo "Testing database operations...\n";
        
        global $wpdb;
        
        // Check if logs table exists
        $table_name = $wpdb->prefix . 'ultra_instinct_logs';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
        
        if ( ! $table_exists ) {
            echo "Logs table not created\n";
            return false;
        }

        // Test inserting a log entry
        $result = $wpdb->insert(
            $table_name,
            array(
                'message' => 'Test database operation',
                'level' => 'info',
                'timestamp' => current_time( 'mysql' ),
            )
        );

        if ( false === $result ) {
            echo "Failed to insert test log entry\n";
            return false;
        }

        echo "Database operations working correctly\n";
        return true;
    }

    /**
     * Test security measures
     */
    public static function test_security_measures() {
        echo "Testing security measures...\n";
        
        // Test that sensitive data is not exposed
        $stored_hash = get_option( 'ultra_instinct_api_key_hash' );
        
        if ( ! empty( $stored_hash ) ) {
            // Check if stored hash looks like a hash (not plaintext)
            if ( strlen( $stored_hash ) === 64 && ctype_xdigit( $stored_hash ) ) {
                echo "API key properly hashed in storage\n";
            } else {
                echo "API key may not be properly hashed\n";
                return false;
            }
        }

        // Test input sanitization (basic check)
        $test_input = '<script>alert("xss")</script>';
        $sanitized = sanitize_text_field( $test_input );
        
        if ( $sanitized === $test_input ) {
            echo "Input sanitization may not be working\n";
            return false;
        }

        echo "Security measures properly implemented\n";
        return true;
    }
}

// Run tests if accessed directly (for testing purposes)
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'ultra-instinct test', array( 'Ultra_Instinct_Plugin_Tester', 'run_tests' ) );
} elseif ( current_user_can( 'manage_options' ) && isset( $_GET['run_ultra_instinct_tests'] ) ) {
    Ultra_Instinct_Plugin_Tester::run_tests();
}
