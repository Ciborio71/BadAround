<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Contribution_Media_Repository {
	const MAX_FILES = 5;
	const MAX_FILE_SIZE = 5242880; // 5 MB.

	public function ingest_rest_files( $contribution_id, $event_id, array $files ) {
		$contribution_id = absint( $contribution_id );
		$event_id = absint( $event_id );
		if ( ! $contribution_id || ! $event_id || empty( $files ) ) {
			return array();
		}

		$normalized = $this->normalize_files( $files );
		if ( count( $normalized ) > self::MAX_FILES ) {
			return new WP_Error( 'ba_contribution_media_too_many', __( 'Puoi allegare al massimo 5 immagini.', 'badaround-core' ), array( 'status' => 400 ) );
		}

		$base = $this->secure_base_dir();
		if ( ! $base ) {
			return new WP_Error( 'ba_contribution_media_storage_unavailable', __( 'Archivio media privato non disponibile.', 'badaround-core' ) );
		}

		$dir = trailingslashit( $base ) . 'contribution-' . $contribution_id;
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'ba_contribution_media_mkdir_failed', __( 'Impossibile creare l’archivio privato del contributo.', 'badaround-core' ) );
		}

		$ids = array();
		foreach ( $normalized as $file ) {
			$validation = $this->validate_uploaded_image( $file );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$filename = sanitize_file_name( $file['name'] );
			$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			$target = trailingslashit( $dir ) . wp_generate_uuid4() . '.' . $extension;

			if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
				return new WP_Error( 'ba_contribution_media_invalid_upload', __( 'Upload non valido.', 'badaround-core' ), array( 'status' => 400 ) );
			}
			if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
				return new WP_Error( 'ba_contribution_media_move_failed', __( 'Impossibile archiviare l’immagine.', 'badaround-core' ) );
			}

			$checksum = hash_file( 'sha256', $target );
			$id = $this->insert_row(
				$contribution_id,
				$event_id,
				basename( $target ),
				$filename,
				$validation['mime'],
				filesize( $target ),
				$checksum
			);
			if ( ! $id ) {
				@unlink( $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return new WP_Error( 'ba_contribution_media_db_failed', __( 'Impossibile registrare l’immagine.', 'badaround-core' ) );
			}
			$ids[] = $id;
			BadAround_Audit_Log::record( 'contribution', $contribution_id, 'contribution_media_received', 'contribution' );
		}
		return $ids;
	}

	public function list_for_contribution( $contribution_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ba_contribution_media WHERE contribution_id = %d AND deleted_at IS NULL ORDER BY id ASC",
				absint( $contribution_id )
			),
			ARRAY_A
		);
	}

	public function cleanup_contribution( $contribution_id ) {
		global $wpdb;
		$contribution_id = absint( $contribution_id );
		if ( ! $contribution_id ) { return; }

		$rows = $this->list_for_contribution( $contribution_id );
		$base = $this->secure_base_dir();
		foreach ( $rows as $row ) {
			if ( $base && ! empty( $row['original_storage_key'] ) ) {
				$path = wp_normalize_path( trailingslashit( $base ) . 'contribution-' . $contribution_id . '/' . basename( $row['original_storage_key'] ) );
				$root = wp_normalize_path( trailingslashit( $base ) );
				if ( 0 === strpos( $path, $root ) && is_file( $path ) ) {
					@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}
		}
		$wpdb->delete( $wpdb->prefix . 'ba_contribution_media', array( 'contribution_id' => $contribution_id ) );
		$dir = $base ? wp_normalize_path( trailingslashit( $base ) . 'contribution-' . $contribution_id ) : '';
		if ( $dir && is_dir( $dir ) ) {
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	public function has_pending_review( $contribution_id ) {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ba_contribution_media WHERE contribution_id = %d AND deleted_at IS NULL AND review_status = 'received'",
				absint( $contribution_id )
			)
		);
		return $count > 0;
	}

	public function private_file( $media_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ba_contribution_media WHERE id = %d AND deleted_at IS NULL LIMIT 1",
				absint( $media_id )
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return new WP_Error( 'ba_contribution_media_not_found', __( 'Media non trovato.', 'badaround-core' ) );
		}
		$base = $this->secure_base_dir();
		if ( ! $base ) {
			return new WP_Error( 'ba_contribution_media_storage_unavailable', __( 'Archivio media privato non disponibile.', 'badaround-core' ) );
		}
		$path = wp_normalize_path( trailingslashit( $base ) . 'contribution-' . absint( $row['contribution_id'] ) . '/' . basename( $row['original_storage_key'] ) );
		$root = wp_normalize_path( trailingslashit( $base ) );
		if ( 0 !== strpos( $path, $root ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'ba_contribution_media_file_unavailable', __( 'File privato non disponibile.', 'badaround-core' ) );
		}
		return array( 'row' => $row, 'path' => $path );
	}

	public function approve( $media_id, $contribution_id ) {
		global $wpdb;
		$row = $this->private_file( $media_id );
		if ( is_wp_error( $row ) ) { return $row; }
		$data = $row['row'];
		if ( (int) $data['contribution_id'] !== absint( $contribution_id ) ) {
			return new WP_Error( 'ba_contribution_media_mismatch', __( 'Media non associato al contributo.', 'badaround-core' ) );
		}
		if ( 'private' !== $data['sensitivity'] || 'image' !== $data['media_type'] ) {
			return new WP_Error( 'ba_contribution_media_not_publishable', __( 'Il media non è pubblicabile.', 'badaround-core' ) );
		}
		if ( ! empty( $data['redaction_required'] ) && 'completed' !== $data['redaction_status'] ) {
			return new WP_Error( 'ba_contribution_media_redaction_required', __( 'Il media richiede redazione prima della pubblicazione.', 'badaround-core' ) );
		}
		if ( in_array( $data['review_status'], array( 'approved_public', 'published' ), true ) ) {
			return true;
		}
		$updated = $wpdb->update(
			$wpdb->prefix . 'ba_contribution_media',
			array(
				'review_status' => 'approved_public',
				'moderated_by' => get_current_user_id() ?: null,
				'moderated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $media_id ) )
		);
		if ( false === $updated ) {
			return new WP_Error( 'ba_contribution_media_approve_failed', __( 'Impossibile approvare il media.', 'badaround-core' ) );
		}
		BadAround_Audit_Log::record( 'contribution', $contribution_id, 'contribution_media_approved', 'contribution' );
		return true;
	}

	public function reject( $media_id, $contribution_id ) {
		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'ba_contribution_media',
			array(
				'review_status' => 'rejected',
				'moderated_by' => get_current_user_id() ?: null,
				'moderated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $media_id ), 'contribution_id' => absint( $contribution_id ) )
		);
		if ( false === $updated ) {
			return new WP_Error( 'ba_contribution_media_reject_failed', __( 'Impossibile rifiutare il media.', 'badaround-core' ) );
		}
		BadAround_Audit_Log::record( 'contribution', $contribution_id, 'contribution_media_rejected', 'contribution' );
		return true;
	}

	public function materialize_approved( $contribution_id, $projection_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, public_attachment_id FROM {$wpdb->prefix}ba_contribution_media WHERE contribution_id = %d AND deleted_at IS NULL AND review_status IN ('approved_public','published') ORDER BY id ASC",
				absint( $contribution_id )
			),
			ARRAY_A
		);
		$attachments = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['public_attachment_id'] ) && get_post( absint( $row['public_attachment_id'] ) ) ) {
				$attachments[] = absint( $row['public_attachment_id'] );
				continue;
			}
			$attachment = $this->materialize_public_copy( absint( $row['id'] ), absint( $projection_id ) );
			if ( is_wp_error( $attachment ) ) { return $attachment; }
			$attachments[] = $attachment;
		}
		return $attachments;
	}

	private function materialize_public_copy( $media_id, $projection_id ) {
		global $wpdb;
		$file = $this->private_file( $media_id );
		if ( is_wp_error( $file ) ) { return $file; }
		$row = $file['row'];

		if ( 'private' !== $row['sensitivity'] || 'image' !== $row['media_type'] || ! in_array( $row['review_status'], array( 'approved_public', 'published' ), true ) ) {
			return new WP_Error( 'ba_contribution_media_invalid_public_source', __( 'Sorgente media non valida.', 'badaround-core' ) );
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'ba_contribution_media_upload_dir', sanitize_text_field( $uploads['error'] ) );
		}
		$dir = trailingslashit( $uploads['basedir'] ) . 'badaround-public/contributions/' . gmdate( 'Y/m' );
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'ba_contribution_media_public_mkdir', __( 'Impossibile creare la cartella pubblica.', 'badaround-core' ) );
		}

		$editor = wp_get_image_editor( $file['path'] );
		if ( is_wp_error( $editor ) ) { return $editor; }
		$filename = wp_unique_filename( $dir, 'contribution-' . absint( $row['contribution_id'] ) . '-' . wp_generate_uuid4() . '.jpg' );
		$target = trailingslashit( $dir ) . $filename;
		$saved = $editor->save( $target, 'image/jpeg' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_file( $saved['path'] ) ) {
			return is_wp_error( $saved ) ? $saved : new WP_Error( 'ba_contribution_media_public_save_failed', __( 'Impossibile creare il derivato pubblico.', 'badaround-core' ) );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title' => __( 'Contributo della community', 'badaround-core' ),
				'post_status' => 'inherit',
			),
			$saved['path'],
			$projection_id,
			true
		);
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $saved['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $attachment_id;
		}
		update_attached_file( $attachment_id, $saved['path'] );
		$metadata = wp_generate_attachment_metadata( $attachment_id, $saved['path'] );
		if ( is_array( $metadata ) ) { wp_update_attachment_metadata( $attachment_id, $metadata ); }

		$updated = $wpdb->update(
			$wpdb->prefix . 'ba_contribution_media',
			array(
				'public_attachment_id' => absint( $attachment_id ),
				'review_status' => 'published',
				'exif_removed' => 1,
				'moderated_by' => get_current_user_id() ?: null,
				'moderated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $media_id ) )
		);
		if ( false === $updated ) {
			wp_delete_attachment( $attachment_id, true );
			return new WP_Error( 'ba_contribution_media_public_link_failed', __( 'Impossibile collegare il derivato pubblico.', 'badaround-core' ) );
		}
		BadAround_Audit_Log::record( 'contribution', absint( $row['contribution_id'] ), 'contribution_media_materialized', 'contribution' );
		return absint( $attachment_id );
	}

	private function validate_uploaded_image( array $file ) {
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
			return new WP_Error( 'ba_contribution_media_upload_error', __( 'Upload immagine non valido.', 'badaround-core' ), array( 'status' => 400 ) );
		}
		if ( empty( $file['size'] ) || (int) $file['size'] > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'ba_contribution_media_size', __( 'Ogni immagine deve essere inferiore a 5 MB.', 'badaround-core' ), array( 'status' => 400 ) );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array(
			'jpg|jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
		) );
		$mime = ! empty( $check['type'] ) ? sanitize_mime_type( $check['type'] ) : '';
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return new WP_Error( 'ba_contribution_media_type', __( 'Sono consentite solo immagini JPG, PNG o WebP.', 'badaround-core' ), array( 'status' => 400 ) );
		}
		return array( 'mime' => $mime );
	}

	private function normalize_files( array $files ) {
		$normalized = array();
		foreach ( $files as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['name'] ) ) { continue; }
			if ( is_array( $field['name'] ) ) {
				foreach ( array_keys( $field['name'] ) as $i ) {
					$normalized[] = array(
						'name' => $field['name'][$i] ?? '',
						'type' => $field['type'][$i] ?? '',
						'tmp_name' => $field['tmp_name'][$i] ?? '',
						'error' => $field['error'][$i] ?? UPLOAD_ERR_NO_FILE,
						'size' => $field['size'][$i] ?? 0,
					);
				}
			} else {
				$normalized[] = $field;
			}
		}
		return array_values( array_filter( $normalized, static function( $file ) {
			return ! empty( $file['name'] ) && UPLOAD_ERR_NO_FILE !== (int) $file['error'];
		} ) );
	}

	private function insert_row( $contribution_id, $event_id, $storage_key, $filename, $mime, $size, $checksum ) {
		global $wpdb;
		$result = $wpdb->insert(
			$wpdb->prefix . 'ba_contribution_media',
			array(
				'contribution_id' => absint( $contribution_id ),
				'event_id' => absint( $event_id ),
				'original_storage_key' => sanitize_text_field( $storage_key ),
				'original_filename' => sanitize_file_name( $filename ),
				'mime_type' => sanitize_mime_type( $mime ),
				'file_size' => absint( $size ),
				'checksum' => sanitize_text_field( $checksum ),
				'media_type' => 'image',
				'review_status' => 'received',
				'sensitivity' => 'private',
				'created_at' => current_time( 'mysql', true ),
			)
		);
		return false !== $result ? (int) $wpdb->insert_id : 0;
	}

	private function secure_base_dir() {
		$dir = defined( 'BADAROUND_PRIVATE_MEDIA_PATH' )
			? BADAROUND_PRIVATE_MEDIA_PATH
			: dirname( untrailingslashit( ABSPATH ) ) . '/badaround-private-media';
		$dir = apply_filters( 'badaround_private_media_path', $dir );
		if ( ! is_string( $dir ) || '' === trim( $dir ) ) { return ''; }

		$normalized = wp_normalize_path( untrailingslashit( $dir ) );
		$public_roots = array_filter( array(
			defined( 'ABSPATH' ) ? wp_normalize_path( untrailingslashit( ABSPATH ) ) : '',
			defined( 'WP_CONTENT_DIR' ) ? wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) ) : '',
		) );
		foreach ( $public_roots as $root ) {
			if ( 0 === strpos( $normalized . '/', $root . '/' ) ) { return ''; }
		}
		return $normalized;
	}
}
