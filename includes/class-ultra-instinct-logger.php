<?php
/**
 * Ultra Instinct Logger Class
 *
 * Enhanced logging with agent tracking and structured data
 *
 * @package UltraInstinct
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ultra Instinct Logger class
 */
class Ultra_Instinct_Logger {

    /**
     * Single instance
     *
     * @var Ultra_Instinct_Logger
     */
    private static $instance = null;

    /**
     * Log table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Get instance
     *
     * @return Ultra_Instinct_Logger
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ultra_instinct_logs';
        $this->ensure_table_exists();
    }

    /**
     * Ensure logs table exists
     */
    private function ensure_table_exists() {
        global $wpdb;

        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->table_name ) ) === $this->table_name;
        
        if ( ! $table_exists ) {
            $this->create_table();
        } else {
            // Check if all required columns exist
            $this->verify_table_structure();
        }
    }

    /**
     * Verify table structure and add missing columns
     */
    private function verify_table_structure() {
        global $wpdb;
        
        $columns = $wpdb->get_results( "DESCRIBE {$this->table_name}" );
        $column_names = wp_list_pluck( $columns, 'Field' );
        
        // Check for missing agent_id column
        if ( ! in_array( 'agent_id', $column_names ) ) {
            $wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN agent_id varchar(100) AFTER user_id" );
            $wpdb->query( "ALTER TABLE {$this->table_name} ADD KEY agent_id (agent_id)" );
        }
        
        // Check for missing action_type column
        if ( ! in_array( 'action_type', $column_names ) ) {
            $wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN action_type varchar(50) AFTER agent_id" );
            $wpdb->query( "ALTER TABLE {$this->table_name} ADD KEY action_type (action_type)" );
        }
    }

    /**
     * Create logs table
     */
    private function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
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
     * Log a message with enhanced context
     *
     * @param string $message The log message.
     * @param string $level The log level (info, warning, error).
     * @param array  $context Additional context data.
     */
    public static function log( $message, $level = 'info', $context = array() ) {
        $instance = self::get_instance();
        if ( $instance ) {
            $instance->write_log( $message, $level, $context );
        }
    }

    /**
     * Write log to database
     *
     * @param string $message The log message.
     * @param string $level The log level.
     * @param array  $context Additional context data.
     */
    private function write_log( $message, $level, $context ) {
        global $wpdb;

        // Check if logging is enabled
        $settings = get_option( 'ultra_instinct_settings', array() );
        if ( ! ( $settings['enable_logging'] ?? true ) ) {
            return;
        }

        // Ensure table exists
        $this->ensure_table_exists();

        $data = array(
            'message' => sanitize_text_field( $message ),
            'level' => sanitize_text_field( $level ),
            'context' => wp_json_encode( $context ),
            'ip_address' => $this->get_client_ip(),
            'user_id' => get_current_user_id(),
            'agent_id' => $context['agent_id'] ?? null,
            'action_type' => $context['action_type'] ?? null,
        );

        $result = $wpdb->insert( $this->table_name, $data );

        if ( false === $result ) {
            // Fallback to error_log if database insert fails
            error_log( "Ultra Instinct Log [{$level}]: {$message}" );
        }

        // Clean old logs periodically
        if ( wp_rand( 1, 100 ) === 1 ) {
            $this->cleanup_old_logs();
        }
    }

    /**
     * Get recent logs with filtering
     *
     * @param int    $limit Number of logs to retrieve.
     * @param string $level Filter by log level.
     * @param string $agent_id Filter by agent ID.
     * @return array
     */
    public static function get_recent_logs( $limit = 50, $level = null, $agent_id = null ) {
        $instance = self::get_instance();
        if ( ! $instance ) {
            return array();
        }
        return $instance->fetch_recent_logs( $limit, $level, $agent_id );
    }

    /**
     * Fetch recent logs from database
     *
     * @param int    $limit Number of logs to retrieve.
     * @param string $level Filter by log level.
     * @param string $agent_id Filter by agent ID.
     * @return array
     */
    private function fetch_recent_logs( $limit, $level = null, $agent_id = null ) {
        global $wpdb;

        $this->ensure_table_exists();

        $where_clauses = array();
        $params = array();

        if ( $level ) {
            $where_clauses[] = 'level = %s';
            $params[] = $level;
        }

        if ( $agent_id ) {
            $where_clauses[] = 'agent_id = %s';
            $params[] = $agent_id;
        }

        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }

        $params[] = $limit;

        $sql = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY timestamp DESC LIMIT %d";
        
        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        $results = $wpdb->get_results( $sql, ARRAY_A );
        
        // Decode context JSON
        if ( is_array( $results ) ) {
            foreach ( $results as &$result ) {
                $result['context'] = json_decode( $result['context'], true ) ?: array();
            }
        }
        
        return is_array( $results ) ? $results : array();
    }

    /**
     * Get log statistics
     *
     * @param int $days Number of days to analyze.
     * @return array
     */
    public static function get_log_statistics( $days = 7 ) {
        $instance = self::get_instance();
        if ( ! $instance ) {
            return array();
        }
        return $instance->fetch_log_statistics( $days );
    }

    /**
     * Fetch log statistics from database
     *
     * @param int $days Number of days to analyze.
     * @return array
     */
    private function fetch_log_statistics( $days ) {
        global $wpdb;

        $this->ensure_table_exists();

        $stats = array();

        // Total logs by level
        $level_stats = $wpdb->get_results( $wpdb->prepare(
            "SELECT level, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY level",
            $days
        ), ARRAY_A );

        $stats['by_level'] = array();
        if ( is_array( $level_stats ) ) {
            foreach ( $level_stats as $stat ) {
                $stats['by_level'][ $stat['level'] ] = intval( $stat['count'] );
            }
        }

        // Total logs by agent
        $agent_stats = $wpdb->get_results( $wpdb->prepare(
            "SELECT agent_id, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)
             AND agent_id IS NOT NULL
             GROUP BY agent_id
             ORDER BY count DESC
             LIMIT 10",
            $days
        ), ARRAY_A );

        $stats['by_agent'] = array();
        if ( is_array( $agent_stats ) ) {
            foreach ( $agent_stats as $stat ) {
                $stats['by_agent'][ $stat['agent_id'] ] = intval( $stat['count'] );
            }
        }

        // Daily activity
        $daily_stats = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(timestamp) as date, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(timestamp)
             ORDER BY date DESC",
            $days
        ), ARRAY_A );

        $stats['daily_activity'] = array();
        if ( is_array( $daily_stats ) ) {
            foreach ( $daily_stats as $stat ) {
                $stats['daily_activity'][ $stat['date'] ] = intval( $stat['count'] );
            }
        }

        return $stats;
    }

    /**
     * Clear all logs
     */
    public static function clear_logs() {
        $instance = self::get_instance();
        if ( $instance ) {
            $instance->truncate_logs();
        }
    }

    /**
     * Truncate logs table
     */
    private function truncate_logs() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
    }

    /**
     * Clean up old logs based on retention settings
     */
    private function cleanup_old_logs() {
        global $wpdb;

        $settings = get_option( 'ultra_instinct_settings', array() );
        $retention_days = absint( $settings['log_retention_days'] ?? 30 );

        $sql = $wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        );

        $deleted = $wpdb->query( $sql );

        if ( $deleted > 0 ) {
            error_log( "Ultra Instinct: Cleaned up {$deleted} old log entries" );
        }
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
}
