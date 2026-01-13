=== Uptime Health Endpoint ===
Contributors: kallioli
Donate link: https://github.com/sponsors/kallioli
Tags: uptime, monitoring, healthcheck, uptime-kuma, uptimerobot
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides a secure REST API endpoint for uptime monitoring tools like Uptime Kuma, UptimeRobot, Pingdom, and more.

== Description ==

Uptime Health Endpoint provides a dedicated healthcheck endpoint for your WordPress site that goes beyond simple HTTP checks. It verifies that your site is actually functional, not just responding.

**Endpoint:** `/wp-json/wp-uptime/v1/check`

= What it checks =

* **Database connectivity** - Verifies MySQL/MariaDB is responding
* **WordPress core** - Confirms options table is readable
* **Theme** - Ensures active theme exists and loads
* **Homepage rendering** (optional) - Checks for PHP errors on the frontend

= Security features =

* Token-based authentication (required)
* Rate limiting (10 attempts/minute per IP)
* Timing-safe token comparison
* No sensitive information exposed

= Compatible with =

* Uptime Kuma
* UptimeRobot
* Pingdom
* Hetrix Tools
* StatusCake
* Any HTTP monitoring tool

= Configuration =

Configure everything from the WordPress admin panel at **Settings > WP Uptime Endpoint**, or use constants in `wp-config.php` for automated deployments.

== Installation ==

1. Upload the `wp-uptime-endpoint` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings > WP Uptime Endpoint**
4. Generate a token or enter your own
5. Configure your monitoring tool using the provided settings

= Alternative: wp-config.php =

For automated deployments (Docker, CI/CD), you can define constants:

    define('UPTIHEEN_TOKEN', 'your-secret-token-here');
    define('UPTIHEEN_HOMEPAGE', true); // Optional: enable homepage check

== Frequently Asked Questions ==

= How do I generate a secure token? =

Use the "Generate New Token" button in the admin panel, or run:

    openssl rand -hex 32

= What's the difference between header and query param authentication? =

**Header** (`X-Healthcheck-Token`): More secure, token doesn't appear in server logs. Recommended for Uptime Kuma.

**Query param** (`?token=xxx`): Works everywhere but token may appear in access logs. Required for UptimeRobot free plan.

= Why does the homepage check fail? =

The homepage check makes a loopback HTTP request. This can fail on some hosting environments due to firewall rules, DNS issues, or reverse proxy configurations. If it fails but your site works fine, disable this check.

= Is this compatible with multisite? =

Yes, the plugin works on multisite installations. Each site has its own endpoint and token.

= What happens if the database is down? =

The endpoint returns HTTP 503 with `{"status":"fail"}`. Your monitoring tool will detect this as downtime.

= I get a 400 error with ModSecurity (Infomaniak, OVH, etc.) =

Some hosting providers use ModSecurity which blocks GET requests with a body or `Content-Length: 0` header (rule 960011).

**Solution for Uptime Kuma:** Do not use the "Keyword" or "Body" field. Only check the status code (200 = UP). The plugin already returns different status codes for each state:

* `200` = Site healthy
* `503` = Site unhealthy
* `403` = Invalid token

**Alternative:** Contact your hosting provider to whitelist rule 960011 for the endpoint URL.

= How do I exclude the endpoint from access logs? =

The healthcheck endpoint generates entries in your server access logs. To exclude it:

**Nginx** (add to your server block):

    location = /wp-json/wp-uptime/v1/check {
        access_log off;
        try_files $uri $uri/ /index.php?$args;
    }

**Apache** (add to your vhost or .htaccess):

    SetEnvIf Request_URI "^/wp-json/wp-uptime/v1/check" dontlog
    CustomLog /var/log/apache2/access.log combined env=!dontlog

**Shared hosting:** Contact your hosting provider to exclude this URL from logging.

== Screenshots ==

1. Admin settings page with token configuration
2. Monitoring tool configuration examples
3. Uptime Kuma setup

== Changelog ==

= 1.0.0 =
* Initial release
* Database connectivity check
* WordPress options check
* Theme existence check
* Optional homepage rendering check
* Token authentication (header + query param)
* Rate limiting
* Admin configuration page

== Upgrade Notice ==

= 1.0.0 =
Initial release.
