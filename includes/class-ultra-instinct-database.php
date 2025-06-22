<?php
/**
 * Ultra Instinct Database Management Class
 *
 * Handles database table creation and maintenance
 *
 * @package UltraInstinct
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ultra Instinct Database class
 */
class Ultra_Instinct_Database {

    /**
     * Single instance
     *
     * @var Ultra_Instinct_Database
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Ultra_Instinct_Database
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
        add_action( 'admin_init', array( $this, 'check_database_version' ) );
    }

    /**
     * Check database version and update if needed
     */
    public function check_database_version() {
        $current_version = get_option( 'ultra_instinct_db_version', '0' );
        $required_version = '2.0';

        if ( version_compare( $current_version, $required_version, '<' ) ) {
            $this->update_database();
            update_option( 'ultra_instinct_db_version', $required_version );
        }
    }

    /**
     * Update database tables
     */
    public function update_database() {
        $this->create_logs_table();
        $this->create_agent_connections_table();
        $this->update_logs_table_structure();
    }

    /**
     * Create logs table
     */
    public function create_logs_table() {
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
        dbDelta( $sql );
    }

    /**
     * Create agent connections table
     */
    public function create_agent_connections_table() {
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
     * Update logs table structure for missing columns
     */
    public function update_logs_table_structure() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ultra_instinct_logs';
        
        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;
        if ( ! $table_exists ) {
            return;
        }

        // Get current columns
        $columns = $wpdb->get_results( "DESCRIBE {$table_name}" );
        $column_names = wp_list_pluck( $columns, 'Field' );

        // Add missing agent_id column
        if ( ! in_array( 'agent_id', $column_names ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN agent_id varchar(100) AFTER user_id" );
            $wpdb->query( "ALTER TABLE {$table_name} ADD KEY agent_id (agent_id)" );
        }

        // Add missing action_type column
        if ( ! in_array( 'action_type', $column_names ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN action_type varchar(50) AFTER agent_id" );
            $wpdb->query( "ALTER TABLE {$table_name} ADD KEY action_type (action_type)" );
        }
    }

    /**
     * Repair database tables
     */
    public function repair_tables() {
        $this->update_database();
        
        // Verify tables exist
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'ultra_instinct_logs';
        $agents_table = $wpdb->prefix . 'ultra_instinct_agent_connections';
        
        $logs_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $logs_table ) ) === $logs_table;
        $agents_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $agents_table ) ) === $agents_table;
        
        return array(
            'logs_table' => $logs_exists,
            'agents_table' => $agents_exists,
        );
    }

    /**
     * Get database status
     */
    public function get_database_status() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'ultra_instinct_logs';
        $agents_table = $wpdb->prefix . 'ultra_instinct_agent_connections';
        
        $status = array(
            'logs_table_exists' => false,
            'agents_table_exists' => false,
            'logs_columns' => array(),
            'agents_columns' => array(),
        );
        
        // Check logs table
        $logs_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $logs_table ) ) === $logs_table;
        $status['logs_table_exists'] = $logs_exists;
        
        if ( $logs_exists ) {
            $columns = $wpdb->get_results( "DESCRIBE {$logs_table}" );
            $status['logs_columns'] = wp_list_pluck( $columns, 'Field' );
        }
        
        // Check agents table
        $agents_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $agents_table ) ) === $agents_table;
        $status['agents_table_exists'] = $agents_exists;
        
        if ( $agents_exists ) {
            $columns = $wpdb->get_results( "DESCRIBE {$agents_table}" );
            $status['agents_columns'] = wp_list_pluck( $columns, 'Field' );
        }
        
        return $status;
    }
}
