<?php
/**
 * Plugin Name: Ultra Instinct Integration
 * Plugin URI: https://ultra-instinct.ai
 * Description: Advanced WordPress integration plugin for Ultra Instinct AI agents with real-time connectivity, webhooks, and comprehensive site management capabilities.
 * Version: 2.0.0
 * Author: Ultra Instinct Team
 * Author URI: https://ultra-instinct.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ultra-instinct
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * Network: false
 *
 * @package UltraInstinct
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'ULTRA_INSTINCT_VERSION', '2.0.0' );
define( 'ULTRA_INSTINCT_PLUGIN_FILE', __FILE__ );
define( 'ULTRA_INSTINCT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ULTRA_INSTINCT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ULTRA_INSTINCT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Ultra Instinct Integration class
 */
class Ultra_Instinct_Integration {

    /**
     * Single instance of the class
     *
     * @var Ultra_Instinct_Integration
     */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return Ultra_Instinct_Integration
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
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook( ULTRA_INSTINCT_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( ULTRA_INSTINCT_PLUGIN_FILE, array( $this, 'deactivate' ) );
        register_uninstall_hook( ULTRA_INSTINCT_PLUGIN_FILE, array( 'Ultra_Instinct_Integration', 'uninstall' ) );

        add_action( 'init', array( $this, 'init' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_filter( 'plugin_action_links_' . ULTRA_INSTINCT_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
        
        // Add CORS support
        add_action( 'rest_api_init', array( $this, 'add_cors_support' ) );
        
        // Add webhook support
        add_action( 'wp_loaded', array( $this, 'handle_webhooks' ) );
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        $files = array(
            'includes/class-ultra-instinct-database.php',
            'includes/class-ultra-instinct-security.php',
            'includes/class-ultra-instinct-logger.php',
            'includes/class-ultra-instinct-api.php',
            'includes/class-ultra-instinct-admin.php',
            'includes/class-ultra-instinct-agent-connector.php',
            'includes/class-ultra-instinct-webhook-handler.php',
        );

        foreach ( $files as $file ) {
            $file_path = ULTRA_INSTINCT_PLUGIN_DIR . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize database management first
        if ( class_exists( 'Ultra_Instinct_Database' ) ) {
            Ultra_Instinct_Database::get_instance();
        }
        
        // Initialize other components only if classes exist
        if ( class_exists( 'Ultra_Instinct_Security' ) ) {
            Ultra_Instinct_Security::get_instance();
        }
        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::get_instance();
        }
        if ( class_exists( 'Ultra_Instinct_API' ) ) {
            Ultra_Instinct_API::get_instance();
        }
        if ( class_exists( 'Ultra_Instinct_Admin' ) ) {
            Ultra_Instinct_Admin::get_instance();
        }
        if ( class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            Ultra_Instinct_Agent_Connector::get_instance();
        }
        if ( class_exists( 'Ultra_Instinct_Webhook_Handler' ) ) {
            Ultra_Instinct_Webhook_Handler::get_instance();
        }
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        if ( class_exists( 'Ultra_Instinct_API' ) ) {
            Ultra_Instinct_API::get_instance()->register_routes();
        }
    }

    /**
     * Add CORS support for agent communication
     */
    public function add_cors_support() {
        remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
        add_filter( 'rest_pre_serve_request', function( $value ) {
            header( 'Access-Control-Allow-Origin: *' );
            header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-Ultra-Instinct-Key, X-WP-Nonce' );
            header( 'Access-Control-Allow-Credentials: true' );
            return $value;
        });
    }

    /**
     * Handle webhook requests
     */
    public function handle_webhooks() {
        if ( isset( $_GET['ultra_instinct_webhook'] ) && class_exists( 'Ultra_Instinct_Webhook_Handler' ) ) {
            Ultra_Instinct_Webhook_Handler::get_instance()->handle_request();
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        if ( class_exists( 'Ultra_Instinct_Admin' ) ) {
            Ultra_Instinct_Admin::get_instance()->add_menu();
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( class_exists( 'Ultra_Instinct_Admin' ) ) {
            Ultra_Instinct_Admin::get_instance()->enqueue_scripts( $hook );
        }
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'ultra-instinct', false, dirname( ULTRA_INSTINCT_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        if ( class_exists( 'Ultra_Instinct_Admin' ) ) {
            Ultra_Instinct_Admin::get_instance()->show_admin_notices();
        }
    }

    /**
     * Plugin action links
     */
    public function plugin_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'options-general.php?page=ultra-instinct' ) . '">' . __( 'Settings', 'ultra-instinct' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create necessary database tables or options
        $this->create_options();
        
        // Create logs table
        $this->create_logs_table();
        
        // Create agent connections table
        $this->create_agent_connections_table();
        
        // Verify tables were created
        $this->verify_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation notice
        set_transient( 'ultra_instinct_activation_notice', true, 30 );
        
        // Log activation if logger is available
        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log( 'Plugin activated - Version ' . ULTRA_INSTINCT_VERSION, 'info' );
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Notify connected agents
        $this->notify_agents_of_deactivation();
        
        // Log deactivation if logger is available
        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log( 'Plugin deactivated', 'info' );
        }
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Remove all plugin options
        delete_option( 'ultra_instinct_api_key_hash' );
        delete_option( 'ultra_instinct_settings' );
        delete_option( 'ultra_instinct_connection_status' );
        delete_option( 'ultra_instinct_agent_connections' );
        
        // Remove database tables
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ultra_instinct_logs" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ultra_instinct_agent_connections" );
    }

    /**
     * Create default options
     */
    private function create_options() {
        $default_settings = array(
            'platform_url' => 'https://app.ultra-instinct.ai',
            'enable_logging' => true,
            'log_retention_days' => 30,
            'rate_limit_requests' => 100,
            'rate_limit_window' => 3600,
            'webhook_enabled' => true,
            'webhook_secret' => wp_generate_password( 32, false ),
            'agent_timeout' => 30,
            'max_concurrent_agents' => 5,
            'enable_real_time' => true,
        );
        
        add_option( 'ultra_instinct_settings', $default_settings );
        add_option( 'ultra_instinct_connection_status', 'disconnected' );
        add_option( 'ultra_instinct_agent_connections', array() );
    }

    /**
     * Create logs table
     */
    private function create_logs_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ultra_instinct_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext,
            ip_address varchar(45),
            user_id bigint(20),
            agent_id varchar(100),
            action_type varchar(50),
            PRIMARY KEY (id),
            KEY level (level),
            KEY timestamp (timestamp),
            KEY agent_id (agent_id),
            KEY action_type (action_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $result = dbDelta( $sql );
        
        // Log the result for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Ultra Instinct: Logs table creation result: ' . print_r( $result, true ) );
        }
    }

    /**
     * Create agent connections table
     */
    private function create_agent_connections_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ultra_instinct_agent_connections';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            agent_id varchar(100) NOT NULL,
            agent_name varchar(255),
            agent_type varchar(50),
            status varchar(20) DEFAULT 'active',
            last_seen datetime DEFAULT CURRENT_TIMESTAMP,
            capabilities longtext,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY agent_id (agent_id),
            KEY status (status),
            KEY last_seen (last_seen)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Verify that required tables exist
     */
    private function verify_tables() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'ultra_instinct_logs';
        $agents_table = $wpdb->prefix . 'ultra_instinct_agent_connections';
        
        // Check logs table
        $logs_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $logs_table ) ) === $logs_table;
        if ( ! $logs_exists ) {
            error_log( 'Ultra Instinct: Failed to create logs table' );
        }
        
        // Check agents table
        $agents_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $agents_table ) ) === $agents_table;
        if ( ! $agents_exists ) {
            error_log( 'Ultra Instinct: Failed to create agents table' );
        }
        
        // Check logs table structure
        if ( $logs_exists ) {
            $columns = $wpdb->get_results( "DESCRIBE {$logs_table}" );
            $column_names = wp_list_pluck( $columns, 'Field' );
            
            if ( ! in_array( 'agent_id', $column_names ) ) {
                // Add missing agent_id column
                $wpdb->query( "ALTER TABLE {$logs_table} ADD COLUMN agent_id varchar(100) AFTER user_id" );
                $wpdb->query( "ALTER TABLE {$logs_table} ADD KEY agent_id (agent_id)" );
                error_log( 'Ultra Instinct: Added missing agent_id column to logs table' );
            }
            
            if ( ! in_array( 'action_type', $column_names ) ) {
                // Add missing action_type column
                $wpdb->query( "ALTER TABLE {$logs_table} ADD COLUMN action_type varchar(50) AFTER agent_id" );
                $wpdb->query( "ALTER TABLE {$logs_table} ADD KEY action_type (action_type)" );
                error_log( 'Ultra Instinct: Added missing action_type column to logs table' );
            }
        }
    }

    /**
     * Notify agents of deactivation
     */
    private function notify_agents_of_deactivation() {
        $connections = get_option( 'ultra_instinct_agent_connections', array() );
        
        foreach ( $connections as $agent_id => $connection ) {
            if ( ! empty( $connection['webhook_url'] ) ) {
                wp_remote_post( $connection['webhook_url'], array(
                    'body' => wp_json_encode( array(
                        'event' => 'plugin_deactivated',
                        'site_url' => get_site_url(),
                        'timestamp' => current_time( 'mysql' ),
                    ) ),
                    'headers' => array(
                        'Content-Type' => 'application/json',
                    ),
                    'timeout' => 10,
                ) );
            }
        }
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', function() {
    Ultra_Instinct_Integration::get_instance();
});
