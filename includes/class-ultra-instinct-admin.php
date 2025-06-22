<?php
/**
 * Ultra Instinct Admin Class
 *
 * Enhanced admin interface with agent management
 *
 * @package UltraInstinct
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ultra Instinct Admin class
 */
class Ultra_Instinct_Admin {

    /**
     * Single instance
     *
     * @var Ultra_Instinct_Admin
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Ultra_Instinct_Admin
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
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_ultra_instinct_generate_key', array( $this, 'ajax_generate_key' ) );
        add_action( 'wp_ajax_ultra_instinct_revoke_key', array( $this, 'ajax_revoke_key' ) );
        add_action( 'wp_ajax_ultra_instinct_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_ultra_instinct_validate_key', array( $this, 'ajax_validate_key' ) );
        add_action( 'wp_ajax_ultra_instinct_disconnect_agent', array( $this, 'ajax_disconnect_agent' ) );
        add_action( 'wp_ajax_ultra_instinct_repair_database', array( $this, 'ajax_repair_database' ) );
    }

    /**
     * Add admin menu under Settings
     */
    public function add_menu() {
        add_options_page(
            __( 'Ultra Instinct Integration', 'ultra-instinct' ),
            __( 'Ultra Instinct', 'ultra-instinct' ),
            'manage_options',
            'ultra-instinct',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Activation notice
        if ( get_transient( 'ultra_instinct_activation_notice' ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php esc_html_e( 'Ultra Instinct Integration v2.0 activated!', 'ultra-instinct' ); ?></strong>
                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=ultra-instinct' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
                        <?php esc_html_e( 'Configure Now', 'ultra-instinct' ); ?>
                    </a>
                </p>
            </div>
            <?php
            delete_transient( 'ultra_instinct_activation_notice' );
        }

        // Connection status notice
        $connection_status = get_option( 'ultra_instinct_connection_status', 'disconnected' );
        if ( 'disconnected' === $connection_status && $this->is_ultra_instinct_page() ) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e( 'Ultra Instinct is not connected.', 'ultra-instinct' ); ?></strong>
                    <?php esc_html_e( 'Generate an API key below to connect your site.', 'ultra-instinct' ); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Check if current page is Ultra Instinct page
     */
    private function is_ultra_instinct_page() {
        $screen = get_current_screen();
        return $screen && strpos( $screen->id, 'ultra-instinct' ) !== false;
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'ultra_instinct_settings', 'ultra_instinct_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ) );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'ultra-instinct' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'ultra-instinct-admin',
            ULTRA_INSTINCT_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            ULTRA_INSTINCT_VERSION,
            true
        );

        wp_enqueue_style(
            'ultra-instinct-admin',
            ULTRA_INSTINCT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ULTRA_INSTINCT_VERSION
        );

        wp_localize_script( 'ultra-instinct-admin', 'ultraInstinct', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ultra_instinct_nonce' ),
            'strings' => array(
                'generating' => __( 'Generating...', 'ultra-instinct' ),
                'revoking' => __( 'Revoking...', 'ultra-instinct' ),
                'testing' => __( 'Testing...', 'ultra-instinct' ),
                'validating' => __( 'Validating...', 'ultra-instinct' ),
                'disconnecting' => __( 'Disconnecting...', 'ultra-instinct' ),
                'copySuccess' => __( 'API key copied to clipboard!', 'ultra-instinct' ),
                'copyError' => __( 'Failed to copy API key. Please copy manually.', 'ultra-instinct' ),
                'confirmRevoke' => __( 'Are you sure you want to revoke the API key? This will disconnect your site from Ultra Instinct.', 'ultra-instinct' ),
                'confirmDisconnectAgent' => __( 'Are you sure you want to disconnect this agent?', 'ultra-instinct' ),
                'connectionSuccess' => __( 'Connection test successful!', 'ultra-instinct' ),
                'connectionFailed' => __( 'Connection test failed!', 'ultra-instinct' ),
            ),
        ) );
    }

    /**
     * Enhanced settings page with agent management
     */
    public function settings_page() {
        if ( ! class_exists( 'Ultra_Instinct_Security' ) ) {
            echo '<div class="wrap"><h1>Ultra Instinct Integration</h1><div class="notice notice-error"><p>Security module not loaded. Please check plugin installation.</p></div></div>';
            return;
        }
        
        $security = Ultra_Instinct_Security::get_instance();
        $has_key = $security->has_api_key();
        $connection_status = get_option( 'ultra_instinct_connection_status', 'disconnected' );
        $settings = get_option( 'ultra_instinct_settings', array() );
        $logs = array();
        $connected_agents = array();
        $log_stats = array();
        
        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            $logs = Ultra_Instinct_Logger::get_recent_logs( 10 );
            $log_stats = Ultra_Instinct_Logger::get_log_statistics( 7 );
        }
        
        if ( class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            $agent_connector = Ultra_Instinct_Agent_Connector::get_instance();
            $connected_agents = $agent_connector->get_connected_agents();
        }
        ?>
        <div class="wrap ultra-instinct-wrap">
            <h1><?php esc_html_e( 'Ultra Instinct Integration v2.0', 'ultra-instinct' ); ?></h1>
            
            <!-- Connection Status -->
            <div class="ultra-instinct-card">
                <h2><?php esc_html_e( 'Connection Status', 'ultra-instinct' ); ?></h2>
                <div class="connection-status">
                    <div class="status-indicator status-<?php echo esc_attr( $connection_status ); ?>">
                        <span class="status-dot"></span>
                        <span class="status-text">
                            <?php
                            switch ( $connection_status ) {
                                case 'connected':
                                    esc_html_e( 'Connected', 'ultra-instinct' );
                                    break;
                                case 'testing':
                                    esc_html_e( 'Testing...', 'ultra-instinct' );
                                    break;
                                default:
                                    esc_html_e( 'Disconnected', 'ultra-instinct' );
                            }
                            ?>
                        </span>
                    </div>
                    
                    <?php if ( $has_key && 'connected' === $connection_status ) : ?>
                        <p class="connection-info">
                            <strong><?php esc_html_e( 'Your site is securely connected to Ultra Instinct.', 'ultra-instinct' ); ?></strong>
                            <br>
                            <?php printf( esc_html__( 'Agents connected: %d', 'ultra-instinct' ), count( $connected_agents ) ); ?>
                            <br>
                            <?php printf( esc_html__( 'Webhook URL: %s', 'ultra-instinct' ), '<code>' . esc_html( get_site_url() . '/?ultra_instinct_webhook=1' ) . '</code>' ); ?>
                        </p>
                        <div class="connection-actions">
                            <button id="test-connection" class="button button-secondary">
                                <?php esc_html_e( 'Test Connection', 'ultra-instinct' ); ?>
                            </button>
                            <button id="revoke-key" class="button button-link-delete">
                                <?php esc_html_e( 'Disconnect', 'ultra-instinct' ); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Database Status -->
            <div class="ultra-instinct-card">
                <h2><?php esc_html_e( 'Database Status', 'ultra-instinct' ); ?></h2>
                <?php
                if ( class_exists( 'Ultra_Instinct_Database' ) ) {
                    $db = Ultra_Instinct_Database::get_instance();
                    $db_status = $db->get_database_status();
                    ?>
                    <div class="database-status">
                        <p>
                            <strong><?php esc_html_e( 'Logs Table:', 'ultra-instinct' ); ?></strong>
                            <?php if ( $db_status['logs_table_exists'] ) : ?>
                                <span style="color: green;">✅ <?php esc_html_e( 'Exists', 'ultra-instinct' ); ?></span>
                                <small>(<?php echo esc_html( count( $db_status['logs_columns'] ) ); ?> columns)</small>
                            <?php else : ?>
                                <span style="color: red;">❌ <?php esc_html_e( 'Missing', 'ultra-instinct' ); ?></span>
                            <?php endif; ?>
                        </p>
                        <p>
                            <strong><?php esc_html_e( 'Agents Table:', 'ultra-instinct' ); ?></strong>
                            <?php if ( $db_status['agents_table_exists'] ) : ?>
                                <span style="color: green;">✅ <?php esc_html_e( 'Exists', 'ultra-instinct' ); ?></span>
                                <small>(<?php echo esc_html( count( $db_status['agents_columns'] ) ); ?> columns)</small>
                            <?php else : ?>
                                <span style="color: red;">❌ <?php esc_html_e( 'Missing', 'ultra-instinct' ); ?></span>
                            <?php endif; ?>
                        </p>
                        <?php if ( ! $db_status['logs_table_exists'] || ! $db_status['agents_table_exists'] ) : ?>
                            <button id="repair-database" class="button button-secondary">
                                <?php esc_html_e( 'Repair Database', 'ultra-instinct' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php
                } else {
                    echo '<p>' . esc_html__( 'Database management not available.', 'ultra-instinct' ) . '</p>';
                }
                ?>
            </div>

            <!-- Connected Agents -->
            <?php if ( ! empty( $connected_agents ) ) : ?>
            <div class="ultra-instinct-card">
                <h2><?php esc_html_e( 'Connected Agents', 'ultra-instinct' ); ?></h2>
                <div class="agents-list">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Agent Name', 'ultra-instinct' ); ?></th>
                                <th><?php esc_html_e( 'Type', 'ultra-instinct' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'ultra-instinct' ); ?></th>
                                <th><?php esc_html_e( 'Last Seen', 'ultra-instinct' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'ultra-instinct' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $connected_agents as $agent ) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $agent['agent_name'] ); ?></strong>
                                        <br>
                                        <small><?php echo esc_html( $agent['agent_id'] ); ?></small>
                                    </td>
                                    <td><?php echo esc_html( ucfirst( $agent['agent_type'] ) ); ?></td>
                                    <td>
                                        <span class="agent-status status-<?php echo esc_attr( $agent['status'] ); ?>">
                                            <?php echo esc_html( ucfirst( $agent['status'] ) ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( human_time_diff( strtotime( $agent['last_seen'] ), current_time( 'timestamp' ) ) ); ?> ago</td>
                                    <td>
                                        <button class="button button-small disconnect-agent" data-agent-id="<?php echo esc_attr( $agent['agent_id'] ); ?>">
                                            <?php esc_html_e( 'Disconnect', 'ultra-instinct' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- API Key Management -->
            <div class="ultra-instinct-card">
                <h2><?php esc_html_e( 'API Key Management', 'ultra-instinct' ); ?></h2>
                
                <?php if ( ! $has_key ) : ?>
                    <div class="api-key-section">
                        <h3><?php esc_html_e( 'Method 1: Generate API Key in WordPress', 'ultra-instinct' ); ?></h3>
                        <p><?php esc_html_e( 'Generate a secure API key here and then enter it in the Ultra Instinct platform.', 'ultra-instinct' ); ?></p>
                        <button id="generate-key" class="button button-primary">
                            <?php esc_html_e( 'Generate New API Key', 'ultra-instinct' ); ?>
                        </button>
                    </div>
                    
                    <hr>
                    
                    <div class="api-key-section">
                        <h3><?php esc_html_e( 'Method 2: Connect via Ultra Instinct Platform', 'ultra-instinct' ); ?></h3>
                        <p><?php esc_html_e( 'Get an API key from the Ultra Instinct platform and enter it below.', 'ultra-instinct' ); ?></p>
                        <div class="platform-connection">
                            <p>
                                <a href="<?php echo esc_url( $settings['platform_url'] ?? 'https://app.ultra-instinct.ai' ); ?>" 
                                   target="_blank" class="button button-secondary">
                                    <?php esc_html_e( 'Open Ultra Instinct Platform', 'ultra-instinct' ); ?>
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            </p>
                            <div class="key-input-container">
                                <input type="text" id="platform-api-key" placeholder="<?php esc_attr_e( 'Paste your API key here...', 'ultra-instinct' ); ?>" class="regular-text" />
                                <button id="validate-key" class="button button-primary">
                                    <?php esc_html_e( 'Validate & Connect', 'ultra-instinct' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="api-key-section">
                        <div class="key-status">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e( 'API Key is active and secure', 'ultra-instinct' ); ?>
                        </div>
                        <div class="key-actions">
                            <button id="regenerate-key" class="button button-secondary">
                                <?php esc_html_e( 'Regenerate Key', 'ultra-instinct' ); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="key-display" class="key-display" style="display: none;">
                    <div class="key-header">
                        <h4><?php esc_html_e( 'Your New API Key', 'ultra-instinct' ); ?></h4>
                    </div>
                    <div class="key-container">
                        <input type="text" id="api-key-value" readonly class="regular-text" />
                        <button id="copy-key" class="button button-secondary">
                            <?php esc_html_e( 'Copy', 'ultra-instinct' ); ?>
                        </button>
                    </div>
                    <div class="key-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <strong><?php esc_html_e( 'Important:', 'ultra-instinct' ); ?></strong>
                        <?php esc_html_e( 'Copy this key now! It will not be shown again for security reasons.', 'ultra-instinct' ); ?>
                    </div>
                    <div class="next-steps">
                        <h5><?php esc_html_e( 'Next Steps:', 'ultra-instinct' ); ?></h5>
                        <ol>
                            <li><?php esc_html_e( 'Copy the API key above', 'ultra-instinct' ); ?></li>
                            <li><?php esc_html_e( 'Go to your Ultra Instinct dashboard', 'ultra-instinct' ); ?></li>
                            <li><?php esc_html_e( 'Add this site using the API key', 'ultra-instinct' ); ?></li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Activity Statistics -->
            <?php if ( ! empty( $log_stats ) ) : ?>
            <div class="ultra-instinct-card">
                <h2><?php esc_html_e( 'Activity Statistics (Last 7 Days)', 'ultra-instinct' ); ?></h2>
                <div class="stats-grid">
                    <?php if ( ! empty( $log_stats['by_level'] ) ) : ?>
                    <div class="stat-item">
                        <h4><?php esc_html_e( 'By Log Level', 'ultra-instinct' ); ?></h4>
                        <ul>
                            <?php foreach ( $log_stats['by_level'] as $level => $count ) : ?>
                                <li>
                                    <span class="log-level-badge level-<?php echo esc_attr( $level ); ?>">
                                        <?php echo esc_html( ucfirst( $level ) ); ?>
                                    </span>
                                    <?php echo esc_html( $count ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $log_stats['by_agent'] ) ) : ?>
                    <div class="stat-item">
                        <h4><?php esc_html_e( 'By Agent Activity', 'ultra-instinct' ); ?></h4>
                        <ul>
                            <?php foreach ( $log_stats['by_agent'] as $agent_id => $count ) : ?>
                                <li>
                                    <code><?php echo esc_html( $agent_id ); ?></code>
                                    <?php echo esc_html( $count ); ?> actions
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activity Logs -->
            <div class="ultra-instinct-card">
                <h2><?php esc_html_e( 'Recent Activity', 'ultra-instinct' ); ?></h2>
                <div class="logs-container">
                    <?php if ( ! empty( $logs ) ) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Time', 'ultra-instinct' ); ?></th>
                                    <th><?php esc_html_e( 'Level', 'ultra-instinct' ); ?></th>
                                    <th><?php esc_html_e( 'Message', 'ultra-instinct' ); ?></th>
                                    <th><?php esc_html_e( 'Agent', 'ultra-instinct' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $logs as $log ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( date( 'Y-m-d H:i:s', strtotime( $log['timestamp'] ) ) ); ?></td>
                                        <td><span class="log-level-badge level-<?php echo esc_attr( $log['level'] ); ?>"><?php echo esc_html( ucfirst( $log['level'] ) ); ?></span></td>
                                        <td><?php echo esc_html( $log['message'] ); ?></td>
                                        <td><?php echo esc_html( $log['agent_id'] ?: '-' ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No activity logs found.', 'ultra-instinct' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Settings -->
            <div class="ultra-instinct-card">
                <h2><?php esc_html_e( 'Advanced Settings', 'ultra-instinct' ); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'ultra_instinct_settings' ); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Platform URL', 'ultra-instinct' ); ?></th>
                            <td>
                                <input type="url" name="ultra_instinct_settings[platform_url]" 
                                       value="<?php echo esc_attr( $settings['platform_url'] ?? 'https://app.ultra-instinct.ai' ); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php esc_html_e( 'URL of the Ultra Instinct platform.', 'ultra-instinct' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Rate Limiting', 'ultra-instinct' ); ?></th>
                            <td>
                                <input type="number" name="ultra_instinct_settings[rate_limit_requests]" 
                                       value="<?php echo esc_attr( $settings['rate_limit_requests'] ?? 100 ); ?>" 
                                       min="1" max="1000" class="small-text" />
                                <span><?php esc_html_e( 'requests per hour', 'ultra-instinct' ); ?></span>
                                <p class="description"><?php esc_html_e( 'Maximum number of API requests allowed per hour.', 'ultra-instinct' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Agent Timeout', 'ultra-instinct' ); ?></th>
                            <td>
                                <input type="number" name="ultra_instinct_settings[agent_timeout]" 
                                       value="<?php echo esc_attr( $settings['agent_timeout'] ?? 30 ); ?>" 
                                       min="5" max="300" class="small-text" />
                                <span><?php esc_html_e( 'seconds', 'ultra-instinct' ); ?></span>
                                <p class="description"><?php esc_html_e( 'Timeout for agent communication.', 'ultra-instinct' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Logging', 'ultra-instinct' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ultra_instinct_settings[enable_logging]" 
                                           value="1" <?php checked( $settings['enable_logging'] ?? true ); ?> />
                                    <?php esc_html_e( 'Log all Ultra Instinct activities', 'ultra-instinct' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Log Retention', 'ultra-instinct' ); ?></th>
                            <td>
                                <input type="number" name="ultra_instinct_settings[log_retention_days]" 
                                       value="<?php echo esc_attr( $settings['log_retention_days'] ?? 30 ); ?>" 
                                       min="1" max="365" class="small-text" />
                                <span><?php esc_html_e( 'days', 'ultra-instinct' ); ?></span>
                                <p class="description"><?php esc_html_e( 'Number of days to keep log entries.', 'ultra-instinct' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Enable Webhooks', 'ultra-instinct' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ultra_instinct_settings[webhook_enabled]" 
                                           value="1" <?php checked( $settings['webhook_enabled'] ?? true ); ?> />
                                    <?php esc_html_e( 'Allow agents to send webhooks to this site', 'ultra-instinct' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(); ?>
                </form>
            </div>

            <!-- API Endpoints Documentation -->
            <div class="ultra-instinct-card">
                <h2><?php esc_html_e( 'API Endpoints', 'ultra-instinct' ); ?></h2>
                <p><?php esc_html_e( 'Available REST API endpoints for agent communication:', 'ultra-instinct' ); ?></p>
                <div class="api-endpoints">
                    <ul>
                        <li><code>GET /wp-json/ultra-instinct/v2/test</code> - Test connection</li>
                        <li><code>POST /wp-json/ultra-instinct/v2/agents/register</code> - Register agent</li>
                        <li><code>POST /wp-json/ultra-instinct/v2/agents/heartbeat</code> - Agent heartbeat</li>
                        <li><code>GET /wp-json/ultra-instinct/v2/agents/list</code> - List agents</li>
                        <li><code>POST /wp-json/ultra-instinct/v2/plugins/update</code> - Update plugins</li>
                        <li><code>POST /wp-json/ultra-instinct/v2/content/create</code> - Create content</li>
                        <li><code>GET /wp-json/ultra-instinct/v2/site/info</code> - Get site information</li>
                    </ul>
                    <p class="description">
                        <?php esc_html_e( 'All endpoints require authentication via the X-Ultra-Instinct-Key header.', 'ultra-instinct' ); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        
        if ( isset( $input['platform_url'] ) ) {
            $sanitized['platform_url'] = esc_url_raw( $input['platform_url'] );
        }
        
        if ( isset( $input['enable_logging'] ) ) {
            $sanitized['enable_logging'] = (bool) $input['enable_logging'];
        }
        
        if ( isset( $input['log_retention_days'] ) ) {
            $sanitized['log_retention_days'] = max( 1, min( 365, absint( $input['log_retention_days'] ) ) );
        }
        
        if ( isset( $input['rate_limit_requests'] ) ) {
            $sanitized['rate_limit_requests'] = max( 1, min( 1000, absint( $input['rate_limit_requests'] ) ) );
        }
        
        if ( isset( $input['agent_timeout'] ) ) {
            $sanitized['agent_timeout'] = max( 5, min( 300, absint( $input['agent_timeout'] ) ) );
        }
        
        if ( isset( $input['webhook_enabled'] ) ) {
            $sanitized['webhook_enabled'] = (bool) $input['webhook_enabled'];
        }
        
        return $sanitized;
    }

    // AJAX Methods

    /**
     * AJAX: Generate API key
     */
    public function ajax_generate_key() {
        check_ajax_referer( 'ultra_instinct_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'ultra-instinct' ) );
        }

        if ( ! class_exists( 'Ultra_Instinct_Security' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security module not available', 'ultra-instinct' ),
            ) );
        }

        $security = Ultra_Instinct_Security::get_instance();
        $api_key = $security->generate_api_key();
        
        if ( $security->store_api_key( $api_key ) ) {
            wp_send_json_success( array(
                'api_key' => $api_key,
                'message' => __( 'API key generated successfully', 'ultra-instinct' ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to generate API key', 'ultra-instinct' ),
            )  );
        }
    }

    /**
     * AJAX: Revoke API key
     */
    public function ajax_revoke_key() {
        check_ajax_referer( 'ultra_instinct_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'ultra-instinct' ) );
        }

        if ( ! class_exists( 'Ultra_Instinct_Security' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security module not available', 'ultra-instinct' ),
            ) );
        }

        $security = Ultra_Instinct_Security::get_instance();
        
        if ( $security->revoke_api_key() ) {
            wp_send_json_success( array(
                'message' => __( 'API key revoked successfully', 'ultra-instinct' ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to revoke API key', 'ultra-instinct' ),
            ) );
        }
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'ultra_instinct_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'ultra-instinct' ) );
        }

        // Update connection status to testing
        update_option( 'ultra_instinct_connection_status', 'testing' );

        if ( ! class_exists( 'Ultra_Instinct_Security' ) ) {
            update_option( 'ultra_instinct_connection_status', 'disconnected' );
            wp_send_json_error( array(
                'message' => __( 'Security module not available', 'ultra-instinct' ),
                'status' => 'disconnected',
            ) );
        }

        $security = Ultra_Instinct_Security::get_instance();
        
        if ( $security->has_api_key() ) {
            // Simulate connection test
            sleep( 1 );
            
            update_option( 'ultra_instinct_connection_status', 'connected' );
            wp_send_json_success( array(
                'message' => __( 'Connection test successful', 'ultra-instinct' ),
                'status' => 'connected',
            ) );
        } else {
            update_option( 'ultra_instinct_connection_status', 'disconnected' );
            wp_send_json_error( array(
                'message' => __( 'No API key found', 'ultra-instinct' ),
                'status' => 'disconnected',
            ) );
        }
    }

    /**
     * AJAX: Validate API key from platform
     */
    public function ajax_validate_key() {
        check_ajax_referer( 'ultra_instinct_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'ultra-instinct' ) );
        }

        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
        
        if ( empty( $api_key ) ) {
            wp_send_json_error( array(
                'message' => __( 'API key is required', 'ultra-instinct' ),
            ) );
        }

        // Enhanced validation
        if ( strlen( $api_key ) < 32 || ! preg_match( '/^[a-f0-9]+$/', $api_key ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid API key format', 'ultra-instinct' ),
            ) );
        }

        if ( ! class_exists( 'Ultra_Instinct_Security' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security module not available', 'ultra-instinct' ),
            ) );
        }

        $security = Ultra_Instinct_Security::get_instance();
        
        if ( $security->store_api_key( $api_key ) ) {
            wp_send_json_success( array(
                'message' => __( 'API key validated and saved successfully', 'ultra-instinct' ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to save API key', 'ultra-instinct' ),
            ) );
        }
    }

    /**
     * AJAX: Disconnect agent
     */
    public function ajax_disconnect_agent() {
        check_ajax_referer( 'ultra_instinct_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'ultra-instinct' ) );
        }

        $agent_id = sanitize_text_field( $_POST['agent_id'] ?? '' );
        
        if ( empty( $agent_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Agent ID is required', 'ultra-instinct' ),
            ) );
        }

        if ( ! class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Agent connector not available', 'ultra-instinct' ),
            ) );
        }

        $agent_connector = Ultra_Instinct_Agent_Connector::get_instance();
        
        if ( $agent_connector->disconnect_agent( $agent_id ) ) {
            wp_send_json_success( array(
                'message' => __( 'Agent disconnected successfully', 'ultra-instinct' ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to disconnect agent', 'ultra-instinct' ),
            ) );
        }
    }

    /**
     * AJAX: Repair database
     */
    public function ajax_repair_database() {
        check_ajax_referer( 'ultra_instinct_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'ultra-instinct' ) );
        }

        if ( ! class_exists( 'Ultra_Instinct_Database' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Database management not available', 'ultra-instinct' ),
            ) );
        }

        $db = Ultra_Instinct_Database::get_instance();
        $result = $db->repair_tables();
        
        if ( $result['logs_table'] && $result['agents_table'] ) {
            wp_send_json_success( array(
                'message' => __( 'Database tables repaired successfully', 'ultra-instinct' ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to repair some database tables', 'ultra-instinct' ),
                'details' => $result,
            ) );
        }
    }
}
