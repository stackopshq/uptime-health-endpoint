<?php
/**
 * Fired when the plugin is deleted from the WordPress admin.
 * Removes all options and transients created by the plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ── Options ───────────────────────────────────────────────────────────────────

$uptiheen_options = [
	// Auth
	'uptiheen_token',
	'uptiheen_tokens',
	'uptiheen_ip_allowlist',
	// Checks
	'uptiheen_homepage',
	'uptiheen_check_cron',
	'uptiheen_check_memory',
	'uptiheen_check_disk',
	'uptiheen_disk_threshold',
	'uptiheen_check_uploads',
	'uptiheen_check_http',
	'uptiheen_check_cache',
	// Notifications
	'uptiheen_webhook_url',
	// Runtime (non-autoloaded)
	'uptiheen_history',
	'uptiheen_last_status',
	// Activation
	'uptiheen_activation_redirect',
];

foreach ( $uptiheen_options as $uptiheen_option ) {
	delete_option( $uptiheen_option );
}

// ── Transients ────────────────────────────────────────────────────────────────

// Known transients with fixed keys
delete_transient( 'uptiheen_http_probe' );

// Rate-limiting transients have dynamic keys: uptiheen_rate_{md5(ip)}
// Use a LIKE query to wipe them all at once rather than tracking every IP.
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_uptiheen_rate_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_uptiheen_rate_' ) . '%'
	)
);
