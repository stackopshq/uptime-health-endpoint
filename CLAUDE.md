# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Uptime Health Endpoint is a WordPress plugin that exposes a single REST endpoint (`GET /wp-json/wp-uptime/v1/check`) for uptime monitoring tools. It runs 4 health checks (database, WordPress core, active theme, optional homepage loopback) and returns a simple JSON status.

## Development

No build system, package manager, or external dependencies. This is pure PHP — edit `uptime-health-endpoint.php` directly.

To test locally: install the plugin in a WordPress instance, configure a token via the admin panel (Settings > Uptime Health Endpoint) or via `wp-config.php`, then hit the endpoint.

```bash
# Test the endpoint manually
curl -H "X-Healthcheck-Token: <token>" https://example.com/wp-json/wp-uptime/v1/check
```

## Architecture

The plugin uses OOP with a `UptimeHealthEndpoint` namespace. The main file (`uptime-health-endpoint.php`) is a 43-line entry point that loads classes from `src/` and calls `Plugin::instance()`.

```
uptime-health-endpoint.php   Entry point — plugin header + require_once loop + bootstrap
src/
  Plugin.php          Singleton bootstrapper, conditional hooks per request type
  REST_Controller.php Registers /wp-json/wp-uptime/v1/check, orchestrates the response
  Authenticator.php   Multi-token auth, IP allowlist (CIDR), per-IP rate limiting
  Health_Checker.php  All health checks + custom filter hook + per-check timing
  History.php         Stores last 100 check results in a non-autoloaded option
  Webhook.php         Non-blocking POST on status change (ok↔fail)
  Admin.php           Full settings page (only loaded on is_admin() requests)
  Site_Health.php     WP Site Health integration (Tools > Site Health)
  CLI_Command.php     WP-CLI: `wp uptime check` / `wp uptime history`
```

**Key design decisions:**

- `Plugin::__construct()` conditionally boots: REST requests load only `REST_Controller`; admin requests load only `Admin` + `Site_Health`; frontend loads nothing.
- `CLI_Command.php` is loaded only when `WP_CLI` is defined (avoids fatal error — `\WP_CLI_Command` doesn't exist at runtime otherwise).
- `Health_Checker` runs checks cheapest→most expensive (in-memory → DB → network) and short-circuits on first failure when `UPTIHEEN_DEBUG` is off.
- `get_transient()` is called exactly once per auth request; the result is reused in `set_transient()`.
- `History` and `Webhook` options use `$autoload = false` so they are never pulled into WP's main options query.

**Configuration priority:** `wp-config.php` constants take precedence over admin panel settings for every option. Pattern: `defined('UPTIHEEN_X') ? UPTIHEEN_X : get_option('uptiheen_x', $default)`.

**Token auth:** `X-Healthcheck-Token` header (preferred) or `?token=` query param. Multiple tokens supported (one per line in admin). `hash_equals()` prevents timing attacks.

**Response shape:** `{"status":"ok"|"fail"|"forbidden"}`. With `UPTIHEEN_DEBUG=true`: adds `checks` (failed check keys) and `durations` (ms per check).

**Custom checks hook:**
```php
add_filter( 'uptiheen_checks', function( array $checks ): array {
    $checks['my_check'] = fn() => my_service_is_up();
    return $checks;
} );
```

## WordPress.org Compatibility

The plugin is published on WordPress.org. Follow WordPress plugin coding standards when making changes:
- Use `sanitize_*` and `esc_*` functions for all input/output
- Prefix all functions, constants, and option names with `uptiheen_` / `UPTIHEEN_`
- Keep zero external dependencies (no Composer, no npm)
- The `readme.txt` (not `README.md`) is what WordPress.org reads — keep both in sync
