<?php
/**
 * Loads the theme's .env file (KEY=value per line) as PHP constants, e.g. PAYPAL_CLIENT_SECRET.
 * Keys already defined in wp-config.php win; empty values are skipped. The .env file is never
 * committed (see .gitignore) and is blocked from the web by .htaccess.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

( function ( $file ) {
    if ( ! is_readable( $file ) ) return;

    foreach ( (array) file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
        $line = trim( $line );

        if ( $line === '' || $line[0] === '#' || strpos( $line, '=' ) === false ) continue;

        list( $key, $value ) = array_map( 'trim', explode( '=', $line, 2 ) );

        if ( ! preg_match( '/^[A-Z][A-Z0-9_]*$/', $key ) || defined( $key ) ) continue;

        if ( preg_match( '/^(["\'])(.*)\1$/', $value, $m ) ) $value = $m[2]; // strip surrounding quotes

        if ( $value !== '' ) define( $key, $value );
    }
} )( dirname( __DIR__ ) . '/.env' );
