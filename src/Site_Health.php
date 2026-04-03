<?php

namespace UptimeHealthEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes health checks in WP's native Tools > Site Health screen.
 *
 * Slow checks (outbound HTTP, homepage loopback) are excluded here because
 * they exceed the time budget of a synchronous Site Health run.
 */
final class Site_Health {

	public function register(): void {
		add_filter( 'site_status_tests', [ $this, 'add_tests' ] );
	}

	public function add_tests( array $tests ): array {
		$checks = [
			'db'		 => __( 'Database connectivity', 'uptime-health-endpoint' ),
			'wp_options' => __( 'WordPress options table', 'uptime-health-endpoint' ),
			'theme'		 => __( 'Active theme', 'uptime-health-endpoint' ),
			'cron'		 => __( 'WP-Cron queue health', 'uptime-health-endpoint' ),
			'memory'	 => __( 'PHP memory usage', 'uptime-health-endpoint' ),
			'disk'		 => __( 'Available disk space', 'uptime-health-endpoint' ),
			'uploads'	 => __( 'Uploads directory writable', 'uptime-health-endpoint' ),
			'cache'		 => __( 'Object cache', 'uptime-health-endpoint' ),
		];
		foreach ( $checks as $key => $label ) {
			$tests['direct'][ 'uptiheen_' . $key ] = [
				/* translators: %s: check label */
				'label' => sprintf( __( 'Uptime: %s', 'uptime-health-endpoint' ), $label ),
				'test'	=> function () use ( $key ) {
					return $this->run_test( $key );
				},
			];
		}
		return $tests;
	}

	private function run_test( string $key ): array {
		$checker = new Health_Checker();
		$method	 = 'check_' . ( 'wp_options' === $key ? 'options' : $key );

		if ( ! method_exists( $checker, $method ) ) {
			return $this->result( $key, 'good', __( 'Check not available.', 'uptime-health-endpoint' ) );
		}

		$ok = (bool) [ $checker, $method ]();
		return $this->result(
			$key,
			$ok ? 'good' : 'critical',
			$ok
				? __( 'This check is passing.', 'uptime-health-endpoint' )
				: __( 'This check is failing — review the Uptime Health Endpoint settings.', 'uptime-health-endpoint' )
		);
	}

	private function result( string $key, string $status, string $description ): array {
		return [
			'label'		  => sprintf(
				/* translators: %s: check key */
				__( 'Uptime: %s', 'uptime-health-endpoint' ),
				$key
			),
			'status'	  => $status,
			'badge'		  => [
				'label' => __( 'Uptime', 'uptime-health-endpoint' ),
				'color' => 'good' === $status ? 'blue' : 'red',
			],
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'	  => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=wp-uptime-endpoint' ) ),
				esc_html__( 'Configure', 'uptime-health-endpoint' )
			),
			'test'		  => 'uptiheen_' . $key,
		];
	}
}
