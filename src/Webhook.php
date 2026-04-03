<?php

namespace UptimeHealthEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fires a non-blocking POST to the configured webhook URL whenever the overall
 * status flips (ok → fail or fail → ok).
 *
 * Uses blocking=false so webhook delivery never adds latency to the health check response.
 *
 * Can be configured via:
 *	 define( 'UPTIHEEN_WEBHOOK_URL', 'https://...' );
 * or via the admin panel.
 */
final class Webhook {

	private const STATUS_OPTION = 'uptiheen_last_status';

	public function maybe_fire( string $current, array $errors ): void {
		$url = $this->url();
		if ( empty( $url ) ) {
			return;
		}

		$previous = (string) get_option( self::STATUS_OPTION, '' );
		update_option( self::STATUS_OPTION, $current, false );

		if ( '' === $previous || $previous === $current ) {
			return; // Status unchanged — nothing to report
		}

		wp_remote_post( $url, [
			'timeout'	  => 0.01,
			'blocking'	  => false,
			'data_format' => 'body',
			'headers'	  => [ 'Content-Type' => 'application/json' ],
			'body'		  => wp_json_encode( [
				'event'		=> 'status_change',
				'previous'	=> $previous,
				'current'	=> $current,
				'errors'	=> $errors,
				'site_url'	=> get_option( 'siteurl' ),
				'timestamp' => time(),
			] ),
		] );
	}

	private function url(): string {
		return defined( 'UPTIHEEN_WEBHOOK_URL' )
			? (string) UPTIHEEN_WEBHOOK_URL
			: (string) get_option( 'uptiheen_webhook_url', '' );
	}
}
