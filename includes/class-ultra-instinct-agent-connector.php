<?php
/**
 * Ultra Instinct Agent Connector Class
 *
 * Handles agent registration, communication, and management
 *
 * @package UltraInstinct
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ultra Instinct Agent Connector class
 */
class Ultra_Instinct_Agent_Connector {

    /**
     * Single instance
     *
     * @var Ultra_Instinct_Agent_Connector
     */
    private static $instance = null;

    /**
     * Connected agents
     *
     * @var array
     */
    private $connected_agents = array();

    /**
     * Get instance
     *
     * @return Ultra_Instinct_Agent_Connector
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
        $this->load_connected_agents();
        add_action( 'wp_loaded', array( $this, 'cleanup_inactive_agents' ) );
        add_action( 'ultra_instinct_agent_heartbeat', array( $this, 'process_agent_heartbeat' ) );
    }

    /**
     * Register a new agent
     *
     * @param array $agent_data Agent registration data.
     * @return array|WP_Error
     */
    public function register_agent( $agent_data ) {
        global $wpdb;

        // Validate required fields
        $required_fields = array( 'agent_id', 'agent_name', 'agent_type' );
        foreach ( $required_fields as $field ) {
            if ( empty( $agent_data[ $field ] ) ) {
                return new WP_Error(
                    'missing_field',
                    sprintf( __( 'Missing required field: %s', 'ultra-instinct' ), $field ),
                    array( 'status' => 400 )
                );
            }
        }

        $agent_id = sanitize_text_field( $agent_data['agent_id'] );
        $agent_name = sanitize_text_field( $agent_data['agent_name'] );
        $agent_type = sanitize_text_field( $agent_data['agent_type'] );
        $capabilities = isset( $agent_data['capabilities'] ) ? $agent_data['capabilities'] : array();
        $metadata = isset( $agent_data['metadata'] ) ? $agent_data['metadata'] : array();

        // Check if agent already exists
        $table_name = $wpdb->prefix . 'ultra_instinct_agent_connections';
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE agent_id = %s",
            $agent_id
        ) );

        if ( $existing ) {
            // Update existing agent
            $result = $wpdb->update(
                $table_name,
                array(
                    'agent_name' => $agent_name,
                    'agent_type' => $agent_type,
                    'status' => 'active',
                    'last_seen' => current_time( 'mysql' ),
                    'capabilities' => wp_json_encode( $capabilities ),
                    'metadata' => wp_json_encode( $metadata ),
                ),
                array( 'agent_id' => $agent_id )
            );
        } else {
            // Insert new agent
            $result = $wpdb->insert(
                $table_name,
                array(
                    'agent_id' => $agent_id,
                    'agent_name' => $agent_name,
                    'agent_type' => $agent_type,
                    'status' => 'active',
                    'last_seen' => current_time( 'mysql' ),
                    'capabilities' => wp_json_encode( $capabilities ),
                    'metadata' => wp_json_encode( $metadata ),
                    'created_at' => current_time( 'mysql' ),
                )
            );
        }

        if ( false === $result ) {
            return new WP_Error(
                'registration_failed',
                __( 'Failed to register agent', 'ultra-instinct' ),
                array( 'status' => 500 )
            );
        }

        // Update in-memory cache
        $this->connected_agents[ $agent_id ] = array(
            'agent_name' => $agent_name,
            'agent_type' => $agent_type,
            'status' => 'active',
            'last_seen' => current_time( 'mysql' ),
            'capabilities' => $capabilities,
            'metadata' => $metadata,
        );

        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log(
                "Agent registered: {$agent_name} ({$agent_id})",
                'info',
                array( 'agent_id' => $agent_id, 'agent_type' => $agent_type )
            );
        }

        return array(
            'success' => true,
            'agent_id' => $agent_id,
            'message' => __( 'Agent registered successfully', 'ultra-instinct' ),
            'site_info' => $this->get_site_info(),
        );
    }

    /**
     * Update agent heartbeat
     *
     * @param string $agent_id Agent ID.
     * @param array  $status_data Status data.
     * @return bool
     */
    public function update_agent_heartbeat( $agent_id, $status_data = array() ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ultra_instinct_agent_connections';
        
        $update_data = array(
            'last_seen' => current_time( 'mysql' ),
            'status' => 'active',
        );

        if ( ! empty( $status_data['metadata'] ) ) {
            $update_data['metadata'] = wp_json_encode( $status_data['metadata'] );
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array( 'agent_id' => $agent_id )
        );

        if ( $result !== false ) {
            // Update in-memory cache
            if ( isset( $this->connected_agents[ $agent_id ] ) ) {
                $this->connected_agents[ $agent_id ]['last_seen'] = current_time( 'mysql' );
                $this->connected_agents[ $agent_id ]['status'] = 'active';
                
                if ( ! empty( $status_data['metadata'] ) ) {
                    $this->connected_agents[ $agent_id ]['metadata'] = $status_data['metadata'];
                }
            }
        }

        return $result !== false;
    }

    /**
     * Get connected agents
     *
     * @param string $status Filter by status.
     * @return array
     */
    public function get_connected_agents( $status = 'active' ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ultra_instinct_agent_connections';
        
        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;
        if ( ! $table_exists ) {
            // Try to create the table
            $this->create_agent_connections_table();
            return array();
        }
        
        if ( $status ) {
            $agents = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE status = %s ORDER BY last_seen DESC",
                $status
            ), ARRAY_A );
        } else {
            $agents = $wpdb->get_results(
                "SELECT * FROM {$table_name} ORDER BY last_seen DESC",
                ARRAY_A
            );
        }

        // Decode JSON fields
        if ( is_array( $agents ) ) {
            foreach ( $agents as &$agent ) {
                $agent['capabilities'] = json_decode( $agent['capabilities'], true ) ?: array();
                $agent['metadata'] = json_decode( $agent['metadata'], true ) ?: array();
            }
        }

        return is_array( $agents ) ? $agents : array();
    }

    /**
     * Disconnect agent
     *
     * @param string $agent_id Agent ID.
     * @return bool
     */
    public function disconnect_agent( $agent_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ultra_instinct_agent_connections';
        
        $result = $wpdb->update(
            $table_name,
            array( 'status' => 'disconnected' ),
            array( 'agent_id' => $agent_id )
        );

        if ( $result !== false ) {
            unset( $this->connected_agents[ $agent_id ] );
            
            if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
                Ultra_Instinct_Logger::log(
                    "Agent disconnected: {$agent_id}",
                    'info',
                    array( 'agent_id' => $agent_id )
                );
            }
        }

        return $result !== false;
    }

    /**
     * Send message to agent
     *
     * @param string $agent_id Agent ID.
     * @param array  $message Message data.
     * @return array|WP_Error
     */
    public function send_message_to_agent( $agent_id, $message ) {
        $agent = $this->get_agent_by_id( $agent_id );
        
        if ( ! $agent ) {
            return new WP_Error(
                'agent_not_found',
                __( 'Agent not found', 'ultra-instinct' ),
                array( 'status' => 404 )
            );
        }

        if ( empty( $agent['metadata']['webhook_url'] ) ) {
            return new WP_Error(
                'no_webhook',
                __( 'Agent has no webhook URL configured', 'ultra-instinct' ),
                array( 'status' => 400 )
            );
        }

        $webhook_url = $agent['metadata']['webhook_url'];
        $settings = get_option( 'ultra_instinct_settings', array() );

        $payload = array(
            'message' => $message,
            'site_url' => get_site_url(),
            'timestamp' => current_time( 'mysql' ),
            'signature' => hash_hmac( 'sha256', wp_json_encode( $message ), $settings['webhook_secret'] ?? '' ),
        );

        $response = wp_remote_post( $webhook_url, array(
            'body' => wp_json_encode( $payload ),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Ultra-Instinct-Signature' => $payload['signature'],
            ),
            'timeout' => $settings['agent_timeout'] ?? 30,
        ) );

        if ( is_wp_error( $response ) ) {
            if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
                Ultra_Instinct_Logger::log(
                    "Failed to send message to agent {$agent_id}: " . $response->get_error_message(),
                    'error',
                    array( 'agent_id' => $agent_id, 'webhook_url' => $webhook_url )
                );
            }
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log(
                "Message sent to agent {$agent_id}",
                'info',
                array( 'agent_id' => $agent_id, 'response_code' => $response_code )
            );
        }

        return array(
            'success' => $response_code >= 200 && $response_code < 300,
            'response_code' => $response_code,
            'response_body' => $response_body,
        );
    }

    /**
     * Broadcast message to all active agents
     *
     * @param array $message Message data.
     * @param array $agent_types Filter by agent types.
     * @return array
     */
    public function broadcast_message( $message, $agent_types = array() ) {
        $agents = $this->get_connected_agents( 'active' );
        $results = array();

        foreach ( $agents as $agent ) {
            // Filter by agent types if specified
            if ( ! empty( $agent_types ) && ! in_array( $agent['agent_type'], $agent_types, true ) ) {
                continue;
            }

            $result = $this->send_message_to_agent( $agent['agent_id'], $message );
            $results[ $agent['agent_id'] ] = $result;
        }

        return $results;
    }

    /**
     * Get agent by ID
     *
     * @param string $agent_id Agent ID.
     * @return array|null
     */
    public function get_agent_by_id( $agent_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ultra_instinct_agent_connections';
        
        $agent = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE agent_id = %s",
            $agent_id
        ), ARRAY_A );

        if ( $agent ) {
            $agent['capabilities'] = json_decode( $agent['capabilities'], true ) ?: array();
            $agent['metadata'] = json_decode( $agent['metadata'], true ) ?: array();
        }

        return $agent;
    }

    /**
     * Load connected agents into memory
     */
    private function load_connected_agents() {
        $this->connected_agents = array();
        $agents = $this->get_connected_agents( 'active' );
        
        foreach ( $agents as $agent ) {
            $this->connected_agents[ $agent['agent_id'] ] = $agent;
        }
    }

    /**
     * Cleanup inactive agents
     */
    public function cleanup_inactive_agents() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ultra_instinct_agent_connections';
        $timeout_minutes = 10; // Consider agents inactive after 10 minutes

        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table_name} SET status = 'inactive' 
             WHERE status = 'active' AND last_seen < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $timeout_minutes
        ) );
    }

    /**
     * Get site information for agents
     *
     * @return array
     */
    private function get_site_info() {
        return array(
            'site_url' => get_site_url(),
            'admin_email' => get_option( 'admin_email' ),
            'wp_version' => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
            'plugin_version' => ULTRA_INSTINCT_VERSION,
            'timezone' => get_option( 'timezone_string' ),
            'language' => get_locale(),
        );
    }

    /**
     * Process agent heartbeat
     *
     * @param array $data Heartbeat data.
     */
    public function process_agent_heartbeat( $data ) {
        if ( empty( $data['agent_id'] ) ) {
            return;
        }

        $this->update_agent_heartbeat( $data['agent_id'], $data );
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
}
