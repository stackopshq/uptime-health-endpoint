<?php

namespace UptimeHealthEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs all health checks in ascending order of execution cost.
 *
 * Short-circuits on the first failure when UPTIHEEN_DEBUG is off, so the
 * endpoint stays fast even when something is broken.
 * In debug mode, all checks run so every failure is reported at once.
 *
 * Third-party code can inject custom checks via the 'uptiheen_checks' filter:
 *
 *	 add_filter( 'uptiheen_checks', function( array $checks ): array {
 *		 $checks['my_service'] = fn() => my_service_ping() === true;
 *		 return $checks;
 *	 } );
 *
 * Available wp-config.php constants (all optional, override admin panel settings):
 *	 UPTIHEEN_HOMEPAGE		  bool	— homepage loopback check
 *	 UPTIHEEN_CHECK_CRON	  bool	— WP-Cron queue health
 *	 UPTIHEEN_CHECK_MEMORY	  bool	— PHP memory usage
 *	 UPTIHEEN_CHECK_DISK	  bool	— available disk space
 *	 UPTIHEEN_DISK_THRESHOLD  int	— free space threshold in MB (default: 500)
 *	 UPTIHEEN_CHECK_UPLOADS	  bool	— uploads directory writable
 *	 UPTIHEEN_CHECK_HTTP	  bool	— outbound HTTP connectivity
 *	 UPTIHEEN_CHECK_CACHE	  bool	— object cache roundtrip
 */
final class Health_Checker {

	private bool $debug;

	public function __construct() {
		$this->debug = defined( 'UPTIHEEN_DEBUG' ) && (bool) UPTIHEEN_DEBUG;
	}

	/**
	 * Runs all checks without short-circuiting; every check always executes.
	 * Returns a per-check status map alongside errors and durations.
	 * Intended for the /status endpoint used by Zabbix and management consoles.
	 *
	 * @return array{ errors: string[], checks: array<string,string>, durations: array<string,int> }
	 */
	public function run_detailed(): array {
		$errors    = [];
		$checks    = [];
		$durations = [];

		foreach ( $this->build_checks() as $key => $fn ) {
			$t  = microtime( true );
			$ok = (bool) $fn();
			$durations[ $key ] = (int) round( ( microtime( true ) - $t ) * 1000 );
			$checks[ $key ]    = $ok ? 'ok' : 'fail';
			if ( ! $ok ) {
				$errors[] = $key;
			}
		}

		return [
			'errors'    => $errors,
			'checks'    => $checks,
			'durations' => $durations,
		];
	}

	/**
	 * @return array{ errors: string[], durations: array<string,int> }
	 */
	public function run(): array {
		$errors	   = [];
		$durations = [];

		foreach ( $this->build_checks() as $key => $fn ) {
			$t	= microtime( true );
			$ok = (bool) $fn();
			$durations[ $key ] = (int) round( ( microtime( true ) - $t ) * 1000 );

			if ( ! $ok ) {
				$errors[] = $key;
				if ( ! $this->debug ) {
					break; // Short-circuit: status is already fail
				}
			}
		}

		return [
			'errors'    => $errors,
			'durations' => $durations,
		];
	}

	/**
	 * Builds the ordered list of checks to run.
	 * Order: cheapest → most expensive (in-memory < DB < network).
	 *
	 * @return array<string, callable>
	 */
	private function build_checks(): array {
		$cfg = $this->cfg();

		$checks = [
			'db'		 => [ $this, 'check_database' ],   // SELECT 1
			'wp_options' => [ $this, 'check_options' ],	   // WP object-cached
			'theme'		 => [ $this, 'check_theme' ],	   // in-memory
		];

		if ( $cfg['cron'] ) {
			$checks['cron'] = [ $this, 'check_cron' ];
		}
		if ( $cfg['memory'] ) {
			$checks['memory'] = [ $this, 'check_memory' ];
		}
		if ( $cfg['uploads'] ) {
			$checks['uploads'] = [ $this, 'check_uploads' ];
		}
		if ( $cfg['disk'] ) {
			$checks['disk'] = [ $this, 'check_disk' ];
		}
		if ( $cfg['cache'] ) {
			$checks['cache'] = [ $this, 'check_object_cache' ];
		}

		// Plugin monitoring — only added when at least one plugin is selected.
		$monitored_plugins = (array) get_option( 'uptiheen_monitored_plugins', [] );
		if ( ! empty( $monitored_plugins ) ) {
			$checks['plugins'] = [ $this, 'check_required_plugins' ];
		}

		// Network calls last — both can be slow.
		if ( $cfg['http'] ) {
			$checks['http'] = [ $this, 'check_http_outbound' ];
		}
		if ( $cfg['homepage'] ) {
			$checks['homepage'] = [ $this, 'check_homepage' ];
		}

		// Third-party / theme custom checks
		foreach ( (array) apply_filters( 'uptiheen_checks', [] ) as $key => $fn ) {
			if ( is_string( $key ) && is_callable( $fn ) ) {
				$checks[ sanitize_key( $key ) ] = $fn;
			}
		}

		return $checks;
	}

	/** Read all toggle options in one pass (WP caches get_option internally). */
	private function cfg(): array {
		return [
			'cron'     => $this->opt( 'uptiheen_check_cron', 'UPTIHEEN_CHECK_CRON', true ),
			'memory'   => $this->opt( 'uptiheen_check_memory', 'UPTIHEEN_CHECK_MEMORY', true ),
			'uploads'  => $this->opt( 'uptiheen_check_uploads', 'UPTIHEEN_CHECK_UPLOADS', true ),
			'disk'     => $this->opt( 'uptiheen_check_disk', 'UPTIHEEN_CHECK_DISK', true ),
			'cache'    => $this->opt( 'uptiheen_check_cache', 'UPTIHEEN_CHECK_CACHE', false ),
			'http'     => $this->opt( 'uptiheen_check_http', 'UPTIHEEN_CHECK_HTTP', false ),
			'homepage' => $this->opt( 'uptiheen_homepage', 'UPTIHEEN_HOMEPAGE', false ),
		];
	}

	/**
	 * Returns the constant value when defined, otherwise the DB option.
	 * Allows per-environment overrides (Dockerfile, wp-config.php) without
	 * touching the admin panel.
	 *
	 * @param string $option   Option name.
	 * @param string $constant Constant name.
	 * @param mixed  $fallback Default value when neither is set.
	 * @return mixed
	 */
	private function opt( string $option, string $constant, $fallback ) {
		return defined( $constant ) ? constant( $constant ) : get_option( $option, $fallback );
	}

	// ── Checks ──────────────────────────────────────────────────────────────

	/** @return bool */
	public function check_database(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return '1' === $wpdb->get_var( 'SELECT 1' );
	}

	public function check_options(): bool {
		return ! empty( get_option( 'siteurl' ) );
	}

	public function check_theme(): bool {
		return wp_get_theme()->exists();
	}

	public function check_cron(): bool {
		// Cache for 1 minute — deserializing the full cron array on every poll
		// is wasteful on sites with many scheduled events.
		$cached = get_transient( 'uptiheen_cron_probe' );
		if ( false !== $cached ) {
			return 'ok' === $cached;
		}

		$ok = $this->evaluate_cron();
		set_transient( 'uptiheen_cron_probe', $ok ? 'ok' : 'fail', MINUTE_IN_SECONDS );
		return $ok;
	}

	private function evaluate_cron(): bool {
		$threshold = time() - 10 * MINUTE_IN_SECONDS;

		// wp_get_scheduled_events() is the public API introduced in WP 6.1.
		if ( function_exists( 'wp_get_scheduled_events' ) ) {
			foreach ( wp_get_scheduled_events() as $hook_events ) {
				foreach ( $hook_events as $event ) {
					if ( isset( $event->timestamp ) && $event->timestamp < $threshold ) {
						return false;
					}
				}
			}
			return true;
		}

		// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- no public alternative before WP 6.1
		foreach ( array_keys( (array) _get_cron_array() ) as $timestamp ) {
			if ( $timestamp < $threshold ) {
				return false;
			}
		}
		return true;
	}

	public function check_memory(): bool {
		$limit = $this->parse_bytes( WP_MEMORY_LIMIT );
		if ( $limit <= 0 ) {
			return true; // Unlimited or unreadable — avoid false-positives
		}
		return ( memory_get_usage( true ) / $limit ) < 0.90;
	}

	public function check_uploads(): bool {
		$dir = wp_upload_dir();
		if ( ! empty( $dir['error'] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		// We are checking readability for a health probe, not writing files.
		// Loading WP_Filesystem (an admin-only API) on every REST poll adds
		// ~100 ms and requires an unnecessary admin include on a REST request.
		return is_writable( $dir['basedir'] );
	}

	public function check_disk(): bool {
		$threshold_mb = (int) $this->opt( 'uptiheen_disk_threshold', 'UPTIHEEN_DISK_THRESHOLD', 500 );
		$free		  = disk_free_space( WP_CONTENT_DIR );
		if ( false === $free ) {
			return true; // Can't determine — avoid false-positives
		}
		return $free >= ( $threshold_mb * MB_IN_BYTES );
	}

	public function check_object_cache(): bool {
		$key   = 'uptiheen_probe_' . uniqid( '', true );
		$value = microtime( true );
		wp_cache_set( $key, $value, '', 30 );
		$fetched = wp_cache_get( $key );
		wp_cache_delete( $key );
		return $fetched === $value;
	}

	public function check_required_plugins(): bool {
		$monitored = (array) get_option( 'uptiheen_monitored_plugins', [] );
		if ( empty( $monitored ) ) {
			return true;
		}
		$active = (array) get_option( 'active_plugins', [] );
		foreach ( $monitored as $plugin_file ) {
			if ( ! in_array( $plugin_file, $active, true ) ) {
				return false;
			}
		}
		return true;
	}

	public function check_http_outbound(): bool {
		// Cache the result for 5 min so a slow DNS/TLS handshake doesn't add
		// latency to every monitoring poll (tools typically poll every 1–5 min)
		$cached = get_transient( 'uptiheen_http_probe' );
		if ( false !== $cached ) {
			return 'ok' === $cached;
		}
		$response = wp_remote_get( 'https://api.wordpress.org/core/version-check/1.7/', [
			'timeout'	=> 5,
			'sslverify' => true,
		] );
		$ok = ! is_wp_error( $response )
			&& wp_remote_retrieve_response_code( $response ) === 200;
		set_transient( 'uptiheen_http_probe', $ok ? 'ok' : 'fail', 5 * MINUTE_IN_SECONDS );
		return $ok;
	}

	public function check_homepage(): bool {
		$response = wp_remote_get( home_url( '/' ), [
			'timeout'     => 5,
			'sslverify'   => false,
			'redirection' => 3,
			'user-agent'  => 'Uptime-Health-Endpoint/2.0',
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return false;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return false;
		}
		$php_error_patterns = [
			'Fatal error',
			'Parse error',
			'Warning:',
			'Notice:',
			'Deprecated:',
			'Uncaught Error',
			'Uncaught Exception',
			'critical error',
			'There has been a critical error',
		];
		foreach ( $php_error_patterns as $pattern ) {
			if ( false !== stripos( $body, $pattern ) ) {
				return false;
			}
		}
		return false !== stripos( $body, '</html>' ) || false !== stripos( $body, '</body>' );
	}

	private function parse_bytes( string $value ): int {
		$unit  = strtoupper( substr( trim( $value ), -1 ) );
		$bytes = (int) $value;
		switch ( $unit ) {
			case 'G':
				return $bytes * GB_IN_BYTES;
			case 'M':
				return $bytes * MB_IN_BYTES;
			case 'K':
				return $bytes * KB_IN_BYTES;
		}
		return $bytes;
	}
}
