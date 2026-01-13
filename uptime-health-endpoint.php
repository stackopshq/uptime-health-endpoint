<?php
/**
 * Plugin Name:       Uptime Health Endpoint
 * Description:       Provides a /wp-json/wp-uptime/v1/check endpoint for uptime monitoring (Uptime Kuma, UptimeRobot, etc.).
 * Version:           1.0.0
 * Author:            Kevin Allioli
 * Author URI:        https://github.com/kallioli
 * Plugin URI:        https://github.com/kallioli/uptime-health-endpoint
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       uptime-health-endpoint
 * Requires at least: 5.0
 * Requires PHP:      7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('wp-uptime/v1', '/check', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => 'uptiheen_handle_request',
    ]);
});

// Admin menu
add_action('admin_menu', 'uptiheen_admin_menu');
add_action('admin_init', 'uptiheen_admin_init');

/**
 * Add admin menu page.
 */
function uptiheen_admin_menu() {
    add_options_page(
        'Uptime Health Endpoint',
        'Uptime Health Endpoint',
        'manage_options',
        'wp-uptime-endpoint',
        'uptiheen_admin_page'
    );
}

/**
 * Register settings.
 */
function uptiheen_admin_init() {
    register_setting('uptiheen_settings', 'uptiheen_token', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
    register_setting('uptiheen_settings', 'uptiheen_homepage', [
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => false,
    ]);
}

/**
 * Render admin page.
 */
function uptiheen_admin_page() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        return;
    }

    // Generate token if requested
    if (isset($_POST['uptiheen_generate_token']) && check_admin_referer('uptiheen_settings-options')) {
        $new_token = bin2hex(random_bytes(32));
        update_option('uptiheen_token', $new_token);
    }

    // Get current values (constants take priority)
    $token_from_constant = defined('UPTIHEEN_TOKEN');
    $homepage_from_constant = defined('UPTIHEEN_HOMEPAGE');
    
    $current_token = $token_from_constant ? UPTIHEEN_TOKEN : get_option('uptiheen_token', '');
    $current_homepage = $homepage_from_constant ? UPTIHEEN_HOMEPAGE : get_option('uptiheen_homepage', false);
    
    $endpoint_url = rest_url('wp-uptime/v1/check');
    ?>
    <div class="wrap">
        <h1>Uptime Health Endpoint</h1>
        
        <h2>Endpoint</h2>
        <p><code><?php echo esc_html($endpoint_url); ?></code></p>
        
        <hr>
        
        <form method="post" action="options.php">
            <?php settings_fields('uptiheen_settings'); ?>
            
            <h2>Configuration</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="uptiheen_token">Token</label></th>
                    <td>
                        <?php if ($token_from_constant): ?>
                            <input type="password" value="<?php echo esc_attr($current_token); ?>" class="regular-text" readonly disabled>
                            <p class="description">Defined via <code>UPTIHEEN_TOKEN</code> in wp-config.php</p>
                        <?php else: ?>
                            <input type="password" name="uptiheen_token" id="uptiheen_token" 
                                   value="<?php echo esc_attr($current_token); ?>" class="regular-text" autocomplete="off">
                            <p class="description">Token required to access the endpoint.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Generate Token</th>
                    <td>
                        <?php if ($token_from_constant): ?>
                            <button type="button" class="button" disabled>Generate New Token</button>
                            <p class="description">Disabled because the token is defined in wp-config.php</p>
                        <?php else: ?>
                            <button type="submit" name="uptiheen_generate_token" class="button">Generate New Token</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Check Homepage</th>
                    <td>
                        <?php if ($homepage_from_constant): ?>
                            <input type="checkbox" <?php checked($current_homepage); ?> disabled>
                            <span>Enabled</span>
                            <p class="description">Defined via <code>UPTIHEEN_HOMEPAGE</code> in wp-config.php</p>
                        <?php else: ?>
                            <label>
                                <input type="checkbox" name="uptiheen_homepage" value="1" <?php checked($current_homepage); ?>>
                                Enable homepage verification (HTTP loopback)
                            </label>
                            <p class="description">Checks that the homepage displays without PHP errors. May fail on some hosting environments.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <?php if (!$token_from_constant || !$homepage_from_constant): ?>
                <?php submit_button('Save Changes'); ?>
            <?php endif; ?>
        </form>
        
        <hr>
        
        <h2>Test</h2>
        <p>
            <a href="<?php echo esc_url($endpoint_url . '?token=' . urlencode($current_token)); ?>" 
               target="_blank" class="button">Test Endpoint</a>
        </p>
        
        <hr>
        
        <h2>Configuration Uptime Kuma</h2>
        <table class="widefat" style="max-width: 700px;">
            <tbody>
                <tr>
                    <td style="width: 150px;"><strong>Monitor Type</strong></td>
                    <td><code>HTTP(s)</code></td>
                </tr>
                <tr>
                    <td><strong>URL</strong></td>
                    <td><code><?php echo esc_html($endpoint_url); ?></code></td>
                </tr>
                <tr>
                    <td><strong>Method</strong></td>
                    <td><code>GET</code></td>
                </tr>
                <tr>
                    <td><strong>Headers</strong></td>
                    <td><code>X-Healthcheck-Token: <?php echo esc_html($current_token); ?></code></td>
                </tr>
                <tr>
                    <td><strong>Expected Status</strong></td>
                    <td><code>200</code></td>
                </tr>
                <tr>
                    <td><strong>Keyword (optional)</strong></td>
                    <td><code>"status":"ok"</code></td>
                </tr>
            </tbody>
        </table>
        
        <hr>
        
        <h2>Configuration UptimeRobot</h2>
        <p><em>Note: The free plan does not support custom headers. Use the token as a query parameter.<br>
        Pro plans and above support headers — in that case, use the same configuration as Uptime Kuma.</em></p>
        <table class="widefat" style="max-width: 700px;">
            <tbody>
                <tr>
                    <td style="width: 150px;"><strong>Monitor Type</strong></td>
                    <td><code>HTTP(s)</code></td>
                </tr>
                <tr>
                    <td><strong>URL</strong></td>
                    <td><code><?php echo esc_html($endpoint_url . '?token=' . $current_token); ?></code></td>
                </tr>
                <tr>
                    <td><strong>Monitoring Interval</strong></td>
                    <td><code>5 minutes</code> (or according to your plan)</td>
                </tr>
                <tr>
                    <td><strong>Alert Contact</strong></td>
                    <td>Select your alert contacts</td>
                </tr>
            </tbody>
        </table>
        
        <hr>
        
        <h2>Configuration for Other Tools (Pingdom, Hetrix, etc.)</h2>
        <table class="widefat" style="max-width: 700px;">
            <tbody>
                <tr>
                    <td style="width: 150px;"><strong>URL (with header)</strong></td>
                    <td><code><?php echo esc_html($endpoint_url); ?></code><br>
                        <small>Header: <code>X-Healthcheck-Token: <?php echo esc_html($current_token); ?></code></small>
                    </td>
                </tr>
                <tr>
                    <td><strong>URL (with query)</strong></td>
                    <td><code><?php echo esc_html($endpoint_url . '?token=' . $current_token); ?></code></td>
                </tr>
                <tr>
                    <td><strong>Expected Response</strong></td>
                    <td>Status <code>200</code> + Body contains <code>{"status":"ok"}</code></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Handle healthcheck request.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function uptiheen_handle_request(WP_REST_Request $request) {
    // Token validation (required if UPTIHEEN_TOKEN is defined)
    if (!uptiheen_validate_token($request)) {
        return new WP_REST_Response(['status' => 'forbidden'], 403);
    }

    // Run health checks
    $checks = uptiheen_run_checks();

    if (!empty($checks['errors'])) {
        return new WP_REST_Response(['status' => 'fail'], 503);
    }

    return new WP_REST_Response(['status' => 'ok'], 200);
}

/**
 * Validate the healthcheck token.
 * Checks header X-Healthcheck-Token first, then query param ?token=
 *
 * @param WP_REST_Request $request
 * @return bool
 */
function uptiheen_validate_token(WP_REST_Request $request) {
    // Rate limiting: max 10 failed attempts per minute per IP
    $ip = uptiheen_get_client_ip();
    $transient_key = 'uptiheen_rate_' . md5($ip);
    $attempts = (int) get_transient($transient_key);
    
    if ($attempts >= 10) {
        return false; // Rate limited
    }
    
    // Get token: constant takes priority, then database option
    $expected = defined('UPTIHEEN_TOKEN') ? UPTIHEEN_TOKEN : get_option('uptiheen_token', '');
    
    // If no token is configured, deny access (token is mandatory)
    if (empty($expected)) {
        return false;
    }

    // Try header first
    $token = $request->get_header('X-Healthcheck-Token');

    // Fallback to query param
    if (empty($token)) {
        $token = $request->get_param('token');
    }

    if (empty($token)) {
        return false;
    }

    $valid = hash_equals($expected, (string) $token);
    
    // Increment failed attempts counter
    if (!$valid) {
        set_transient($transient_key, $attempts + 1, 60); // 60 seconds TTL
    }
    
    return $valid;
}

/**
 * Get client IP address.
 *
 * @return string
 */
function uptiheen_get_client_ip() {
    $ip = '';
    
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REAL_IP']));
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    }
    
    return $ip;
}

/**
 * Run all health checks.
 *
 * @return array ['errors' => [...]]
 */
function uptiheen_run_checks() {
    global $wpdb;

    $errors = [];

    // Check 1: Database connectivity
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $db_result = $wpdb->get_var('SELECT 1');
    if ((string) $db_result !== '1') {
        $errors[] = 'db';
    }

    // Check 2: WordPress core - can read options
    $siteurl = get_option('siteurl');
    if (empty($siteurl)) {
        $errors[] = 'wp_options';
    }

    // Check 3: WordPress core - theme loaded
    $theme = wp_get_theme();
    if (!$theme->exists()) {
        $errors[] = 'theme';
    }

    // Check 4 (optional): Homepage renders without errors
    $check_homepage = defined('UPTIHEEN_HOMEPAGE') ? UPTIHEEN_HOMEPAGE : get_option('uptiheen_homepage', false);
    if ($check_homepage) {
        $homepage_check = uptiheen_check_homepage();
        if ($homepage_check !== true) {
            $errors[] = 'homepage';
        }
    }

    return ['errors' => $errors];
}

/**
 * Check if homepage renders correctly without PHP errors.
 * Makes a loopback HTTP request to the homepage.
 *
 * @return bool|string True if OK, error message otherwise
 */
function uptiheen_check_homepage() {
    $url = home_url('/');

    $response = wp_remote_get($url, [
        'timeout'     => 15,
        'sslverify'   => false,
        'redirection' => 3,
        'user-agent'  => 'WP-Healthcheck/1.0',
    ]);

    // Request failed entirely
    if (is_wp_error($response)) {
        return 'request_failed';
    }

    // Check HTTP status
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code >= 500) {
        return 'http_error';
    }

    // Get body
    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return 'empty_body';
    }

    // Check for PHP error patterns
    $error_patterns = [
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

    foreach ($error_patterns as $pattern) {
        if (stripos($body, $pattern) !== false) {
            return 'php_error';
        }
    }

    // Check for minimal HTML structure
    if (stripos($body, '</html>') === false && stripos($body, '</body>') === false) {
        return 'invalid_html';
    }

    return true;
}
