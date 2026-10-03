<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BadAround_Core {
    public function run() {
        $installer = new BadAround_Installer();
        $installer->register_hooks();

        $event_post_type = new BadAround_Event_Post_Type();
        $event_post_type->register_hooks();

        $territory_admin = new BadAround_Territory_Admin();
        $territory_admin->register_hooks();

        $wpforms_event_intake = new BadAround_WPForms_Event_Intake();
        $wpforms_event_intake->register_hooks();

        $moderation_admin = new BadAround_Moderation_Admin();
        $moderation_admin->register_hooks();
    }
}
