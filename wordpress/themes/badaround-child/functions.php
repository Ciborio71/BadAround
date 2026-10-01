<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Presentation-specific hooks for the BadAround child theme.
 * Application logic must remain in badaround-core.
 */
function badaround_child_enqueue_styles() {
    wp_enqueue_style(
        'badaround-child-style',
        get_stylesheet_uri(),
        array( 'astra-theme-css' ),
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', 'badaround_child_enqueue_styles', 20 );
