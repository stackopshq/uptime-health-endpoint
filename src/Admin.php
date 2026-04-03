<?php

namespace UptimeHealthEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings page.
 * Instantiated only when is_admin() — never loaded on REST or frontend requests.
 */
final class Admin {

	public function register_menu(): void {
		add_options_page(
			__( 'Uptime Health Endpoint', 'uptime-health-endpoint' ),
			__( 'Uptime Health Endpoint', 'uptime-health-endpoint' ),
			'manage_options',
			'wp-uptime-endpoint',
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		$defs = [
			'uptiheen_tokens'         => [
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_tokens' ],
				'default'           => '',
			],
			'uptiheen_ip_allowlist'   => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			],
			'uptiheen_webhook_url'    => [
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			],
			'uptiheen_check_cron'     => [
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			],
			'uptiheen_check_memory'   => [
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			],
			'uptiheen_check_disk'     => [
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			],
			'uptiheen_disk_threshold' => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 500,
			],
			'uptiheen_check_uploads'  => [
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			],
			'uptiheen_check_http'     => [
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			],
			'uptiheen_check_cache'    => [
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			],
			'uptiheen_homepage'       => [
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			],
		];
		foreach ( $defs as $key => $args ) {
			register_setting( 'uptiheen_settings', $key, $args );
		}
	}

	public function sanitize_tokens( string $value ): string {
		$lines = array_filter( array_map( 'sanitize_text_field', explode( "\n", $value ) ) );
		return implode( "\n", $lines );
	}

	public function generate_token(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'uptime-health-endpoint' ) );
		}
		check_admin_referer( 'uptiheen_generate_token' );

		$existing  = trim( (string) get_option( 'uptiheen_tokens', '' ) );
		$new_token = bin2hex( random_bytes( 32 ) );
		$updated   = '' !== $existing ? $existing . "\n" . $new_token : $new_token;
		update_option( 'uptiheen_tokens', $updated );

		wp_safe_redirect( admin_url( 'options-general.php?page=wp-uptime-endpoint&token_generated=1' ) );
		exit;
	}

	public function clear_history(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'uptime-health-endpoint' ) );
		}
		check_admin_referer( 'uptiheen_clear_history' );
		( new History() )->clear();
		wp_safe_redirect( admin_url( 'options-general.php?page=wp-uptime-endpoint&history_cleared=1' ) );
		exit;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$token_const	= defined( 'UPTIHEEN_TOKEN' ) || ( defined( 'UPTIHEEN_TOKENS' ) && is_array( UPTIHEEN_TOKENS ) );
		$homepage_const = defined( 'UPTIHEEN_HOMEPAGE' );

		$tokens_raw = $token_const
			? ( defined( 'UPTIHEEN_TOKENS' ) ? implode( "\n", UPTIHEEN_TOKENS ) : UPTIHEEN_TOKEN )
			: ( '' !== trim( (string) get_option( 'uptiheen_tokens', '' ) ) ? trim( (string) get_option( 'uptiheen_tokens', '' ) ) : (string) get_option( 'uptiheen_token', '' ) );

		$allowlist	 = (string) get_option( 'uptiheen_ip_allowlist', '' );
		$webhook	 = (string) get_option( 'uptiheen_webhook_url', '' );
		$endpoint	 = rest_url( 'wp-uptime/v1/check' );
		$first_token = explode( "\n", trim( $tokens_raw ) )[0] ?? '';

		$o = [
			'homepage' => $homepage_const ? (bool) UPTIHEEN_HOMEPAGE : (bool) get_option( 'uptiheen_homepage', false ),
			'cron'	   => (bool) get_option( 'uptiheen_check_cron', true ),
			'memory'   => (bool) get_option( 'uptiheen_check_memory', true ),
			'disk'	   => (bool) get_option( 'uptiheen_check_disk', true ),
			'disk_mb'  => (int) get_option( 'uptiheen_disk_threshold', 500 ),
			'uploads'  => (bool) get_option( 'uptiheen_check_uploads', true ),
			'http'	   => (bool) get_option( 'uptiheen_check_http', false ),
			'cache'	   => (bool) get_option( 'uptiheen_check_cache', false ),
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Uptime Health Endpoint', 'uptime-health-endpoint' ); ?></h1>

			<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( isset( $_GET['activated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Plugin activated! A token has been generated automatically — copy it below to configure your monitoring service.', 'uptime-health-endpoint' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( isset( $_GET['token_generated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'New token added.', 'uptime-health-endpoint' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( isset( $_GET['history_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'History cleared.', 'uptime-health-endpoint' ); ?></p>
				</div>
			<?php endif; ?>
			<?php // phpcs:enable ?>

			<h2><?php esc_html_e( 'Endpoint', 'uptime-health-endpoint' ); ?></h2>
			<p><code><?php echo esc_html( $endpoint ); ?></code></p>

			<hr>

			<?php /* Generate-token form — outside the settings form (HTML forbids nested <form>). */ ?>
			<?php /* The button below references it via the HTML5 `form` attribute.				  */ ?>
			<?php if ( ! $token_const ) : ?>
			<form id="uptiheen-generate-form" method="post"
				  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
				<input type="hidden" name="action" value="uptiheen_generate_token">
				<?php wp_nonce_field( 'uptiheen_generate_token' ); ?>
			</form>
			<?php endif; ?>

			<form id="uptiheen-clear-history-form" method="post"
				  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
				<input type="hidden" name="action" value="uptiheen_clear_history">
				<?php wp_nonce_field( 'uptiheen_clear_history' ); ?>
			</form>

			<form method="post" action="options.php">
				<?php settings_fields( 'uptiheen_settings' ); ?>

				<h2><?php esc_html_e( 'Authentication', 'uptime-health-endpoint' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Token(s)', 'uptime-health-endpoint' ); ?></th>
						<td>
							<?php if ( $token_const ) : ?>
								<textarea class="large-text code" rows="4" readonly disabled><?php echo esc_textarea( $tokens_raw ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Defined via', 'uptime-health-endpoint' ); ?>
									<code>UPTIHEEN_TOKEN</code> <?php esc_html_e( 'or', 'uptime-health-endpoint' ); ?>
									<code>UPTIHEEN_TOKENS</code>
									<?php esc_html_e( 'in wp-config.php.', 'uptime-health-endpoint' ); ?>
								</p>
							<?php else : ?>
								<textarea name="uptiheen_tokens" class="large-text code" rows="4"
										  autocomplete="off" spellcheck="false"><?php echo esc_textarea( $tokens_raw ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One token per line. Use a separate token per monitoring service so you can revoke them individually.', 'uptime-health-endpoint' ); ?></p>
								<p>
									<button type="submit" form="uptiheen-generate-form" class="button">
										<?php esc_html_e( 'Generate New Token', 'uptime-health-endpoint' ); ?>
									</button>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'IP Allowlist', 'uptime-health-endpoint' ); ?></th>
						<td>
							<textarea name="uptiheen_ip_allowlist" class="large-text code" rows="4"
									  spellcheck="false"><?php echo esc_textarea( $allowlist ); ?></textarea>
							<p class="description">
								<?php
								printf(
									/* translators: 1: example IP, 2: example CIDR */
									esc_html__( 'One IP or CIDR per line (e.g. %1$s or %2$s). IPs in this list bypass token validation entirely. Leave empty to require a token from all clients.', 'uptime-health-endpoint' ),
									'<code>192.168.1.1</code>',
									'<code>10.0.0.0/8</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Health Checks', 'uptime-health-endpoint' ); ?></h2>
				<p><?php esc_html_e( 'Core checks (database, WordPress options, active theme) always run and cannot be disabled.', 'uptime-health-endpoint' ); ?></p>
				<table class="form-table">
					<?php
					$toggles = [
						'uptiheen_check_cron'	 => [
							$o['cron'],
							__( 'WP-Cron health', 'uptime-health-endpoint' ),
							__( 'Flags if any scheduled event is more than 10 minutes overdue.', 'uptime-health-endpoint' ),
						],
						'uptiheen_check_memory'	 => [
							$o['memory'],
							__( 'PHP memory usage', 'uptime-health-endpoint' ),
							__( 'Fails when more than 90% of WP_MEMORY_LIMIT is consumed.', 'uptime-health-endpoint' ),
						],
						'uptiheen_check_disk'	 => [
							$o['disk'],
							__( 'Available disk space', 'uptime-health-endpoint' ),
							'',
						],
						'uptiheen_check_uploads' => [
							$o['uploads'],
							__( 'Uploads directory writable', 'uptime-health-endpoint' ),
							__( 'Checks that wp-content/uploads is writable.', 'uptime-health-endpoint' ),
						],
						'uptiheen_check_cache'	 => [
							$o['cache'],
							__( 'Object cache roundtrip', 'uptime-health-endpoint' ),
							__( 'Recommended only when a persistent cache (Redis/Memcached) is configured.', 'uptime-health-endpoint' ),
						],
						'uptiheen_check_http'	 => [
							$o['http'],
							__( 'Outbound HTTP connectivity', 'uptime-health-endpoint' ),
							__( 'Verifies WordPress can reach the internet. Result is cached 5 minutes to avoid adding latency to every poll.', 'uptime-health-endpoint' ),
						],
						'uptiheen_homepage'		 => [
							$o['homepage'],
							__( 'Homepage rendering (loopback)', 'uptime-health-endpoint' ),
							__( 'Makes an HTTP request to the homepage and scans for PHP errors. May fail on hosts that block loopback requests.', 'uptime-health-endpoint' ),
						],
					];
					foreach ( $toggles as $name => [ $checked, $label, $desc ] ) :
						$is_const = defined( strtoupper( $name ) );
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1"
									   <?php checked( $checked ); ?> <?php disabled( $is_const ); ?>>
								<?php esc_html_e( 'Enable', 'uptime-health-endpoint' ); ?>
							</label>
							<?php if ( $desc ) : ?>
								<p class="description"><?php echo esc_html( $desc ); ?></p>
							<?php endif; ?>
							<?php if ( $is_const ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: %s: constant name */
										esc_html__( 'Defined via %s in wp-config.php.', 'uptime-health-endpoint' ),
										'<code>' . esc_html( strtoupper( $name ) ) . '</code>'
									);
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Disk space threshold', 'uptime-health-endpoint' ); ?></th>
						<td>
							<input type="number" name="uptiheen_disk_threshold"
								   value="<?php echo esc_attr( $o['disk_mb'] ); ?>"
								   min="1" step="1" class="small-text">
							<?php esc_html_e( 'MB', 'uptime-health-endpoint' ); ?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: directory path */
									esc_html__( 'Alert when free space on %s drops below this value.', 'uptime-health-endpoint' ),
									'<code>wp-content/</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Notifications', 'uptime-health-endpoint' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook URL', 'uptime-health-endpoint' ); ?></th>
						<td>
							<input type="url" name="uptiheen_webhook_url"
								   value="<?php echo esc_attr( $webhook ); ?>" class="large-text">
							<p class="description">
								<?php esc_html_e( 'Non-blocking POST (JSON) sent whenever the status flips between ok and fail.', 'uptime-health-endpoint' ); ?><br>
								<?php esc_html_e( 'Payload:', 'uptime-health-endpoint' ); ?>
								<code>{"event":"status_change","previous":"ok","current":"fail","errors":[...],"site_url":"...","timestamp":...}</code>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Changes', 'uptime-health-endpoint' ) ); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Test', 'uptime-health-endpoint' ); ?></h2>
			<p>
				<button type="button" class="button button-primary" id="uptiheen-test-btn"
						onclick="uptiheen_test()">
					<?php esc_html_e( 'Run Check', 'uptime-health-endpoint' ); ?>
				</button>
				<span id="uptiheen-test-result" style="margin-left:12px; font-weight:bold;"></span>
			</p>
			<script>
			function uptiheen_test() {
				var result = document.getElementById( 'uptiheen-test-result' );
				var btn	   = document.getElementById( 'uptiheen-test-btn' );
				result.textContent = <?php echo wp_json_encode( __( 'Running…', 'uptime-health-endpoint' ) ); ?>;
				result.style.color = '';
				btn.disabled = true;
				fetch( <?php echo wp_json_encode( $endpoint ); ?>, {
					headers: { 'X-Healthcheck-Token': <?php echo wp_json_encode( $first_token ); ?> }
				} )
				.then( function( r ) {
					return r.json().then( function( d ) { return { status: r.status, data: d }; } );
				} )
				.then( function( r ) {
					result.textContent = r.status + ' \u2014 ' + JSON.stringify( r.data );
					result.style.color = ( r.status === 200 ) ? 'green' : 'red';
				} )
				.catch( function( e ) {
					result.textContent = <?php echo wp_json_encode( __( 'Error:', 'uptime-health-endpoint' ) ); ?> + ' ' + e.message;
					result.style.color = 'red';
				} )
				.finally( function() { btn.disabled = false; } );
			}
			</script>

			<hr>

			<h2><?php esc_html_e( 'Recent Check History', 'uptime-health-endpoint' ); ?></h2>
			<?php
			$history = ( new History() )->get();
			if ( empty( $history ) ) :
			?>
				<p><?php esc_html_e( 'No checks recorded yet. History is populated automatically each time the endpoint is polled.', 'uptime-health-endpoint' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:700px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'uptime-health-endpoint' ); ?></th>
							<th><?php esc_html_e( 'Status', 'uptime-health-endpoint' ); ?></th>
							<th><?php esc_html_e( 'Failed checks', 'uptime-health-endpoint' ); ?></th>
							<th><?php esc_html_e( 'Duration', 'uptime-health-endpoint' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $history, 0, 20 ) as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( __( 'Y-m-d H:i:s', 'uptime-health-endpoint' ), $entry['timestamp'] ) ); ?></td>
							<td style="color:<?php echo 'ok' === $entry['status'] ? 'green' : 'red'; ?>; font-weight:bold;">
								<?php echo esc_html( strtoupper( $entry['status'] ) ); ?>
							</td>
							<td><?php echo $entry['errors'] ? esc_html( implode( ', ', $entry['errors'] ) ) : '&mdash;'; ?></td>
							<td>
								<?php
								printf(
									/* translators: %d: duration in milliseconds */
									esc_html__( '%d ms', 'uptime-health-endpoint' ),
									(int) $entry['total_ms']
								);
								?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<?php
					printf(
						/* translators: 1: entries shown, 2: total entries */
						esc_html__( 'Showing last %1$d of %2$d entries.', 'uptime-health-endpoint' ),
						(int) min( 20, count( $history ) ),
						count( $history )
					);
					?>
					&nbsp;
					<button type="submit" form="uptiheen-clear-history-form" class="button"
							onclick="return confirm( <?php echo wp_json_encode( __( 'Clear all history?', 'uptime-health-endpoint' ) ); ?> );">
						<?php esc_html_e( 'Clear History', 'uptime-health-endpoint' ); ?>
					</button>
				</p>
			<?php endif; ?>

			<hr>

			<h2><?php esc_html_e( 'Uptime Kuma', 'uptime-health-endpoint' ); ?></h2>
			<table class="widefat" style="max-width:700px;">
				<tbody>
					<tr>
						<td style="width:160px;"><strong><?php esc_html_e( 'Monitor Type', 'uptime-health-endpoint' ); ?></strong></td>
						<td><code>HTTP(s)</code></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'URL', 'uptime-health-endpoint' ); ?></strong></td>
						<td><code><?php echo esc_html( $endpoint ); ?></code></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Method', 'uptime-health-endpoint' ); ?></strong></td>
						<td><code>GET</code></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Headers', 'uptime-health-endpoint' ); ?></strong></td>
						<td><code>X-Healthcheck-Token: <?php echo esc_html( $first_token ); ?></code></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Expected Status', 'uptime-health-endpoint' ); ?></strong></td>
						<td><code>200</code></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Keyword (optional)', 'uptime-health-endpoint' ); ?></strong></td>
						<td><code>"status":"ok"</code></td>
					</tr>
				</tbody>
			</table>

			<h2 style="margin-top:1.5em;">UptimeRobot</h2>
			<p><em><?php esc_html_e( 'Free plan: headers not supported — use query param. Pro+: same configuration as Uptime Kuma.', 'uptime-health-endpoint' ); ?></em></p>
			<table class="widefat" style="max-width:700px;">
				<tbody>
					<tr>
						<td style="width:160px;"><strong><?php esc_html_e( 'Monitor Type', 'uptime-health-endpoint' ); ?></strong></td>
						<td><code>HTTP(s)</code></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'URL', 'uptime-health-endpoint' ); ?></strong></td>
						<td><code><?php echo esc_html( $endpoint . '?token=' . rawurlencode( $first_token ) ); ?></code></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Expected Status', 'uptime-health-endpoint' ); ?></strong></td>
						<td><code>200</code></td>
					</tr>
				</tbody>
			</table>

			<h2 style="margin-top:1.5em;"><?php esc_html_e( 'Other tools (Pingdom, Hetrix, etc.)', 'uptime-health-endpoint' ); ?></h2>
			<table class="widefat" style="max-width:700px;">
				<tbody>
					<tr>
						<td style="width:160px;"><strong><?php esc_html_e( 'URL (header auth)', 'uptime-health-endpoint' ); ?></strong></td>
						<td>
							<code><?php echo esc_html( $endpoint ); ?></code><br>
							<small><?php esc_html_e( 'Header:', 'uptime-health-endpoint' ); ?> <code>X-Healthcheck-Token: <?php echo esc_html( $first_token ); ?></code></small>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'URL (query auth)', 'uptime-health-endpoint' ); ?></strong></td>
						<td><code><?php echo esc_html( $endpoint . '?token=' . rawurlencode( $first_token ) ); ?></code></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Expected response', 'uptime-health-endpoint' ); ?></strong></td>
						<td><?php esc_html_e( 'HTTP', 'uptime-health-endpoint' ); ?> <code>200</code> &middot; <?php esc_html_e( 'body contains', 'uptime-health-endpoint' ); ?> <code>{"status":"ok"}</code></td>
					</tr>
				</tbody>
			</table>

			<hr>

			<h2><?php esc_html_e( 'Advanced', 'uptime-health-endpoint' ); ?></h2>

			<h3><?php esc_html_e( 'Debug mode', 'uptime-health-endpoint' ); ?></h3>
			<p><?php esc_html_e( 'Include failed check names and per-check durations in 503 responses (disable in production):', 'uptime-health-endpoint' ); ?></p>
			<pre style="background:#f6f7f7;padding:10px;display:inline-block;">define( 'UPTIHEEN_DEBUG', true );</pre>
			<p><?php esc_html_e( 'Response:', 'uptime-health-endpoint' ); ?> <code>{"status":"fail","checks":["db"],"durations":{"db":2,"wp_options":0}}</code></p>

			<h3>WP-CLI</h3>
			<pre style="background:#f6f7f7;padding:10px;display:inline-block;">wp uptime check				# <?php esc_html_e( 'Run all checks, exit 1 on failure', 'uptime-health-endpoint' ); ?>

wp uptime check --format=json
wp uptime history			 # <?php esc_html_e( 'Display recent check history', 'uptime-health-endpoint' ); ?>

wp uptime history --limit=50 --format=json</pre>

			<h3><?php esc_html_e( 'Custom checks (for developers)', 'uptime-health-endpoint' ); ?></h3>
			<p><?php esc_html_e( 'Register additional checks from a plugin or theme:', 'uptime-health-endpoint' ); ?></p>
			<pre style="background:#f6f7f7;padding:10px;display:inline-block;">add_filter( 'uptiheen_checks', function( array $checks ): array {
	$checks['my_service'] = function(): bool {
		return my_service_ping() === true;
	};
	return $checks;
} );</pre>

		</div>
		<?php
	}
}
