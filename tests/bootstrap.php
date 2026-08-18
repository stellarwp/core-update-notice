<?php

/**
 * The package runs inside WordPress but the suite does not: Brain\Monkey stubs the handful of core
 * functions it calls, so the tests run with nothing but composer installed.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/tests/fixtures/wordpress/');
}
