<?php

namespace UptimeHealthEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;

/**
 * Validates the bearer token and enforces per-IP rate limiting.
 *
 * Token resolution order (first match wins):
 *	 1. UPTIHEEN_TOKENS constant (string[])
 *	 2. UPTIHEEN_TOKEN	constant (string, backward compat)
 *	 3. uptiheen_tokens option	 (newline-separated)
 *	 4. uptiheen_token	option	 (string, backward compat)
 *
 * IP allowlist: if the client IP is in the list, the token check is skipped entirely.
 * Supports exact IPs and IPv4 CIDR notation (e.g. 10.0.0.0/8).
 *
 * X-Forwarded-For / X-Real-IP are only trusted when REMOTE_ADDR is a private or
 * reserved address, preventing clients from spoofing their IP to bypass rate limiting.
 */
final class Authenticator {

	private const MAX_ATTEMPTS	 = 10;
	private const WINDOW_SECONDS = 60;

	public function validate( WP_REST_Request $request ): bool {
		$ip		  = $this->client_ip();
		$rate_key = 'uptiheen_rate_' . md5( $ip );
		$attempts = (int) get_transient( $rate_key ); // single read, reused below

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			return false;
		}

		// IP allowlist bypasses token validation entirely
		if ( $this->ip_in_allowlist( $ip ) ) {
			delete_transient( $rate_key );
			return true;
		}

		$tokens = $this->expected_tokens();
		if ( empty( $tokens ) ) {
			return false;
		}

		// Header takes priority over query param
		$provided = (string) ( $request->get_header( 'X-Healthcheck-Token' )
			?? $request->get_param( 'token' )
			?? '' );

		if ( '' === $provided ) {
			return false;
		}

		foreach ( $tokens as $expected ) {
			if ( hash_equals( $expected, $provided ) ) {
				delete_transient( $rate_key ); // Reset counter on success
				return true;
			}
		}

		// Reuse $attempts from the single get_transient() call above
		set_transient( $rate_key, $attempts + 1, self::WINDOW_SECONDS );
		return false;
	}

	/** @return string[] */
	private function expected_tokens(): array {
		if ( defined( 'UPTIHEEN_TOKENS' ) && is_array( UPTIHEEN_TOKENS ) ) {
			return array_values( array_filter( array_map( 'trim', UPTIHEEN_TOKENS ) ) );
		}
		if ( defined( 'UPTIHEEN_TOKEN' ) && '' !== (string) UPTIHEEN_TOKEN ) {
			return [ (string) UPTIHEEN_TOKEN ];
		}
		$multi = trim( (string) get_option( 'uptiheen_tokens', '' ) );
		if ( '' !== $multi ) {
			return array_values( array_filter( array_map( 'trim', explode( "\n", $multi ) ) ) );
		}
		$single = (string) get_option( 'uptiheen_token', '' );
		return '' !== $single ? [ $single ] : [];
	}

	private function ip_in_allowlist( string $ip ): bool {
		if ( defined( 'UPTIHEEN_IP_ALLOWLIST' ) && is_array( UPTIHEEN_IP_ALLOWLIST ) ) {
			$list = UPTIHEEN_IP_ALLOWLIST;
		} else {
			$raw  = trim( (string) get_option( 'uptiheen_ip_allowlist', '' ) );
			$list = '' !== $raw ? array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) : [];
		}

		foreach ( $list as $entry ) {
			if ( false !== strpos( $entry, '/' ) ) {
				if ( $this->ip_in_cidr( $ip, $entry ) ) {
					return true;
				}
			} elseif ( $ip === $entry ) {
				return true;
			}
		}
		return false;
	}

	private function ip_in_cidr( string $ip, string $cidr ): bool {
		// IPv6 CIDR not supported — fall through to exact-match logic
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return false;
		}
		[ $subnet, $bits ] = explode( '/', $cidr, 2 );
		$bits		 = max( 0, min( 32, (int) $bits ) );
		$ip_long	 = ip2long( $ip );
		$subnet_long = ip2long( $subnet );
		if ( false === $ip_long || false === $subnet_long ) {
			return false;
		}
		$mask = -1 << ( 32 - $bits );
		return ( $ip_long & $mask ) === ( $subnet_long & $mask );
	}

	private function client_ip(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$remote = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		// Trust forwarded headers only when the direct connection is from a
		// private/reserved IP (i.e. an actual reverse proxy, not a spoofing client)
		$via_proxy = false === filter_var(
			$remote,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);

		if ( $via_proxy ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$parts	   = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
				$candidate = trim( $parts[0] );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					return $candidate;
				}
			}
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
				$candidate = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					return $candidate;
				}
			}
		}

		return $remote;
	}
}
