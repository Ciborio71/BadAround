<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owns the versioned database schema for private and relational data. */
class BadAround_Installer {
	const SCHEMA_VERSION = '1.1.0';
	const OPTION_NAME    = 'ba_db_schema_version';

	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
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
