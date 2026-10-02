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
					if ( ! empty( $raw_value['file'] ) && is_string( $raw_value['file'] ) ) {
						$candidates[] = $raw_value['file'];
					} elseif ( ! empty( $raw_value['value'] ) && is_string( $raw_value['value'] ) ) {
						$candidates[] = $raw_value['value'];
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
