<?php

namespace UptimeHealthEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

/**
 * Thin orchestrator: registers the REST route and delegates all logic to
 * dedicated classes (Authenticator, Health_Checker, History, Webhook).
 *
 * Adds security headers to every response so proxies and CDNs never cache
 * the result, regardless of their default configuration.
 */
final class REST_Controller {

    public function register_routes(): void {
        register_rest_route( 'wp-uptime/v1', '/check', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => [ $this, 'handle' ],
        ] );
    }

    public function handle( WP_REST_Request $request ): WP_REST_Response {
        if ( ! ( new Authenticator() )->validate( $request ) ) {
            return $this->secured( new WP_REST_Response( [ 'status' => 'forbidden' ], 403 ) );
        }

        $checker = new Health_Checker();
        $result  = $checker->run();
        $errors  = $result['errors'];
        $status  = empty( $errors ) ? 'ok' : 'fail';

        ( new History() )->record( $status, $errors, $result['durations'] );
        ( new Webhook() )->maybe_fire( $status, $errors );

        $body = [ 'status' => $status ];
        if ( defined( 'UPTIHEEN_DEBUG' ) && UPTIHEEN_DEBUG ) {
            $body['checks']    = $errors;
            $body['durations'] = $result['durations'];
        }

        return $this->secured( new WP_REST_Response( $body, $status === 'ok' ? 200 : 503 ) );
    }

    private function secured( WP_REST_Response $r ): WP_REST_Response {
        $r->header( 'Cache-Control',          'no-store, no-cache, must-revalidate' );
        $r->header( 'X-Content-Type-Options', 'nosniff' );
        $r->header( 'X-Robots-Tag',           'noindex, nofollow' );
        return $r;
    }
}
