# Uptime Health Endpoint

A WordPress plugin that provides a secure REST API endpoint for uptime monitoring tools like Uptime Kuma, UptimeRobot, Pingdom, and more.

## Why?

A simple HTTP check on your homepage only tells you the server responds — not that WordPress is actually working. This plugin verifies:

- ✅ **Database connectivity** — MySQL/MariaDB is responding
- ✅ **WordPress core** — Options table is readable
- ✅ **Theme** — Active theme exists and loads
- ✅ **Homepage rendering** (optional) — No PHP errors on the frontend

## Installation

### From WordPress Admin

1. Download the [latest release](../../releases/latest)
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file and activate

### Manual

1. Download and extract to `/wp-content/plugins/wp-uptime-endpoint/`
2. Activate in WordPress admin

## Configuration

### Option 1: Admin Panel (recommended)

Go to **Settings > WP Uptime Endpoint** and:

1. Click **Generate New Token** or enter your own
2. Optionally enable **Homepage Check**
3. Copy the configuration for your monitoring tool

### Option 2: wp-config.php (for automated deployments)

```php
define('UPTIHEEN_TOKEN', 'your-64-character-token-here');
define('UPTIHEEN_HOMEPAGE', true); // Optional
```

Generate a secure token:

```bash
openssl rand -hex 32
```

## Endpoint

```
GET /wp-json/wp-uptime/v1/check
```

### Authentication

**Header** (recommended):
```
X-Healthcheck-Token: your-token
```

**Query parameter** (for tools that don't support headers):
```
?token=your-token
```

### Responses

| Status | Body | Meaning |
|--------|------|---------|
| `200` | `{"status":"ok"}` | All checks passed |
| `403` | `{"status":"forbidden"}` | Invalid or missing token |
| `503` | `{"status":"fail"}` | One or more checks failed |

## Monitoring Tool Setup

### Uptime Kuma

| Setting | Value |
|---------|-------|
| Monitor Type | HTTP(s) |
| URL | `https://your-site.com/wp-json/wp-uptime/v1/check` |
| Method | GET |
| Headers | `X-Healthcheck-Token: your-token` |
| Expected Status | 200 |

### UptimeRobot

| Setting | Value |
|---------|-------|
| Monitor Type | HTTP(s) |
| URL | `https://your-site.com/wp-json/wp-uptime/v1/check?token=your-token` |

> Note: UptimeRobot free plan doesn't support custom headers. Pro plans can use headers like Uptime Kuma.

## Security

- 🔐 **Token required** — No anonymous access
- ⏱️ **Rate limiting** — 10 failed attempts/minute per IP
- 🛡️ **Timing-safe comparison** — Prevents timing attacks
- 🚫 **No sensitive data** — Only returns `ok`, `fail`, or `forbidden`

## Exclude from Access Logs

The healthcheck endpoint generates entries in your server access logs. To exclude it:

### Nginx

```nginx
location = /wp-json/wp-uptime/v1/check {
    access_log off;
    try_files $uri $uri/ /index.php?$args;
}
```

### Apache

```apache
SetEnvIf Request_URI "^/wp-json/wp-uptime/v1/check" dontlog
CustomLog /var/log/apache2/access.log combined env=!dontlog
```

**Shared hosting:** Contact your hosting provider to exclude this URL from logging.

## Requirements

- WordPress 5.0+
- PHP 7.4+

## License

GPL-2.0+

## Author

Kevin Allioli
