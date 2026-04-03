<?php

namespace UptimeHealthEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores the last MAX_ENTRIES check results in a non-autoloaded option so the
 * data is never pulled into WP's main options query on regular page requests.
 */
final class History {

	private const OPTION_KEY  = 'uptiheen_history';
	private const MAX_ENTRIES = 100;

	public function record( string $status, array $errors, array $durations ): void {
		$history = $this->get();
		array_unshift( $history, [
			'timestamp' => time(),
			'status'	=> $status,
			'errors'	=> $errors,
			'total_ms'	=> array_sum( $durations ),
		] );
		// $autoload = false — never loaded on regular page requests
		update_option( self::OPTION_KEY, array_slice( $history, 0, self::MAX_ENTRIES ), false );
	}

	/**
	 * @return array<int, array{timestamp:int, status:string, errors:string[], total_ms:int}>
	 */
	public function get(): array {
		return (array) get_option( self::OPTION_KEY, [] );
	}

	public function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}
