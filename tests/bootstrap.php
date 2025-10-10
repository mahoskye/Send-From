<?php
/**
 * PHPUnit bootstrap file for Send From plugin tests
 *
 * @package Send_From
 */

// Determine the WordPress test library path
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// If WordPress test library is not found, provide instructions
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php\n";
    echo "Please install WordPress test library:\n";
    echo "1. Run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
    echo "2. Or set WP_TESTS_DIR environment variable to your WordPress tests directory\n";
    exit(1);
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    require dirname(dirname(__FILE__)) . '/plugin/send-from.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';
