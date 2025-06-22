=== Ultra Instinct Integration ===
Contributors: ultrainstinct
Tags: ai, automation, management, api, integration, agents, webhooks
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced WordPress integration plugin for Ultra Instinct AI agents with real-time connectivity, webhooks, and comprehensive site management capabilities.

== Description ==

Ultra Instinct Integration v2.0 is an advanced WordPress plugin that provides seamless connectivity between your WordPress site and Ultra Instinct AI agents. This plugin enables AI-powered automation and management of your site with enterprise-grade security and real-time communication capabilities.

= Key Features =

* **Advanced Agent Management**: Register, monitor, and manage multiple AI agents
* **Real-time Communication**: WebSocket-like connectivity with webhook support
* **Enhanced Security**: Multi-layer authentication with request signing
* **Comprehensive API**: RESTful endpoints for all WordPress operations
* **Activity Monitoring**: Detailed logging and statistics
* **Task Management**: Create and track long-running tasks
* **Rate Limiting**: Intelligent protection against abuse
* **Multi-site Ready**: Designed for enterprise deployments

= Supported Operations =

* Plugin installation, updates, and activation/deactivation
* Content creation and management (posts, pages, custom post types)
* Media file uploads and management
* User management and permissions
* Theme management and customization
* Database operations and maintenance
* Site settings and configuration
* Real-time monitoring and alerts

= Agent Communication =

* **Agent Registration**: Secure agent onboarding with capability validation
* **Heartbeat Monitoring**: Real-time agent status tracking
* **Webhook Support**: Bidirectional communication with signature verification
* **Task Distribution**: Intelligent task routing to appropriate agents
* **Error Handling**: Comprehensive error reporting and recovery

= Security Features =

* API keys are hashed and encrypted before storage
* Request signature validation prevents replay attacks
* Rate limiting with IP and agent-based tracking
* Comprehensive activity logging with security events
* WordPress capability checks for all operations
* Input validation and sanitization
* CORS support for cross-origin requests

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ultra-instinct-integration` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to Settings → Ultra Instinct to configure the plugin.
4. Choose your preferred connection method and follow the setup instructions.
5. Register your first agent using the provided API endpoints.

== Frequently Asked Questions ==

= How do I connect my site to Ultra Instinct? =

You have two options:
1. Generate an API key in WordPress (Settings → Ultra Instinct) and enter it in the Ultra Instinct platform
2. Connect to the Ultra Instinct platform first and enter the provided API key in WordPress

= How do agents connect to my site? =

Agents connect via REST API endpoints using the generated API key. They can register themselves, send heartbeats, and receive tasks through webhooks.

= Is my data secure? =

Yes, the plugin follows enterprise security best practices:
- API keys are encrypted and hashed before storage
- All communication uses HTTPS with signature verification
- Rate limiting prevents abuse
- Comprehensive logging tracks all activities
- WordPress capabilities are respected for all operations

= Can I monitor agent activity? =

Yes, the plugin provides comprehensive monitoring:
- Real-time agent status dashboard
- Activity logs with filtering and search
- Performance statistics and analytics
- Error tracking and alerting

= What WordPress capabilities are required? =

The plugin respects WordPress user capabilities:
- Plugin management requires `install_plugins`, `update_plugins`, `activate_plugins`
- Content creation requires `edit_posts`
- Media uploads require `upload_files`
- Settings management requires `manage_options`

= Can I revoke access at any time? =

Yes, you can revoke the API key or disconnect individual agents at any time from the plugin settings page. This will immediately stop all agent access.

= Does the plugin support webhooks? =

Yes, the plugin includes a comprehensive webhook system for real-time communication with agents. Webhooks are secured with signature verification.

== Screenshots ==

1. Enhanced dashboard with agent management
2. API key generation and validation interface
3. Connected agents monitoring
4. Activity logs and statistics
5. Advanced settings and configuration
6. Real-time connection status

== Changelog ==

= 2.0.0 =
* Major version upgrade with enhanced agent connectivity
* Added comprehensive agent management system
* Implemented webhook support for real-time communication
* Enhanced security with request signature validation
* Added task management and distribution system
* Improved logging with agent tracking and statistics
* Added rate limiting with agent-specific controls
* Enhanced admin interface with agent monitoring
* Added CORS support for cross-origin requests
* Improved error handling and recovery mechanisms
* Added comprehensive API documentation
* Enhanced performance and scalability

= 1.0.0 =
* Initial release
* Basic API key management
* REST API endpoints for site management
* Activity logging system
* Dual connection methods
* WordPress coding standards compliance

== Upgrade Notice ==

= 2.0.0 =
Major upgrade with enhanced agent connectivity, real-time communication, and comprehensive management features. Backup your site before upgrading.

== Developer Information ==

This plugin provides REST API endpoints under the `ultra-instinct/v2` namespace. All endpoints require authentication via API key in the `X-Ultra-Instinct-Key` header or `Authorization: Bearer` header.

= Agent Management Endpoints =
* `POST /ultra-instinct/v2/agents/register` - Register new agent
* `POST /ultra-instinct/v2/agents/heartbeat` - Send agent heartbeat
* `GET /ultra-instinct/v2/agents/list` - List connected agents
* `POST /ultra-instinct/v2/agents/{id}/disconnect` - Disconnect agent

= WordPress Management Endpoints =
* `GET /ultra-instinct/v2/test` - Test connection
* `POST /ultra-instinct/v2/plugins/update` - Update plugins
* `POST /ultra-instinct/v2/plugins/install` - Install plugins
* `POST /ultra-instinct/v2/plugins/toggle` - Activate/deactivate plugins
* `POST /ultra-instinct/v2/content/create` - Create content
* `POST /ultra-instinct/v2/media/upload` - Upload media
* `GET /ultra-instinct/v2/site/info` - Get site information

= Task Management Endpoints =
* `POST /ultra-instinct/v2/tasks/create` - Create new task
* `GET /ultra-instinct/v2/tasks/{id}/status` - Get task status

= Webhook Support =
* Webhook URL: `{site_url}/?ultra_instinct_webhook=1`
* Signature verification using HMAC-SHA256
* Support for agent heartbeats, status updates, and task completion

For detailed API documentation and agent development guides, visit the Ultra Instinct developer portal.
