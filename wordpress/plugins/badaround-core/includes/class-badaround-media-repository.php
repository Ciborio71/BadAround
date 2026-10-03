<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores original report media outside public WordPress paths. */
class BadAround_Media_Repository {
	public function ingest_wpforms_files( $report_id, $event_id, $field ) {
		$files = $this->extract_file_references( $field );
		if ( ! $files ) {
			return array();
		}

		$base_dir = $this->secure_base_dir();
		if ( ! $base_dir ) {
			return new WP_Error( 'ba_secure_media_unavailable', 'Secure media storage is not configured outside the public WordPress tree.' );
		}

		$report_dir = trailingslashit( $base_dir ) . 'report-' . absint( $report_id );
		if ( ! wp_mkdir_p( $report_dir ) ) {
			return new WP_Error( 'ba_secure_media_mkdir_failed', 'Unable to create secure report media directory.' );
		}

		$stored = array();
		foreach ( $files as $reference ) {
			$source = $this->local_path_from_reference( $reference );
			if ( ! $source || ! is_file( $source ) || ! is_readable( $source ) ) {
				continue;
			}

			$filename = sanitize_file_name( basename( $source ) );
			$target   = trailingslashit( $report_dir ) . wp_generate_uuid4() . '-' . $filename;
			if ( ! @rename( $source, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( ! copy( $source, $target ) || ! unlink( $source ) ) {
					continue;
				}
			}

			$checksum = hash_file( 'sha256', $target );
			$mime     = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $target ) : '';
			$mime     = $mime ? $mime : 'application/octet-stream';
			$size     = filesize( $target );
			$row_id   = $this->insert_media_row( $report_id, $event_id, basename( $target ), $filename, $mime, $size, $checksum );
			if ( $row_id ) {
				$stored[] = $row_id;
			}
		}
		return $stored;
	}

	public function private_file_for_media_id( $media_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, report_id, event_id, original_storage_key, original_filename, mime_type, file_size, checksum, media_type, review_status, sensitivity, public_attachment_id FROM {$wpdb->prefix}ba_report_media WHERE id = %d AND deleted_at IS NULL LIMIT 1",
				absint( $media_id )
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'ba_media_not_found', __( 'Media non trovato.', 'badaround-core' ) );
		}

		$base = $this->secure_base_dir();
		if ( ! $base ) {
			return new WP_Error( 'ba_secure_media_unavailable', __( 'Archivio media privato non disponibile.', 'badaround-core' ) );
		}

		$path = wp_normalize_path( trailingslashit( $base ) . 'report-' . absint( $row->report_id ) . '/' . basename( $row->original_storage_key ) );
		$root = wp_normalize_path( trailingslashit( $base ) );
		if ( 0 !== strpos( $path, $root ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'ba_media_file_unavailable', __( 'File media privato non disponibile.', 'badaround-core' ) );
		}

		return array( 'row' => $row, 'path' => $path );
	}

	public function approve_for_publication( $media_id, $event_id ) {
		global $wpdb;
		$media_id = absint( $media_id );
		$event_id = absint( $event_id );
		if ( ! $media_id || ! $event_id ) {
			return new WP_Error( 'ba_public_media_invalid', __( 'Media non valido.', 'badaround-core' ) );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, event_id, media_type, review_status, sensitivity, public_attachment_id, redaction_required, redaction_status FROM {$wpdb->prefix}ba_report_media WHERE id = %d AND event_id = %d AND deleted_at IS NULL LIMIT 1",
				$media_id,
				$event_id
			)
		);
		if ( ! $row ) {
			return new WP_Error( 'ba_public_media_not_found', __( 'Media non trovato.', 'badaround-core' ) );
		}
		if ( 'private' !== $row->sensitivity ) {
			return new WP_Error( 'ba_public_media_not_private', __( 'Il media originale deve rimanere privato.', 'badaround-core' ) );
		}
		if ( 'image' !== $row->media_type ) {
			return new WP_Error( 'ba_public_media_unsupported', __( 'Per B3 è supportata la pubblicazione delle immagini.', 'badaround-core' ) );
		}
		if ( ! empty( $row->redaction_required ) && 'completed' !== $row->redaction_status ) {
			return new WP_Error( 'ba_public_media_redaction_required', __( 'Il media richiede una redazione prima della pubblicazione.', 'badaround-core' ) );
		}
		if ( in_array( $row->review_status, array( 'approved_public', 'published' ), true ) ) {
			return true;
		}
		$updated = $wpdb->update(
			$wpdb->prefix . 'ba_report_media',
			array(
				'review_status' => 'approved_public',
				'moderated_by'  => get_current_user_id() ?: null,
				'moderated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => $media_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		return false === $updated ? new WP_Error( 'ba_public_media_approval_failed', __( 'Impossibile approvare il media per la pubblicazione.', 'badaround-core' ) ) : true;
	}

	public function materialize_approved_public_media_for_event( $event_id ) {
		global $wpdb;
		$event_id = absint( $event_id );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, public_attachment_id FROM {$wpdb->prefix}ba_report_media WHERE event_id = %d AND deleted_at IS NULL AND review_status IN ('approved_public','published') ORDER BY id ASC",
				$event_id
			)
		);
		$attachments = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row->public_attachment_id ) && get_post( (int) $row->public_attachment_id ) ) {
				$attachments[] = (int) $row->public_attachment_id;
				continue;
			}
			$attachment_id = $this->materialize_public_copy( (int) $row->id, $event_id );
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}
			$attachments[] = (int) $attachment_id;
		}
		return $attachments;
	}

	private function materialize_public_copy( $media_id, $event_id ) {
		global $wpdb;
		$file = $this->private_file_for_media_id( $media_id );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$row = $file['row'];
		if ( (int) $row->event_id !== absint( $event_id ) || 'private' !== $row->sensitivity || 'image' !== $row->media_type ) {
			return new WP_Error( 'ba_public_media_invalid_source', __( 'Sorgente media non valida.', 'badaround-core' ) );
		}
		if ( ! in_array( $row->review_status, array( 'approved_public', 'published' ), true ) ) {
			return new WP_Error( 'ba_public_media_not_approved', __( 'Il media non è stato approvato per la pubblicazione.', 'badaround-core' ) );
		}

		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'ba_public_media_upload_dir', sanitize_text_field( $uploads['error'] ) );
		}
		$subdir = trailingslashit( $uploads['basedir'] ) . 'badaround-public/' . gmdate( 'Y/m' );
		if ( ! wp_mkdir_p( $subdir ) ) {
			return new WP_Error( 'ba_public_media_mkdir_failed', __( 'Impossibile creare la cartella media pubblica.', 'badaround-core' ) );
		}

		$ext = strtolower( pathinfo( $row->original_filename, PATHINFO_EXTENSION ) );
		$ext = in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ? $ext : 'jpg';
		$filename = wp_unique_filename( $subdir, 'event-' . absint( $event_id ) . '-' . wp_generate_uuid4() . '.' . $ext );
		$target = trailingslashit( $subdir ) . $filename;
		$editor = wp_get_image_editor( $file['path'] );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}
		$saved = $editor->save( $target );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_file( $saved['path'] ) ) {
			return is_wp_error( $saved ) ? $saved : new WP_Error( 'ba_public_media_save_failed', __( 'Impossibile creare la copia media pubblica.', 'badaround-core' ) );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => sanitize_mime_type( $saved['mime-type'] ?? $row->mime_type ),
				'post_title'     => sanitize_text_field( get_the_title( $event_id ) ),
				'post_status'    => 'inherit',
			),
			$saved['path'],
			$event_id,
			true
		);
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $saved['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $attachment_id;
		}
		$metadata = wp_generate_attachment_metadata( $attachment_id, $saved['path'] );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'ba_report_media',
			array(
				'public_attachment_id' => $attachment_id,
				'review_status'        => 'published',
				'exif_removed'         => 1,
				'moderated_by'         => get_current_user_id() ?: null,
				'moderated_at'         => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $media_id ) ),
			array( '%d', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			wp_delete_attachment( $attachment_id, true );
			return new WP_Error( 'ba_public_media_link_failed', __( 'Impossibile collegare la copia media pubblica.', 'badaround-core' ) );
		}

		BadAround_Audit_Log::record( 'event', $event_id, 'public_media_materialized', 'publication' );
		return $attachment_id;
	}

	private function secure_base_dir() {
		$dir = defined( 'BADAROUND_PRIVATE_MEDIA_PATH' )
			? BADAROUND_PRIVATE_MEDIA_PATH
			: dirname( untrailingslashit( ABSPATH ) ) . '/badaround-private-media';
		$dir = apply_filters( 'badaround_private_media_path', $dir );
		if ( ! is_string( $dir ) || '' === trim( $dir ) ) {
			return '';
		}
		$normalized = wp_normalize_path( untrailingslashit( $dir ) );
		$public_roots = array_filter(
			array(
				defined( 'ABSPATH' ) ? wp_normalize_path( untrailingslashit( ABSPATH ) ) : '',
				defined( 'WP_CONTENT_DIR' ) ? wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) ) : '',
			)
		);
		foreach ( $public_roots as $root ) {
			if ( 0 === strpos( $normalized . '/', $root . '/' ) ) {
				return '';
			}
		}
		return $normalized;
	}

	private function extract_file_references( $field ) {
		if ( ! is_array( $field ) ) {
			return array();
		}

		$candidates = array();

		if ( ! empty( $field['value_raw'] ) ) {
			$raw_values = is_array( $field['value_raw'] ) ? $field['value_raw'] : array( $field['value_raw'] );
			foreach ( $raw_values as $raw_value ) {
				if ( is_array( $raw_value ) ) {
					if ( ! empty( $raw_value['value'] ) && is_string( $raw_value['value'] ) ) {
						$candidates[] = $raw_value['value'];
					} elseif ( ! empty( $raw_value['file'] ) && is_string( $raw_value['file'] ) ) {
						$candidates[] = $raw_value['file'];
					}
				} elseif ( is_scalar( $raw_value ) ) {
					$candidates[] = (string) $raw_value;
				}
			}
		}

		if ( ! $candidates && ! empty( $field['value'] ) ) {
			$value = $field['value'];
			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					if ( is_scalar( $item ) ) {
						$candidates[] = (string) $item;
					}
				}
			} else {
				$candidates = preg_split( '/[\r\n,]+/', (string) $value );
			}
		}

		$candidates = array_map( 'trim', $candidates );
		return array_values( array_unique( array_filter( $candidates ) ) );
	}

	private function local_path_from_reference( $reference ) {
		$reference = (string) $reference;
		if ( is_file( $reference ) ) {
			return $reference;
		}
		$uploads = wp_upload_dir();
		if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) || 0 !== strpos( $reference, $uploads['baseurl'] ) ) {
			return '';
		}
		$relative = ltrim( substr( $reference, strlen( $uploads['baseurl'] ) ), '/' );
		$path = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . $relative );
		$base = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
		return 0 === strpos( $path, $base ) ? $path : '';
	}

	private function insert_media_row( $report_id, $event_id, $storage_key, $filename, $mime, $size, $checksum ) {
		global $wpdb;
		$result = $wpdb->insert(
			$wpdb->prefix . 'ba_report_media',
			array(
				'report_id'            => absint( $report_id ),
				'event_id'             => absint( $event_id ),
				'original_storage_key' => sanitize_text_field( $storage_key ),
				'original_filename'    => sanitize_file_name( $filename ),
				'mime_type'            => sanitize_mime_type( $mime ),
				'file_size'            => absint( $size ),
				'checksum'             => sanitize_text_field( $checksum ),
				'media_type'           => 0 === strpos( $mime, 'image/' ) ? 'image' : 'file',
				'review_status'        => 'received',
				'sensitivity'          => 'private',
				'created_at'           => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		return false !== $result ? (int) $wpdb->insert_id : 0;
	}
}
