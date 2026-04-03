<?php

namespace UptimeHealthEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap — singleton.
 *
 * Wires all hooks conditionally per request context:
 *	- REST requests	 → only REST_Controller boots.
 *	- Admin requests → only Admin + Site_Health boot.
 *	- Frontend		 → nothing boots at all.
 */
final class Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'boot_rest' ] );

		if ( is_admin() ) {
			// One-time redirect to the settings page after plugin activation
			add_action( 'admin_init', [ $this, 'maybe_redirect_after_activation' ] );

			$admin = new Admin();
			add_action( 'admin_menu', [ $admin, 'register_menu' ] );
			add_action( 'admin_init', [ $admin, 'register_settings' ] );
			add_action( 'admin_post_uptiheen_generate_token', [ $admin, 'generate_token' ] );
			add_action( 'admin_post_uptiheen_clear_history', [ $admin, 'clear_history' ] );

			( new Site_Health() )->register();
		}
	}

	public function boot_rest(): void {
		( new REST_Controller() )->register_routes();
	}

	/**
	 * Redirect to the settings page once, immediately after activation.
	 * Skipped during bulk activation (activate-multi) to avoid interrupting the flow.
	 */
	public function maybe_redirect_after_activation(): void {
		if ( ! get_option( 'uptiheen_activation_redirect' ) ) {
			return;
		}
		delete_option( 'uptiheen_activation_redirect' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=wp-uptime-endpoint&activated=1' ) );
		exit;
	}
}
