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

		$discovery = new BadAround_Discovery_Query();
		$discovery->register_hooks();

		$sentinels = new BadAround_Sentinel_Service();
		$sentinels->register_hooks();

		$sentinel_matching = new BadAround_Sentinel_Matching_Service();
		$sentinel_matching->register_hooks();

		$contributions = new BadAround_Contribution_Service();
		$contributions->register_hooks();

		$contribution_projection = new BadAround_Contribution_Post_Type();
		$contribution_projection->register_hooks();

		$contribution_moderation = new BadAround_Contribution_Moderation_Admin();
		$contribution_moderation->register_hooks();

		$wpforms_event_intake = new BadAround_WPForms_Event_Intake();
		$wpforms_event_intake->register_hooks();

		$moderation_admin = new BadAround_Moderation_Admin();
		$moderation_admin->register_hooks();
	}
}
