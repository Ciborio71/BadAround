<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owns the versioned database schema for private and relational data. */
class BadAround_Installer {
	const SCHEMA_VERSION = '1.8.0';
	const OPTION_NAME    = 'ba_db_schema_version';

	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		/*
		 * Schema upgrades must not depend on an administrator opening wp-admin.
		 * Priority 30 runs after the content model/taxonomies registered on init.
		 */
		add_action( 'init', array( $this, 'maybe_upgrade' ), 30 );
	}

	public static function activate() {
		self::install_schema();
		self::install_capabilities();
		$content_model = new BadAround_Event_Post_Type();
		$content_model->register_content_model();
		self::install_event_taxonomy();
		flush_rewrite_rules();
	}

	private static function install_capabilities() {
		$administrator = get_role( 'administrator' );
		if ( ! $administrator ) {
			return;
		}

		$capabilities = array(
			'edit_ba_evento',
			'read_ba_evento',
			'delete_ba_evento',
			'edit_ba_eventi',
			'edit_others_ba_eventi',
			'publish_ba_eventi',
			'read_private_ba_eventi',
			'delete_ba_eventi',
			'delete_private_ba_eventi',
			'delete_published_ba_eventi',
			'delete_others_ba_eventi',
			'edit_private_ba_eventi',
			'edit_published_ba_eventi',
			'create_ba_eventi',
			'ba_moderate_events',
			'ba_view_private_reports',
			'ba_view_private_media',
			'ba_view_audit_log',
			'ba_moderate_contributions',
			'ba_view_private_contributions',
		);

		foreach ( $capabilities as $capability ) {
			$administrator->add_cap( $capability );
		}
	}

	public function maybe_upgrade() {
		if ( self::SCHEMA_VERSION !== get_option( self::OPTION_NAME ) ) {
			self::install_schema();
			self::install_capabilities();
			self::install_event_taxonomy();
		}
	}

	private static function install_event_taxonomy() {
		$taxonomy = BadAround_Event_Post_Type::EVENT_TYPE_TAX;

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$legacy_vehicles = get_term_by( 'slug', 'veicolo-o-mobilita', $taxonomy );
		$vehicles        = get_term_by( 'slug', 'veicoli', $taxonomy );

		if ( ! $vehicles && $legacy_vehicles instanceof WP_Term ) {
			wp_update_term(
				$legacy_vehicles->term_id,
				$taxonomy,
				array(
					'name' => 'Veicoli',
					'slug' => 'veicoli',
				)
			);
			$vehicles = get_term( $legacy_vehicles->term_id, $taxonomy );
		}

		$parents = array(
			'veicoli'              => 'Veicoli',
			'case-e-attivita'      => 'Case e attività',
			'pericoli'             => 'Pericoli',
			'spazi-pubblici'       => 'Spazi pubblici',
			'animali'              => 'Animali',
			'oggetti-e-documenti'  => 'Oggetti e documenti',
		);

		foreach ( $parents as $slug => $name ) {
			if ( ! term_exists( $slug, $taxonomy ) ) {
				wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
			}
		}

		$public_spaces = get_term_by( 'slug', 'spazi-pubblici', $taxonomy );
		$urban_decay   = get_term_by( 'slug', 'degrado-urbano', $taxonomy );

		if (
			$public_spaces instanceof WP_Term &&
			$urban_decay instanceof WP_Term &&
			0 === (int) $urban_decay->parent
		) {
			wp_update_term(
				$urban_decay->term_id,
				$taxonomy,
				array( 'parent' => (int) $public_spaces->term_id )
			);
		}
	}

	private static function install_schema() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$reports         = $wpdb->prefix . 'ba_reports';
		$media           = $wpdb->prefix . 'ba_report_media';
		$audit           = $wpdb->prefix . 'ba_audit_log';
		$sentinels       = $wpdb->prefix . 'ba_sentinels';
		$sentinel_matches = $wpdb->prefix . 'ba_sentinel_event_matches';
		$contributions    = $wpdb->prefix . 'ba_contributions';
		$contribution_media = $wpdb->prefix . 'ba_contribution_media';
		$contribution_notifications = $wpdb->prefix . 'ba_contribution_notifications';

		$sql = "CREATE TABLE {$reports} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned DEFAULT NULL,
			parent_report_id bigint(20) unsigned DEFAULT NULL,
			report_type varchar(32) NOT NULL DEFAULT 'initial',
			source_type varchar(32) NOT NULL DEFAULT 'wpforms',
			source_form_id bigint(20) unsigned DEFAULT NULL,
			source_entry_id bigint(20) unsigned DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			status varchar(32) NOT NULL DEFAULT 'received',
			author_name varchar(191) DEFAULT NULL,
			author_surname varchar(191) DEFAULT NULL,
			author_email varchar(191) DEFAULT NULL,
			author_phone varchar(64) DEFAULT NULL,
			contact_preference varchar(32) DEFAULT NULL,
			content_original longtext DEFAULT NULL,
			exact_address text DEFAULT NULL,
			exact_civic_number varchar(32) DEFAULT NULL,
			exact_lat decimal(10,7) DEFAULT NULL,
			exact_lng decimal(10,7) DEFAULT NULL,
			full_plate varchar(32) DEFAULT NULL,
			consent_privacy tinyint(1) DEFAULT NULL,
			consent_publication tinyint(1) DEFAULT NULL,
			consent_contact tinyint(1) DEFAULT NULL,
			consent_version varchar(64) DEFAULT NULL,
			consented_at datetime DEFAULT NULL,
			payload_hash char(64) DEFAULT NULL,
			ip_hash char(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_entry (source_type,source_form_id,source_entry_id),
			KEY event_status (event_id,status),
			KEY user_created (user_id,created_at),
			KEY report_status (report_type,status),
			KEY created_at (created_at)
		) {$charset_collate};

		CREATE TABLE {$media} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			report_id bigint(20) unsigned NOT NULL,
			event_id bigint(20) unsigned DEFAULT NULL,
			original_storage_key varchar(255) NOT NULL,
			original_filename varchar(255) NOT NULL,
			mime_type varchar(127) NOT NULL,
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			checksum char(64) NOT NULL,
			media_type varchar(32) NOT NULL,
			review_status varchar(32) NOT NULL DEFAULT 'received',
			sensitivity varchar(32) NOT NULL DEFAULT 'private',
			public_attachment_id bigint(20) unsigned DEFAULT NULL,
			redaction_required tinyint(1) NOT NULL DEFAULT 0,
			redaction_status varchar(32) DEFAULT NULL,
			exif_removed tinyint(1) NOT NULL DEFAULT 0,
			moderated_by bigint(20) unsigned DEFAULT NULL,
			moderated_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY report_status (report_id,review_status),
			KEY event_status (event_id,review_status),
			KEY public_attachment (public_attachment_id),
			KEY checksum (checksum)
		) {$charset_collate};

		CREATE TABLE {$sentinels} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			email varchar(191) NOT NULL,
			email_hash char(64) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			territory_term_id bigint(20) unsigned NOT NULL,
			category_term_id bigint(20) unsigned DEFAULT NULL,
			event_type_term_id bigint(20) unsigned DEFAULT NULL,
			criteria_json longtext NOT NULL,
			criteria_hash char(64) NOT NULL,
			verify_token_hash char(64) DEFAULT NULL,
			verification_expires_at datetime DEFAULT NULL,
			confirmed_at datetime DEFAULT NULL,
			disabled_at datetime DEFAULT NULL,
			deleted_at datetime DEFAULT NULL,
			last_notification_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY email_criteria (email_hash,criteria_hash),
			KEY status_territory (status,territory_term_id),
			KEY status_category (status,category_term_id),
			KEY status_event_type (status,event_type_term_id),
			KEY user_status (user_id,status),
			KEY verification_expiry (status,verification_expires_at)
		) {$charset_collate};

		CREATE TABLE {$sentinel_matches} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sentinel_id bigint(20) unsigned NOT NULL,
			event_id bigint(20) unsigned NOT NULL,
			match_status varchar(32) NOT NULL DEFAULT 'matched',
			notification_status varchar(32) NOT NULL DEFAULT 'queued',
			notification_attempts int(10) unsigned NOT NULL DEFAULT 0,
			matched_at datetime NOT NULL,
			last_attempt_at datetime DEFAULT NULL,
			next_retry_at datetime DEFAULT NULL,
			sent_at datetime DEFAULT NULL,
			last_error_code varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY sentinel_event (sentinel_id,event_id),
			KEY event_id (event_id),
			KEY sentinel_status (sentinel_id,notification_status),
			KEY notification_retry (notification_status,next_retry_at)
		) {$charset_collate};

		CREATE TABLE {$contributions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			event_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			email varchar(191) NOT NULL,
			email_hash char(64) NOT NULL,
			email_verified_at datetime DEFAULT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending_verification',
			contribution_type varchar(32) NOT NULL,
			public_identity_mode varchar(32) NOT NULL DEFAULT 'public_anonymous',
			public_display_name varchar(191) DEFAULT NULL,
			visibility_requested varchar(32) NOT NULL DEFAULT 'public',
			visibility_decided varchar(32) DEFAULT NULL,
			content_original longtext NOT NULL,
			observed_at datetime DEFAULT NULL,
			observed_at_precision varchar(32) DEFAULT NULL,
			exact_location_text text DEFAULT NULL,
			exact_lat decimal(10,7) DEFAULT NULL,
			exact_lng decimal(10,7) DEFAULT NULL,
			direction varchar(191) DEFAULT NULL,
			additional_details longtext DEFAULT NULL,
			contact_allowed tinyint(1) NOT NULL DEFAULT 0,
			consent_privacy tinyint(1) NOT NULL DEFAULT 0,
			consent_version varchar(64) DEFAULT NULL,
			consented_at datetime DEFAULT NULL,
			verify_token_hash char(64) DEFAULT NULL,
			verification_expires_at datetime DEFAULT NULL,
			payload_hash char(64) NOT NULL,
			ip_hash char(64) DEFAULT NULL,
			source varchar(32) NOT NULL DEFAULT 'event_detail',
			public_projection_id bigint(20) unsigned DEFAULT NULL,
			moderated_by bigint(20) unsigned DEFAULT NULL,
			moderated_at datetime DEFAULT NULL,
			moderation_reason text DEFAULT NULL,
			moderated_draft longtext DEFAULT NULL,
			recipient_text longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			KEY event_status (event_id,status),
			KEY email_event (email_hash,event_id),
			KEY payload_duplicate (event_id,email_hash,payload_hash),
			KEY verification_expiry (status,verification_expires_at),
			KEY created_at (created_at)
		) {$charset_collate};

		CREATE TABLE {$contribution_media} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contribution_id bigint(20) unsigned NOT NULL,
			event_id bigint(20) unsigned NOT NULL,
			original_storage_key varchar(255) NOT NULL,
			original_filename varchar(255) NOT NULL,
			mime_type varchar(127) NOT NULL,
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			checksum char(64) NOT NULL,
			media_type varchar(32) NOT NULL DEFAULT 'image',
			review_status varchar(32) NOT NULL DEFAULT 'received',
			sensitivity varchar(32) NOT NULL DEFAULT 'private',
			public_attachment_id bigint(20) unsigned DEFAULT NULL,
			redaction_required tinyint(1) NOT NULL DEFAULT 0,
			redaction_status varchar(32) DEFAULT NULL,
			exif_removed tinyint(1) NOT NULL DEFAULT 0,
			moderated_by bigint(20) unsigned DEFAULT NULL,
			moderated_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY contribution_status (contribution_id,review_status),
			KEY event_status (event_id,review_status),
			KEY public_attachment (public_attachment_id),
			KEY checksum (checksum)
		) {$charset_collate};

		CREATE TABLE {$contribution_notifications} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contribution_id bigint(20) unsigned NOT NULL,
			event_id bigint(20) unsigned NOT NULL,
			notification_kind varchar(32) NOT NULL,
			recipient_hash char(64) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'queued',
			notification_attempts int(10) unsigned NOT NULL DEFAULT 0,
			last_attempt_at datetime DEFAULT NULL,
			next_retry_at datetime DEFAULT NULL,
			sent_at datetime DEFAULT NULL,
			last_error_code varchar(64) DEFAULT NULL,
			access_public_id char(36) DEFAULT NULL,
			access_token_hash char(64) DEFAULT NULL,
			access_expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY contribution_id (contribution_id),
			UNIQUE KEY access_public_id (access_public_id),
			KEY status_retry (status,next_retry_at),
			KEY event_id (event_id)
		) {$charset_collate};

		CREATE TABLE {$audit} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_user_id bigint(20) unsigned DEFAULT NULL,
			object_type varchar(32) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			action varchar(64) NOT NULL,
			old_value longtext DEFAULT NULL,
			new_value longtext DEFAULT NULL,
			reason text DEFAULT NULL,
			context varchar(32) NOT NULL DEFAULT 'system',
			request_id varchar(64) DEFAULT NULL,
			ip_hash char(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY object_history (object_type,object_id,created_at),
			KEY actor_created (actor_user_id,created_at),
			KEY action_created (action,created_at),
			KEY request_id (request_id)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::OPTION_NAME, self::SCHEMA_VERSION, false );
	}
}
