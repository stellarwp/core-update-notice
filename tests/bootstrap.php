<?php declare(strict_types=1);

/**
 * The package runs inside WordPress but the suite does not: Brain\Monkey stubs the handful of core
 * functions it calls, so the tests run with nothing but Composer installed.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/Fixtures/FrozenV1/CoreUpdateNotice.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/tests/fixtures/wordpress/' );
}
