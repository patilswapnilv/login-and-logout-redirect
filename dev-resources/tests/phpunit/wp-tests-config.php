<?php

// Use the force!
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'DB_NAME', 'wordpress_test' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'WP_DEBUG_LOG', true );

define( 'WP_CACHE', false );
define( 'WP_ALLOW_REPAIR', true );

if ( ! file_exists( dirname( __FILE__ ) . '/includes/functions.php' ) ) {
	echo "Could not find tests/phpunit/includes/functions.php, have you run bin/install-wp-tests.sh ?\n";	exit( 1 );
}

require_once dirname( __FILE__ ) . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/login-and-logout-redirect.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require_once $_SERVER['WP_PHPUNIT__DIR'] . '/includes/bootstrap.php';
