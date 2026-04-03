<?php

namespace UptimeHealthEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP-CLI integration.
 *
 * Registered in uptime-health-endpoint.php only when WP_CLI is defined,
 * so this file is loaded conditionally and \WP_CLI_Command is always available.
 *
 * Usage:
 *   wp uptime check
 *   wp uptime check --format=json
 *   wp uptime history
 *   wp uptime history --limit=50 --format=json
 *
 * @when after_wp_load
 */
class CLI_Command extends \WP_CLI_Command {

    /**
     * Run all configured health checks.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Accepts: table, json. Default: table.
     *
     * ## EXAMPLES
     *
     *   wp uptime check
     *   wp uptime check --format=json
     *
     * @subcommand check
     */
    public function check( array $args, array $assoc_args ): void {
        $result    = ( new Health_Checker() )->run();
        $errors    = $result['errors'];
        $durations = $result['durations'];
        $status    = empty( $errors ) ? 'ok' : 'fail';

        $rows = [];
        foreach ( $durations as $key => $ms ) {
            $rows[] = [
                'check'   => $key,
                'status'  => in_array( $key, $errors, true ) ? 'FAIL' : 'ok',
                'time_ms' => $ms,
            ];
        }

        $format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

        if ( $format === 'json' ) {
            \WP_CLI::line( (string) wp_json_encode( [
                'status'   => $status,
                'checks'   => $rows,
                'total_ms' => array_sum( $durations ),
            ] ) );
            if ( $status !== 'ok' ) {
                exit( 1 );
            }
            return;
        }

        \WP_CLI\Utils\format_items( 'table', $rows, [ 'check', 'status', 'time_ms' ] );
        \WP_CLI::line( '' );
        \WP_CLI::line( 'Total: ' . array_sum( $durations ) . ' ms' );
        \WP_CLI::line( '' );

        if ( $status === 'ok' ) {
            \WP_CLI::success( 'All checks passed.' );
        } else {
            \WP_CLI::error( 'Failed: ' . implode( ', ', $errors ), false );
            exit( 1 );
        }
    }

    /**
     * Display recent check history.
     *
     * ## OPTIONS
     *
     * [--limit=<n>]
     * : Number of entries to display. Default: 20.
     *
     * [--format=<format>]
     * : Output format. Accepts: table, json. Default: table.
     *
     * ## EXAMPLES
     *
     *   wp uptime history
     *   wp uptime history --limit=50 --format=json
     *
     * @subcommand history
     */
    public function history( array $args, array $assoc_args ): void {
        $history = ( new History() )->get();

        if ( empty( $history ) ) {
            \WP_CLI::line( 'No history recorded yet.' );
            return;
        }

        $limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 20;
        $rows  = [];
        foreach ( array_slice( $history, 0, $limit ) as $entry ) {
            $rows[] = [
                'date'    => date_i18n( 'Y-m-d H:i:s', $entry['timestamp'] ),
                'status'  => strtoupper( $entry['status'] ),
                'errors'  => ! empty( $entry['errors'] ) ? implode( ', ', $entry['errors'] ) : '—',
                'time_ms' => $entry['total_ms'],
            ];
        }

        $format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

        if ( $format === 'json' ) {
            \WP_CLI::line( (string) wp_json_encode( $rows ) );
            return;
        }

        \WP_CLI\Utils\format_items( 'table', $rows, [ 'date', 'status', 'errors', 'time_ms' ] );
    }
}
