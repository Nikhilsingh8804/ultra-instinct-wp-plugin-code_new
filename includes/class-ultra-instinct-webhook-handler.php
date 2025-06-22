<?php
/**
 * Ultra Instinct Webhook Handler Class
 *
 * Handles incoming webhook requests from agents
 *
 * @package UltraInstinct
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ultra Instinct Webhook Handler class
 */
class Ultra_Instinct_Webhook_Handler {

    /**
     * Single instance
     *
     * @var Ultra_Instinct_Webhook_Handler
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Ultra_Instinct_Webhook_Handler
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Handle webhook request
     */
    public function handle_request() {
        // Verify request method
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            $this->send_error( 405, 'Method not allowed' );
            return;
        }

        // Get request body
        $input = file_get_contents( 'php://input' );
        $data = json_decode( $input, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->send_error( 400, 'Invalid JSON' );
            return;
        }

        // Verify signature
        if ( ! $this->verify_signature( $input ) ) {
            $this->send_error( 401, 'Invalid signature' );
            return;
        }

        // Process webhook
        $this->process_webhook( $data );
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Raw payload.
     * @return bool
     */
    private function verify_signature( $payload ) {
        $signature = $_SERVER['HTTP_X_ULTRA_INSTINCT_SIGNATURE'] ?? '';
        
        if ( empty( $signature ) ) {
            return false;
        }

        $settings = get_option( 'ultra_instinct_settings', array() );
        $secret = $settings['webhook_secret'] ?? '';
        
        if ( empty( $secret ) ) {
            return false;
        }

        $expected_signature = hash_hmac( 'sha256', $payload, $secret );
        
        return hash_equals( $expected_signature, $signature );
    }

    /**
     * Process webhook data
     *
     * @param array $data Webhook data.
     */
    private function process_webhook( $data ) {
        $event_type = $data['event'] ?? '';
        $agent_id = $data['agent_id'] ?? '';

        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log(
                "Webhook received: {$event_type}",
                'info',
                array( 'agent_id' => $agent_id, 'event' => $event_type )
            );
        }

        switch ( $event_type ) {
            case 'agent_heartbeat':
                $this->handle_agent_heartbeat( $data );
                break;
                
            case 'agent_status_update':
                $this->handle_agent_status_update( $data );
                break;
                
            case 'task_completed':
                $this->handle_task_completed( $data );
                break;
                
            case 'task_failed':
                $this->handle_task_failed( $data );
                break;
                
            case 'agent_error':
                $this->handle_agent_error( $data );
                break;
                
            default:
                $this->send_error( 400, 'Unknown event type' );
                return;
        }

        $this->send_success( array( 'message' => 'Webhook processed successfully' ) );
    }

    /**
     * Handle agent heartbeat
     *
     * @param array $data Heartbeat data.
     */
    private function handle_agent_heartbeat( $data ) {
        if ( class_exists( 'Ultra_Instinct_Agent_Connector' ) ) {
            $agent_connector = Ultra_Instinct_Agent_Connector::get_instance();
            $agent_connector->update_agent_heartbeat( $data['agent_id'], $data );
        }
    }

    /**
     * Handle agent status update
     *
     * @param array $data Status update data.
     */
    private function handle_agent_status_update( $data ) {
        $agent_id = $data['agent_id'];
        $status = $data['status'] ?? 'active';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ultra_instinct_agent_connections';
        
        $wpdb->update(
            $table_name,
            array(
                'status' => sanitize_text_field( $status ),
                'last_seen' => current_time( 'mysql' ),
            ),
            array( 'agent_id' => $agent_id )
        );
    }

    /**
     * Handle task completed
     *
     * @param array $data Task completion data.
     */
    private function handle_task_completed( $data ) {
        $task_id = $data['task_id'] ?? '';
        $result = $data['result'] ?? array();
        
        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log(
                "Task completed: {$task_id}",
                'info',
                array(
                    'agent_id' => $data['agent_id'],
                    'task_id' => $task_id,
                    'result' => $result,
                )
            );
        }

        // Store task result
        update_option( "ultra_instinct_task_result_{$task_id}", array(
            'status' => 'completed',
            'result' => $result,
            'completed_at' => current_time( 'mysql' ),
            'agent_id' => $data['agent_id'],
        ) );
    }

    /**
     * Handle task failed
     *
     * @param array $data Task failure data.
     */
    private function handle_task_failed( $data ) {
        $task_id = $data['task_id'] ?? '';
        $error = $data['error'] ?? 'Unknown error';
        
        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log(
                "Task failed: {$task_id} - {$error}",
                'error',
                array(
                    'agent_id' => $data['agent_id'],
                    'task_id' => $task_id,
                    'error' => $error,
                )
            );
        }

        // Store task failure
        update_option( "ultra_instinct_task_result_{$task_id}", array(
            'status' => 'failed',
            'error' => $error,
            'failed_at' => current_time( 'mysql' ),
            'agent_id' => $data['agent_id'],
        ) );
    }

    /**
     * Handle agent error
     *
     * @param array $data Error data.
     */
    private function handle_agent_error( $data ) {
        $error_message = $data['error'] ?? 'Unknown agent error';
        
        if ( class_exists( 'Ultra_Instinct_Logger' ) ) {
            Ultra_Instinct_Logger::log(
                "Agent error: {$error_message}",
                'error',
                array(
                    'agent_id' => $data['agent_id'],
                    'error_details' => $data,
                )
            );
        }
    }

    /**
     * Send success response
     *
     * @param array $data Response data.
     */
    private function send_success( $data = array() ) {
        wp_send_json_success( $data );
    }

    /**
     * Send error response
     *
     * @param int    $code Error code.
     * @param string $message Error message.
     */
    private function send_error( $code, $message ) {
        status_header( $code );
        wp_send_json_error( array( 'message' => $message ) );
    }
}
