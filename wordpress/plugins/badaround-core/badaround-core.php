<?php
/**
 * Plugin Name: BadAround Core
 * Description: Core application logic for the BadAround platform.
 * Version: 0.1.0
 * Author: BadAround
 * Text Domain: badaround-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BADAROUND_CORE_VERSION', '0.1.0' );
define( 'BADAROUND_CORE_FILE', __FILE__ );
define( 'BADAROUND_CORE_PATH', plugin_dir_path( __FILE__ ) );

require_once BADAROUND_CORE_PATH . 'includes/class-badaround-core.php';

function badaround_core_run() {
    $plugin = new BadAround_Core();
    $plugin->run();
}

badaround_core_run();
