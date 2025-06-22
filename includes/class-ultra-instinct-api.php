<?php
/**
 * Ultra Instinct API Class
 *
 * Enhanced REST API endpoints for agent communication
 *
 * @package UltraInstinct
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ultra Instinct API class
 */
class Ultra_Instinct_API {

    /**
     * Single instance
     *
     * @var Ultra_Instinct_API
     */
    private static $instance = null;

    /**
     * API namespace
     *
     * @var string
     */
    private $namespace = 'ultra-instinct/v2';

    /**
     * Get instance
     *
     * @return Ultra_Instinct_API
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Connection and authentication endpoints
        register_rest_route( $this->namespace, '/connect', array(
            'methods' => 'POST',
            'callback' => array( $this, 'connect_site' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ) );

        register_rest_route( $this->namespace, '/test', array(
            'methods' => 'GET',
            'callback' => array( $this, 'test_connection' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ) );

        // Agent management endpoints
        register_rest_route( $this->namespace, '/agents/register', array(
            'methods' => 'POST',
            'callback' => array( $this, 'register_agent' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
            'args' => array(
                'agent_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'agent_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'agent_type' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/agents/heartbeat', array(
            'methods' => 'POST',
            'callback' => array( $this, 'agent_heartbeat' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
            'args' => array(
                'agent_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/agents/list', array(
            'methods' => 'GET',
            'callback' => array( $this, 'list_agents' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ) );

        register_rest_route( $this->namespace, '/agents/(?P<agent_id>[a-zA-Z0-9_-]+)/disconnect', array(
            'methods' => 'POST',
            'callback' => array( $this, 'disconnect_agent' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ) );

        // WordPress management endpoints
        register_rest_route( $this->namespace, '/plugins/update', array(
            'methods' => 'POST',
            'callback' => array( $this, 'update_plugins' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
            'args' => array(
                'plugin' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/plugins/install', array(
            'methods' => 'POST',
            'callback' => array( $this, 'install_plugin' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'zip_url' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/plugins/toggle', array(
            'methods' => 'POST',
            'callback' => array( $this, 'toggle_plugin' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
            'args' => array(
                'plugin' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'action' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array( 'activate', 'deactivate' ),
                ),
            ),
        ) );

        // Content management endpoints
        register_rest_route( $this->namespace, '/content/create', array(
            'methods' => 'POST',
            'callback' => array( $this, 'create_content' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
            'args' => array(
                'title' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'content' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'wp_kses_post',
                ),
                'type' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'post',
                    'enum' => array( 'post', 'page' ),
                ),
                'status' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'draft',
                    'enum' => array( 'draft', 'publish', 'private' ),
                ),
            ),
        ) );

        // Media upload endpoint
        register_rest_route( $this->namespace, '/media/upload', array(
            'methods' => 'POST',
            'callback' => array( $this, 'upload_media' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ) );

        // Site information endpoint
        register_rest_route( $this->namespace, '/site/info', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_site_info' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ) );

        // Task management endpoints
        register_rest_route( $this->namespace, '/tasks/create', array(
            'methods' => 'POST',
            'callback' => array( $this, 'create_task' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ) );

        register_rest_route( $this->namespace, '/tasks/(?P<task_id>[a-zA-Z0-9_-]+)/status', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_task_status' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ) );

        // Settings endpoints
        register_rest_route( $this->namespace, '/settings/permalinks/flush', array(
            'methods' => 'POST',
            'callback' => array( $this, 'flush_permalinks' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ) );
    }

    /**
     * Authenticate request
     *
     * @param WP_REST_Request $request The REST request.
     * @return bool|WP_Error
     */
    public function authenticate_request( $request ) {
        if ( ! class_exists( 'Ultra_Instinct_Security' ) ) {
            return new WP_Error(
                'ultra_instinct_security_unavailable',
                __( 'Security module not available', 'ultra-instinct' ),
                array( 'status' => 500 )
            );
        }
        
        $security = Ultra_Instinct_Security::get_instance();
        
        // Check rate limit
        $client_ip = $this->get_client_ip();
        if ( ! $security->check_rate_limit( $client_ip ) ) {
            return new WP_Error(
                'ultra_instinct_rate_limit',
                __( 'Rate limit exceeded', 'ultra-instinct' ),
                array( 'status' => 429 )
            );
        }
        
        return $security->authenticate_request( $request );
    }

    /**
     * Connect site endpoint
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function connect_site( $request ) {
        $agent_data = $request->get_json_params();
        
        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log( 'Site connection initiated', 'info', $agent_data );
        }
        
        return new WP_REST_Response( array(
            'status' => 'connected',
            'site_url' => get_site_url(),
            'wp_version' => get_bloginfo( 'version' ),
            'plugin_version' => ULTRA_INSTINCT_VERSION,
            'timestamp' => current_time( 'mysql' ),
            'webhook_url' => get_site_url() . '/?ultra_instinct_webhook=1',
        ), 200 );
    }

    /**
     * Test connection endpoint
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function test_connection( $request ) {
        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log( 'Connection test successful', 'info' );
        }
        
        $agents_connected = 0;
        if ( class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            $agents_connected = count( Ultra_Instinct_Agent_Connector::get_instance()->get_connected_agents() );
        }
        
        return new WP_REST_Response( array(
            'status' => 'connected',
            'site_url' => get_site_url(),
            'wp_version' => get_bloginfo( 'version' ),
            'plugin_version' => ULTRA_INSTINCT_VERSION,
            'timestamp' => current_time( 'mysql' ),
            'agents_connected' => $agents_connected,
        ), 200 );
    }

    /**
     * Register agent endpoint
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function register_agent( $request ) {
        if ( ! class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Agent connector not available', 'ultra-instinct' ),
            ), 500 );
        }
        
        $agent_connector = Ultra_Instinct_Agent_Connector::get_instance();
        $agent_data = $request->get_json_params();
        
        $result = $agent_connector->register_agent( $agent_data );
        
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), $result->get_error_data()['status'] ?? 500 );
        }
        
        return new WP_REST_Response( $result, 201 );
    }

    /**
     * Agent heartbeat endpoint
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function agent_heartbeat( $request ) {
        if ( ! class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Agent connector not available', 'ultra-instinct' ),
            ), 500 );
        }
        
        $agent_connector = Ultra_Instinct_Agent_Connector::get_instance();
        $data = $request->get_json_params();
        
        $success = $agent_connector->update_agent_heartbeat( $data['agent_id'], $data );
        
        return new WP_REST_Response( array(
            'success' => $success,
            'timestamp' => current_time( 'mysql' ),
        ), $success ? 200 : 500 );
    }

    /**
     * List agents endpoint
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function list_agents( $request ) {
        if ( ! class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            return new WP_REST_Response( array(
                'agents' => array(),
                'total' => 0,
            ), 200 );
        }
        
        $agent_connector = Ultra_Instinct_Agent_Connector::get_instance();
        $status = $request->get_param( 'status' );
        
        $agents = $agent_connector->get_connected_agents( $status );
        
        return new WP_REST_Response( array(
            'agents' => $agents,
            'total' => count( $agents ),
        ), 200 );
    }

    /**
     * Disconnect agent endpoint
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function disconnect_agent( $request ) {
        if ( ! class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Agent connector not available', 'ultra-instinct' ),
            ), 500 );
        }
        
        $agent_connector = Ultra_Instinct_Agent_Connector::get_instance();
        $agent_id = $request->get_param( 'agent_id' );
        
        $success = $agent_connector->disconnect_agent( $agent_id );
        
        return new WP_REST_Response( array(
            'success' => $success,
            'message' => $success ? __( 'Agent disconnected successfully', 'ultra-instinct' ) : __( 'Failed to disconnect agent', 'ultra-instinct' ),
        ), $success ? 200 : 500 );
    }

    /**
     * Create task endpoint
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function create_task( $request ) {
        $task_data = $request->get_json_params();
        $task_id = wp_generate_uuid4();
        
        // Store task
        update_option( "ultra_instinct_task_{$task_id}", array(
            'id' => $task_id,
            'type' => $task_data['type'] ?? 'generic',
            'data' => $task_data,
            'status' => 'pending',
            'created_at' => current_time( 'mysql' ),
        ) );
        
        // Send to appropriate agents
        if ( class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            $agent_connector = Ultra_Instinct_Agent_Connector::get_instance();
            $message = array(
                'task_id' => $task_id,
                'task_type' => $task_data['type'] ?? 'generic',
                'task_data' => $task_data,
            );
            
            $agent_types = $task_data['agent_types'] ?? array();
            $results = $agent_connector->broadcast_message( $message, $agent_types );
            
            if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
                Ultra_Instinct_Logger::log(
                    "Task created: {$task_id}",
                    'info',
                    array( 'task_id' => $task_id, 'task_data' => $task_data )
                );
            }
            
            return new WP_REST_Response( array(
                'task_id' => $task_id,
                'status' => 'pending',
                'agents_notified' => count( $results ),
            ), 201 );
        }
        
        return new WP_REST_Response( array(
            'task_id' => $task_id,
            'status' => 'pending',
            'agents_notified' => 0,
        ), 201 );
    }

    /**
     * Get task status endpoint
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function get_task_status( $request ) {
        $task_id = $request->get_param( 'task_id' );
        
        $task = get_option( "ultra_instinct_task_{$task_id}" );
        $result = get_option( "ultra_instinct_task_result_{$task_id}" );
        
        if ( ! $task ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Task not found', 'ultra-instinct' ),
            ), 404 );
        }
        
        return new WP_REST_Response( array(
            'task' => $task,
            'result' => $result,
        ), 200 );
    }

    /**
     * Update plugins
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function update_plugins( $request ) {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return new WP_Error(
                'ultra_instinct_insufficient_permissions',
                __( 'Insufficient permissions to update plugins', 'ultra-instinct' ),
                array( 'status' => 403 )
            );
        }

        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        include_once ABSPATH . 'wp-admin/includes/update.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $plugin = $request->get_param( 'plugin' );
        $results = array();

        if ( $plugin ) {
            // Update specific plugin
            $upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
            $result = $upgrader->upgrade( $plugin );
            
            $results[ $plugin ] = array(
                'success' => ! is_wp_error( $result ),
                'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Plugin updated successfully', 'ultra-instinct' ),
            );
            
            if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
                Ultra_Instinct_Logger::log( "Plugin update attempted: {$plugin}", 'info' );
            }
        } else {
            // Update all plugins
            $plugins = get_plugin_updates();
            
            foreach ( $plugins as $plugin_file => $plugin_data ) {
                $upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
                $result = $upgrader->upgrade( $plugin_file );
                
                $results[ $plugin_file ] = array(
                    'success' => ! is_wp_error( $result ),
                    'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Plugin updated successfully', 'ultra-instinct' ),
                );
            }
            
            if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
                Ultra_Instinct_Logger::log( 'Bulk plugin update attempted', 'info' );
            }
        }

        return new WP_REST_Response( array(
            'success' => true,
            'results' => $results,
        ), 200 );
    }

    /**
     * Install plugin
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function install_plugin( $request ) {
        if ( ! current_user_can( 'install_plugins' ) ) {
            return new WP_Error(
                'ultra_instinct_insufficient_permissions',
                __( 'Insufficient permissions to install plugins', 'ultra-instinct' ),
                array( 'status' => 403 )
            );
        }

        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $slug = $request->get_param( 'slug' );
        $zip_url = $request->get_param( 'zip_url' );

        $upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );

        if ( $zip_url ) {
            $result = $upgrader->install( $zip_url );
        } else {
            $api = plugins_api( 'plugin_information', array( 'slug' => $slug ) );
            
            if ( is_wp_error( $api ) ) {
                return new WP_Error(
                    'ultra_instinct_plugin_not_found',
                    $api->get_error_message(),
                    array( 'status' => 404 )
                );
            }

            $result = $upgrader->install( $api->download_link );
        }

        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log( "Plugin installation attempted: {$slug}", 'info' );
        }

        return new WP_REST_Response( array(
            'success' => ! is_wp_error( $result ),
            'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Plugin installed successfully', 'ultra-instinct' ),
        ), 200 );
    }

    /**
     * Toggle plugin activation
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function toggle_plugin( $request ) {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return new WP_Error(
                'ultra_instinct_insufficient_permissions',
                __( 'Insufficient permissions to manage plugins', 'ultra-instinct' ),
                array( 'status' => 403 )
            );
        }

        $plugin = $request->get_param( 'plugin' );
        $action = $request->get_param( 'action' );

        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        if ( 'activate' === $action ) {
            $result = activate_plugin( $plugin );
            $message = __( 'Plugin activated successfully', 'ultra-instinct' );
        } else {
            deactivate_plugins( $plugin );
            $result = null;
            $message = __( 'Plugin deactivated successfully', 'ultra-instinct' );
        }

        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log( "Plugin {$action}: {$plugin}", 'info' );
        }

        return new WP_REST_Response( array(
            'success' => ! is_wp_error( $result ),
            'message' => is_wp_error( $result ) ? $result->get_error_message() : $message,
        ), 200 );
    }

    /**
     * Create content
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function create_content( $request ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error(
                'ultra_instinct_insufficient_permissions',
                __( 'Insufficient permissions to create content', 'ultra-instinct' ),
                array( 'status' => 403 )
            );
        }

        $title = $request->get_param( 'title' );
        $content = $request->get_param( 'content' );
        $type = $request->get_param( 'type' );
        $status = $request->get_param( 'status' );

        $post_data = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => $type,
            'post_status' => $status,
            'post_author' => get_current_user_id(),
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return new WP_Error(
                'ultra_instinct_content_creation_failed',
                $post_id->get_error_message(),
                array( 'status' => 500 )
            );
        }

        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log( "Content created: {$type} #{$post_id} - {$title}", 'info' );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
            'view_url' => get_permalink( $post_id ),
        ), 201 );
    }

    /**
     * Upload media
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function upload_media( $request ) {
        if ( ! current_user_can( 'upload_files' ) ) {
            return new WP_Error(
                'ultra_instinct_insufficient_permissions',
                __( 'Insufficient permissions to upload files', 'ultra-instinct' ),
                array( 'status' => 403 )
            );
        }

        if ( empty( $_FILES ) ) {
            return new WP_Error(
                'ultra_instinct_no_file',
                __( 'No file provided', 'ultra-instinct' ),
                array( 'status' => 400 )
            );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'file', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            return new WP_Error(
                'ultra_instinct_upload_failed',
                $attachment_id->get_error_message(),
                array( 'status' => 500 )
            );
        }

        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log( "Media uploaded: #{$attachment_id}", 'info' );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url( $attachment_id ),
        ), 201 );
    }

    /**
     * Flush permalinks
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function flush_permalinks( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'ultra_instinct_insufficient_permissions',
                __( 'Insufficient permissions to manage settings', 'ultra-instinct' ),
                array( 'status' => 403 )
            );
        }

        flush_rewrite_rules();
        
        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log( 'Permalinks flushed', 'info' );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Permalinks flushed successfully', 'ultra-instinct' ),
        ), 200 );
    }

    /**
     * Get site information
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function get_site_info( $request ) {
        $theme = wp_get_theme();
        $plugins = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );

        $plugin_info = array();
        foreach ( $plugins as $plugin_file => $plugin_data ) {
            $plugin_info[] = array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'active' => in_array( $plugin_file, $active_plugins, true ),
                'file' => $plugin_file,
            );
        }

        $connected_agents = array();
        if ( class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            $agent_connector = Ultra_Instinct_Agent_Connector::get_instance();
            $connected_agents = $agent_connector->get_connected_agents();
        }

        return new WP_REST_Response( array(
            'site_url' => get_site_url(),
            'admin_email' => get_option( 'admin_email' ),
            'wp_version' => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
            'plugin_version' => ULTRA_INSTINCT_VERSION,
            'theme' => array(
                'name' => $theme->get( 'Name' ),
                'version' => $theme->get( 'Version' ),
                'template' => $theme->get_template(),
            ),
            'plugins' => $plugin_info,
            'users_count' => count_users()['total_users'],
            'posts_count' => wp_count_posts()->publish,
            'pages_count' => wp_count_posts( 'page' )->publish,
            'agents_connected' => count( $connected_agents ),
            'webhook_url' => get_site_url() . '/?ultra_instinct_webhook=1',
            'timezone' => get_option( 'timezone_string' ),
            'language' => get_locale(),
        ), 200 );
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
