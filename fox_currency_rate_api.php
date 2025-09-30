<?php
/*
Plugin Name: FOX Currency Rate API
Plugin URI:  https://example.com/fox-currency-rate-api
Description: Provides custom REST API endpoints to expose real-time currency rates configured in FOX - Currency Switcher Professional for WooCommerce. Use these endpoints to retrieve the latest exchange rates or convert amounts between currencies programmatically. No authentication is required because the plugin only exposes read-only rate data.
Version:     1.0.2
Author:      Gustavo Gonzalez
License:     GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: fox-currency-rate-api
*/

// Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Load additional CSFX endpoint.
require_once __DIR__ . '/includes/csfx-endpoint.php';

/**
 * Register custom REST API routes for exposing WOOCS currency data.
 *
 * The plugin registers three routes under the namespace 'fox-rate/v1':
 * 1. /currencies – Returns an array of all currencies defined in the FOX currency switcher with their rates and metadata.
 * 2. /currency/<code> – Returns details for a single currency specified by its ISO code (e.g. USD, EUR). Includes rate, symbol, position, etc.
 * 3. /convert – Converts an amount from one currency to another using the current rates. Accepts query parameters 'amount', 'from' and 'to'.
 *
 * All routes are publicly accessible and require no authentication (permission_callback returns true). If the FOX currency switcher plugin is not active, the endpoint returns an appropriate error.
 */
add_action( 'rest_api_init', function () {
    // Endpoint to get all available currencies with their rates.
    register_rest_route( 'fox-rate/v1', '/currencies', array(
        'methods'  => 'GET',
        'callback' => 'fox_rate_api_get_currencies',
        'permission_callback' => '__return_true',
        'args'     => array(),
    ) );

    // Endpoint to get rate and metadata for a specific currency.
    register_rest_route( 'fox-rate/v1', '/currency/(?P<code>[A-Za-z]{2,5})', array(
        'methods'  => 'GET',
        'callback' => 'fox_rate_api_get_currency',
        'permission_callback' => '__return_true',
        'args'     => array(
            'code' => array(
                'description' => __( 'Currency ISO code (e.g. USD, EUR)', 'fox-currency-rate-api' ),
                'type'        => 'string',
                'required'    => true,
            ),
        ),
    ) );

    // Endpoint to convert an amount from one currency to another.
    register_rest_route( 'fox-rate/v1', '/convert', array(
        'methods'  => 'GET',
        'callback' => 'fox_rate_api_convert',
        'permission_callback' => '__return_true',
        'args'     => array(
            'amount' => array(
                'description' => __( 'Numeric amount to convert', 'fox-currency-rate-api' ),
                'type'        => 'number',
                'required'    => true,
            ),
            'from'   => array(
                'description' => __( 'Source currency ISO code', 'fox-currency-rate-api' ),
                'type'        => 'string',
                'required'    => true,
            ),
            'to'     => array(
                'description' => __( 'Target currency ISO code', 'fox-currency-rate-api' ),
                'type'        => 'string',
                'required'    => true,
            ),
        ),
    ) );
} );

/**
 * Retrieve an array of all currencies configured in FOX - WooCommerce Currency Switcher (WOOCS).
 *
 * Each currency entry contains keys such as 'name', 'rate', 'symbol', 'position', and optional extras like 'flag' or 'description'.
 *
 * @param WP_REST_Request $request REST request object (unused).
 *
 * @return array|WP_Error Returns the currencies array on success or a WP_Error if the WOOCS plugin is not active.
 */
function fox_rate_api_get_currencies( WP_REST_Request $request ) {
    // Make sure WOOCS plugin is available and the global object is set.
    if ( ! isset( $GLOBALS['WOOCS'] ) || ! is_object( $GLOBALS['WOOCS'] ) ) {
        return new WP_Error( 'woocs_not_active', __( 'WOOCS plugin is not active or loaded', 'fox-currency-rate-api' ), array( 'status' => 500 ) );
    }

    global $WOOCS;

    // Retrieve all currencies. Set suppress_filters to true to avoid modifications.
    $currencies = $WOOCS->get_currencies( true );

    return rest_ensure_response( $currencies );
}

/**
 * Retrieve rate and metadata for a specific currency.
 *
 * @param WP_REST_Request $request REST request containing the currency code.
 *
 * @return array|WP_Error Returns an associative array of currency data or WP_Error if not found or plugin inactive.
 */
function fox_rate_api_get_currency( WP_REST_Request $request ) {
    $code = strtoupper( sanitize_key( $request->get_param( 'code' ) ) );

    if ( ! isset( $GLOBALS['WOOCS'] ) || ! is_object( $GLOBALS['WOOCS'] ) ) {
        return new WP_Error( 'woocs_not_active', __( 'WOOCS plugin is not active or loaded', 'fox-currency-rate-api' ), array( 'status' => 500 ) );
    }

    global $WOOCS;
    $currencies = $WOOCS->get_currencies( true );

    if ( isset( $currencies[ $code ] ) ) {
        return rest_ensure_response( $currencies[ $code ] );
    }

    return new WP_Error( 'currency_not_found', sprintf( __( 'Currency "%s" not found', 'fox-currency-rate-api' ), esc_html( $code ) ), array( 'status' => 404 ) );
}

/**
 * Convert an amount from one currency to another using WOOCS rates.
 *
 * @param WP_REST_Request $request REST request containing amount, from and to.
 *
 * @return array|WP_Error Returns converted amount and context or WP_Error on failure.
 */
function fox_rate_api_convert( WP_REST_Request $request ) {
    $amount = floatval( $request->get_param( 'amount' ) );
    $from   = strtoupper( sanitize_key( $request->get_param( 'from' ) ) );
    $to     = strtoupper( sanitize_key( $request->get_param( 'to' ) ) );

    if ( ! isset( $GLOBALS['WOOCS'] ) || ! is_object( $GLOBALS['WOOCS'] ) ) {
        return new WP_Error( 'woocs_not_active', __( 'WOOCS plugin is not active or loaded', 'fox-currency-rate-api' ), array( 'status' => 500 ) );
    }

    global $WOOCS;

    // Validate that the conversion method exists (available in WOOCS v2.4.0+).
    if ( ! method_exists( $WOOCS, 'convert_from_to_currency' ) ) {
        return new WP_Error( 'conversion_method_missing', __( 'Conversion method is not available in this version of WOOCS', 'fox-currency-rate-api' ), array( 'status' => 500 ) );
    }

    // Perform conversion; if invalid codes are passed, WOOCS will typically return zero.
    $converted_value = $WOOCS->convert_from_to_currency( $amount, $from, $to );

    return rest_ensure_response( array(
        'amount'     => $amount,
        'from'       => $from,
        'to'         => $to,
        'converted'  => $converted_value,
    ) );
}

/**
 * Optional: expose basic status endpoint for debugging.
 *
 * Example: GET /wp-json/fox-rate/v1/status will return OK if WOOCS is loaded.
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'fox-rate/v1', '/status', array(
        'methods'  => 'GET',
        'callback' => function () {
            if ( isset( $GLOBALS['WOOCS'] ) && is_object( $GLOBALS['WOOCS'] ) ) {
                return rest_ensure_response( array( 'status' => 'OK', 'message' => 'WOOCS is active' ) );
            }
            return new WP_Error( 'woocs_not_active', __( 'WOOCS plugin is not active or loaded', 'fox-currency-rate-api' ), array( 'status' => 500 ) );
        },
        'permission_callback' => '__return_true',
    ) );
} );

?>
