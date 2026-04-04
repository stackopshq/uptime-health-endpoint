<?php
/**
 * Plugin Name:		  Uptime Health Endpoint
 * Description:		  Provides a /wp-json/wp-uptime/v1/check endpoint for uptime monitoring (Uptime Kuma, UptimeRobot, etc.).
 * Version:			  2.0.0
 * Author:			  Kevin Allioli
 * Author URI:		  https://github.com/kallioli
 * Plugin URI:		  https://github.com/kallioli/uptime-health-endpoint
 * License:			  GPL-2.0+
 * License URI:		  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:		  uptime-health-endpoint
 * Requires at least: 5.0
 * Requires PHP:	  8.2
 */

namespace UptimeHealthEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UPTIHEEN_VERSION', '2.0.0' );

// ── Activation ────────────────────────────────────────────────────────────────

register_activation_hook( __FILE__, __NAMESPACE__ . '\on_activate' );

/**
 * On first activation: generate a token automatically if none is configured,
 * then flag a one-time redirect to the settings page.
 */
function on_activate(): void {
	$has_constant = defined( 'UPTIHEEN_TOKEN' )
		|| ( defined( 'UPTIHEEN_TOKENS' ) && is_array( UPTIHEEN_TOKENS ) );
	$has_option	  = '' !== get_option( 'uptiheen_tokens', '' )
		|| '' !== get_option( 'uptiheen_token', '' );

	if ( ! $has_constant && ! $has_option ) {
		update_option( 'uptiheen_tokens', bin2hex( random_bytes( 32 ) ) );
	}

	// Triggers a one-time redirect on the next admin page load (see Plugin.php)
	add_option( 'uptiheen_activation_redirect', true );
}

// ── Classes ───────────────────────────────────────────────────────────────────

// Load in dependency order (leaf classes first)
require_once __DIR__ . '/src/History.php';
require_once __DIR__ . '/src/Webhook.php';
require_once __DIR__ . '/src/Authenticator.php';
require_once __DIR__ . '/src/Health_Checker.php';
require_once __DIR__ . '/src/REST_Controller.php';
require_once __DIR__ . '/src/Site_Health.php';
require_once __DIR__ . '/src/Admin.php';
require_once __DIR__ . '/src/Plugin.php';

Plugin::instance();

// CLI_Command extends \WP_CLI_Command which only exists when WP-CLI is running.
// Load conditionally to avoid a fatal error on non-CLI requests.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/src/CLI_Command.php';
	\WP_CLI::add_command( 'uptime', __NAMESPACE__ . '\CLI_Command' );
}
