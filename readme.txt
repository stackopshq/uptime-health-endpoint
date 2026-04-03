=== Uptime Health Endpoint ===
Contributors: kallioli
Donate link: https://github.com/sponsors/kallioli
Tags: uptime, monitoring, healthcheck, uptime-kuma, uptimerobot
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides a secure REST API endpoint for uptime monitoring tools like Uptime Kuma, UptimeRobot, Pingdom, and more.

== Description ==

Uptime Health Endpoint provides a dedicated healthcheck endpoint for your WordPress site that goes beyond simple HTTP checks. It verifies that your site is actually functional, not just responding.

**Endpoint:** `/wp-json/wp-uptime/v1/check`

= What it checks =

**Core checks (always active):**

* **Database connectivity** — Verifies MySQL/MariaDB is responding
* **WordPress options** — Confirms the options table is readable
* **Active theme** — Ensures the active theme exists and loads

**Optional checks (individually enabled in settings):**

* **WP-Cron health** — Flags if any scheduled event is more than 10 minutes overdue (default: on)
* **PHP memory usage** — Fails when more than 90% of WP_MEMORY_LIMIT is consumed (default: on)
* **Available disk space** — Alerts when free space on wp-content/ drops below a configurable threshold (default: on, 500 MB)
* **Uploads directory writable** — Checks that wp-content/uploads is writable (default: on)
* **Object cache roundtrip** — Verifies the persistent cache (Redis, Memcached) responds correctly (default: off)
* **Outbound HTTP connectivity** — Verifies the server can reach the internet (default: off)
* **Homepage rendering** — Makes a loopback request and scans for PHP errors (default: off)

= Security features =

* **Multiple tokens** — Use one token per monitoring service; revoke individually
* **IP allowlist** — Trusted IPs (with IPv4 CIDR support) bypass token validation
* **Rate limiting** — 10 failed attempts per minute per IP, resets on success
* **Timing-safe comparison** — `hash_equals()` prevents timing attacks
* **Security response headers** — `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`, `X-Robots-Tag: noindex`
* **No data leakage** — Only returns `ok`, `fail`, or `forbidden`

= Additional features =

* **Check history** — Last 100 results visible in the admin panel
* **Webhook notifications** — Non-blocking POST when status changes (ok ↔ fail)
* **WP-CLI** — `wp uptime check` and `wp uptime history`
* **WordPress Site Health** — Checks appear in Tools > Site Health
* **Custom checks** — Register additional checks via the `uptiheen_checks` filter
* **Debug mode** — `UPTIHEEN_DEBUG` constant adds failed check names and durations to the response

= Compatible with =

* Uptime Kuma
* UptimeRobot
* Pingdom
* Hetrix Tools
* StatusCake
* Any HTTP monitoring tool supporting custom headers or query parameters

= Configuration =

Configure everything from **Settings > Uptime Health Endpoint**, or use constants in `wp-config.php` for automated deployments (Docker, CI/CD):

    define( 'UPTIHEEN_TOKEN',  'your-secret-token' );   // single token (backward compat)
    define( 'UPTIHEEN_TOKENS', [ 'token-a', 'token-b' ] ); // multiple tokens
    define( 'UPTIHEEN_HOMEPAGE', true );                // enable homepage check

== Installation ==

1. Upload the `uptime-health-endpoint` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. You will be redirected to **Settings > Uptime Health Endpoint** — a token is generated automatically on first activation
4. Copy the token and configure your monitoring tool using the provided setup guides

= Alternative: wp-config.php =

For automated deployments, define constants before the plugin loads:

    define( 'UPTIHEEN_TOKEN', 'your-secret-token-here' );
    define( 'UPTIHEEN_HOMEPAGE', true ); // Optional: enable homepage check

== External Services ==

When the **Outbound HTTP connectivity** check is enabled, this plugin makes a request to:

* **WordPress.org version check API** — `https://api.wordpress.org/core/version-check/1.7/`
  * **Purpose:** Verify the server can make outbound HTTPS connections
  * **When:** Only when this optional check is explicitly enabled by an administrator in Settings > Uptime Health Endpoint; **disabled by default**
  * **Data sent:** Standard HTTP headers (User-Agent, Accept); no site data or personal information
  * **WordPress.org Privacy Policy:** https://wordpress.org/about/privacy/

No other external connections are made. All check logic runs entirely within your WordPress installation.

== Frequently Asked Questions ==

= Is a token generated automatically? =

Yes. A secure random token (64-character hex string) is generated automatically the first time you activate the plugin. You will be redirected to the settings page where you can copy it immediately.

= How do I add multiple tokens? =

Enter one token per line in the **Token(s)** textarea in Settings > Uptime Health Endpoint, or click **Generate New Token** to append a new one. Use a separate token for each monitoring service so you can revoke them independently.

= What's the difference between header and query parameter authentication? =

**Header** (`X-Healthcheck-Token: your-token`): Recommended. The token does not appear in server access logs or browser history.

**Query parameter** (`?token=your-token`): Works everywhere but the token may appear in access logs. Required for UptimeRobot free plan.

= What does the IP allowlist do? =

IPs listed in the allowlist (exact IPv4 addresses or CIDR ranges like `10.0.0.0/8`) bypass token validation entirely. Useful when your monitoring service has a fixed IP range. Token auth is still used for all other clients.

= How do I use the webhook notification? =

Enter a URL in the **Webhook URL** field. The plugin will POST a JSON payload to that URL whenever the overall status changes between `ok` and `fail`. The request is non-blocking and does not affect the endpoint's response time.

Payload format:

    {
      "event": "status_change",
      "previous": "ok",
      "current": "fail",
      "errors": ["db"],
      "site_url": "https://example.com",
      "timestamp": 1700000000
    }

= How do I use WP-CLI? =

    wp uptime check                    # Run all checks; exits with code 1 on failure
    wp uptime check --format=json      # JSON output
    wp uptime history                  # Show last 20 check results
    wp uptime history --limit=50       # Show last 50 results
    wp uptime history --format=json    # JSON output

= Why does the homepage check fail? =

The homepage check makes a loopback HTTP request. This can fail on some hosting environments due to firewall rules blocking server-to-self connections, DNS resolution issues, or reverse proxy configurations. If it fails but your site works fine publicly, disable this check.

= Is this compatible with multisite? =

Yes. Each site in a multisite network has its own independent endpoint and token configuration.

= What happens if the database is down? =

The endpoint returns HTTP 503 with `{"status":"fail"}`. Your monitoring tool will detect this as downtime.

= I get a 400 error with ModSecurity (Infomaniak, OVH, etc.) =

Some hosting providers use ModSecurity which may block GET requests without a body (rule 960011).

**Solution for Uptime Kuma:** Do not use the "Keyword" or "Body" match option. Only check the HTTP status code (`200` = UP, `503` = DOWN, `403` = invalid token).

**Alternative:** Contact your hosting provider to whitelist rule 960011 for the endpoint URL.

= How do I exclude the endpoint from server access logs? =

**Nginx** (add to your server block):

    location = /wp-json/wp-uptime/v1/check {
        access_log off;
        try_files $uri $uri/ /index.php?$args;
    }

**Apache** (add to your vhost or .htaccess):

    SetEnvIf Request_URI "^/wp-json/wp-uptime/v1/check" dontlog
    CustomLog /var/log/apache2/access.log combined env=!dontlog

= How do I register custom checks? =

Add this to your theme's `functions.php` or a custom plugin:

    add_filter( 'uptiheen_checks', function( array $checks ): array {
        $checks['my_service'] = function(): bool {
            return my_service_ping() === true;
        };
        return $checks;
    } );

The callback must return `true` (pass) or `false` (fail). The check key appears in debug responses and the admin history table.

== Screenshots ==

1. Admin settings page — authentication and check configuration
2. Check history table showing recent results and durations
3. Monitoring tool setup guides (Uptime Kuma, UptimeRobot, others)

== Changelog ==

= 2.0.0 =
* **New:** 6 additional health checks — WP-Cron queue, PHP memory usage, disk space, uploads directory writable, object cache roundtrip, outbound HTTP connectivity
* **New:** Multiple tokens support — one per monitoring service, individually revocable
* **New:** IP allowlist with IPv4 CIDR notation support
* **New:** Check history — last 100 results in the admin panel
* **New:** Webhook notifications — non-blocking POST on status change (ok ↔ fail)
* **New:** WP-CLI integration — `wp uptime check`, `wp uptime history`
* **New:** WordPress Site Health integration — checks appear in Tools > Site Health
* **New:** `uptiheen_checks` filter for custom third-party checks
* **New:** Security response headers on every response — `Cache-Control: no-store`, `X-Content-Type-Options`, `X-Robots-Tag`
* **New:** `UPTIHEEN_DEBUG` constant to expose failed check keys and per-check durations in responses
* **New:** Automatic token generation on first activation + redirect to settings page
* **New:** `uninstall.php` — complete cleanup of all options and transients on plugin deletion
* **Improved:** Object-oriented rewrite with namespace `UptimeHealthEndpoint`
* **Improved:** Admin code loads only on admin requests; REST code only on REST requests; nothing on frontend
* **Improved:** Health checks run cheapest-first; short-circuit on first failure when not in debug mode
* **Improved:** Rate-limit counter now resets on successful authentication
* **Improved:** `X-Forwarded-For` and `X-Real-IP` only trusted when `REMOTE_ADDR` is a private IP (reverse proxy)
* **Improved:** Full internationalisation (i18n) — all UI strings translatable
* **Fixed:** "Generate New Token" button now correctly generates and saves a new token (was broken in 1.x)
* **Fixed:** Token not URL-encoded in admin configuration tables
* **Fixed:** Homepage check now flags 4xx responses (was only catching 5xx)

= 1.0.0 =
* Initial release
* Database connectivity check
* WordPress options check
* Theme existence check
* Optional homepage rendering check
* Token authentication (header + query param)
* Rate limiting (10 attempts/minute per IP)
* Admin configuration page with monitoring tool guides

== Upgrade Notice ==

= 2.0.0 =
Major rewrite adding 6 new health checks, multi-token support, webhook notifications, WP-CLI, check history, and full i18n. Fully backward compatible — your existing single-token configuration continues to work without any changes.

= 1.0.0 =
Initial release.
