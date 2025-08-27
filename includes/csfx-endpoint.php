<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'fox-rate/v1', '/rate', array(
        'methods'             => 'GET',
        'callback'            => 'fox_rate_endpoint',
        'permission_callback' => '__return_true',
        'args'                => array(
            'from' => array(
                'description' => __( 'Source currency ISO code', 'fox-currency-rate-api' ),
                'type'        => 'string',
                'required'    => false,
                'default'     => 'USD',
            ),
            'to'   => array(
                'description' => __( 'Target currency ISO code', 'fox-currency-rate-api' ),
                'type'        => 'string',
                'required'    => false,
                'default'     => 'VES',
            ),
        ),
    ) );
} );

add_filter( 'rest_pre_serve_request', 'fox_rate_add_no_cache_headers', 10, 4 );

function fox_rate_add_no_cache_headers( $served, $result, $request, $server ) {
    if ( ! $request instanceof WP_REST_Request ) {
        return $served;
    }

    if ( 0 !== strpos( $request->get_route(), '/fox-rate/v1/rate' ) ) {
        return $served;
    }

    if ( headers_sent() ) {
        return $served;
    }

    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );

    return $served;
}

function fox_rate_endpoint( WP_REST_Request $req ) {
    $from = strtoupper( sanitize_text_field( (string) ( $req->get_param( 'from' ) ?: 'USD' ) ) );
    $to   = strtoupper( sanitize_text_field( (string) ( $req->get_param( 'to' ) ?: 'VES' ) ) );

    $ttl       = 5 * MINUTE_IN_SECONDS;
    $now       = time();
    $cache_key = 'fox_rate_' . $from . '_' . $to;
    $last_key  = 'fox_last_good_rate_' . $from . '_' . $to;

    $cached = get_transient( $cache_key );
    if ( is_array( $cached ) ) {
        $response          = $cached;
        $response['source'] = 'cache';
        $response['stale']  = false;
        if ( isset( $response['updated'] ) ) {
            $response['age'] = max( 0, $now - (int) $response['updated'] );
        }

        return new WP_REST_Response( $response, 200 );
    }

    try {
        $rate = fox_fetch_upstream_rate( $from, $to );
        if ( ! is_numeric( $rate ) || $rate <= 0 ) {
            throw new Exception( 'bad_rate' );
        }

        $data = array(
            'rate'    => (float) $rate,
            'from'    => $from,
            'to'      => $to,
            'updated' => $now,
            'ttl'     => $ttl,
            'source'  => 'upstream',
            'stale'   => false,
        );

        set_transient( $cache_key, $data, $ttl );
        update_option( $last_key, $data, false );

        return new WP_REST_Response( $data, 200 );
    } catch ( Throwable $e ) {
        $stale = get_option( $last_key );
        if ( is_array( $stale ) && isset( $stale['updated'] ) ) {
            $age = max( 0, $now - (int) $stale['updated'] );
            if ( $age <= DAY_IN_SECONDS ) {
                $stale['stale']  = true;
                $stale['source'] = 'stale';
                $stale['age']    = $age;

                return new WP_REST_Response( $stale, 200 );
            }
        }

        return new WP_REST_Response( array( 'error' => 'upstream_unavailable' ), 503 );
    }
}

function fox_fetch_upstream_rate( string $from, string $to ) {
    $base_url = get_option( 'fox_upstream_url', '' );
    if ( empty( $base_url ) ) {
        throw new Exception( 'no_upstream_url' );
    }

    $url = add_query_arg(
        array(
            'from' => $from,
            'to'   => $to,
        ),
        $base_url
    );

    $url = apply_filters( 'fox_rate_upstream_url', $url, $from, $to );

    $response = wp_remote_get(
        $url,
        array(
            'timeout'     => 2,
            'redirection' => 0,
            'sslverify'   => true,
            'headers'     => array(
                'Accept'     => 'application/json',
                'User-Agent' => 'FoxCurrencyRate/1.0',
            ),
            'decompress'         => true,
            'reject_unsafe_urls' => true,
        )
    );

    if ( is_wp_error( $response ) ) {
        throw new Exception( 'wp_error' );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( 200 !== $code || empty( $body ) ) {
        throw new Exception( 'bad_response' );
    }

    $decoded = json_decode( $body, true );
    if ( JSON_ERROR_NONE !== json_last_error() ) {
        throw new Exception( 'bad_json' );
    }

    if ( isset( $decoded['rate'] ) ) {
        return (float) $decoded['rate'];
    }

    if ( isset( $decoded['data']['rate'] ) ) {
        return (float) $decoded['data']['rate'];
    }

    throw new Exception( 'zero_rate' );
}
