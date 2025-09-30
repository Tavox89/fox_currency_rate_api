<?php
// CSFX rate endpoint for USD→VES (or any currency pair via query params).
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'csfx/v1', '/rate', array(
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function ( WP_REST_Request $req ) {
            $from = strtoupper( $req->get_param( 'from' ) ?: 'USD' );
            $to   = strtoupper( $req->get_param( 'to' ) ?: 'VES' );

            // Cache key per currency pair.
            $cache_key = 'csfx_rate_' . $from . '_' . $to;
            $cached    = get_transient( $cache_key );
            if ( false !== $cached ) {
                return rest_ensure_response( $cached );
            }

            $data = array(
                'mode'    => 'fox',
                'rate'    => 0.0,
                'from'    => $from,
                'to'      => $to,
                'updated' => current_time( 'c' ),
                'source'  => 'fox',
            );

            if ( class_exists( 'WOOCS' ) ) {
                global $WOOCS;
                $currs = is_object( $WOOCS ) && method_exists( $WOOCS, 'get_currencies' ) ? $WOOCS->get_currencies() : array();
                $base  = get_option( 'woocommerce_currency', 'USD' );
            
                // --- Alias VES <-> VEF for backwards compatibility.
                $alias = function ( $code, $set ) {
                    if ( isset( $set[ $code ] ) ) {
                        return $code;
                    }
                    if ( 'VES' === $code && isset( $set['VEF'] ) ) {
                        return 'VEF';
                    }
                    if ( 'VEF' === $code && isset( $set['VES'] ) ) {
                        return 'VES';
                    }
                    return $code;
                };

                $from_x = $alias( $from, $currs );
                $to_x   = $alias( $to, $currs );
                $r_from = ( $from_x === $base ) ? 1.0 : floatval( $currs[ $from_x ]['rate'] ?? 0 );
                $r_to   = ( $to_x === $base ) ? 1.0 : floatval( $currs[ $to_x ]['rate'] ?? 0 );
                if ( $r_from > 0 && $r_to > 0 ) {
                    $data['rate'] = $r_to / $r_from;
                              $data['from'] = $from_x; // expose mapped codes
                    $data['to']   = $to_x;
                } else {
                    $data['error'] = 'invalid_currency_rate';
                }
            } else {
                $data['error'] = 'no_woocs';
            }

            // Cache for 60 seconds even if rate is 0 to avoid request flood.
            set_transient( $cache_key, $data, 60 );

            return rest_ensure_response( $data );
        },
    ) );
} );