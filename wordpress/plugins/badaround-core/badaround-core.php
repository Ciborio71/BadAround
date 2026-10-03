<?php
/**
 * Plugin Name: BadAround Core
 * Description: Core application logic for the BadAround platform.
 * Version: 0.5.4
 * Author: BadAround
 * Text Domain: badaround-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BADAROUND_CORE_VERSION', '0.5.4' );
define( 'BADAROUND_CORE_FILE', __FILE__ );
define( 'BADAROUND_CORE_PATH', plugin_dir_path( __FILE__ ) );

require_once BADAROUND_CORE_PATH . 'includes/class-badaround-core.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-installer.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-event-post-type.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-territory-admin.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-audit-log.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-report-repository.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-wpforms-field-mapper.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-event-type-resolver.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-territory-resolver.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-media-repository.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-publication-service.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-moderation-service.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-moderation-admin.php';
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-wpforms-event-intake.php';

register_activation_hook( BADAROUND_CORE_FILE, array( 'BadAround_Installer', 'activate' ) );

function badaround_core_run() {
    $plugin = new BadAround_Core();
    $plugin->run();
}

badaround_core_run();
