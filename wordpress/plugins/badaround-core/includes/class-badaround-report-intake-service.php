<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical report -> BadAround domain intake.
 *
 * No WPForms field IDs or DOM concepts are accepted here.
 */
class BadAround_Report_Intake_Service {
	private $repository;
	private $validator;
	private $taxonomy_map;
	private $territory_resolver;
	private $publication_service;

	public function __construct( $repository = null, $validator = null, $taxonomy_map = null, $territory_resolver = null, $publication_service = null ) {
		$this->repository          = $repository ?: new BadAround_Report_Repository();
		$this->validator           = $validator ?: new BadAround_Report_Validator();
		$this->taxonomy_map        = $taxonomy_map ?: new BadAround_Event_Taxonomy_Map();
		$this->territory_resolver  = $territory_resolver ?: new BadAround_Territory_Resolver();
		$this->publication_service = $publication_service ?: new BadAround_Publication_Service();
	}

	public function ingest( $canonical_report, $source = array() ) {
		$request_id = wp_generate_uuid4();
		$validated  = $this->validator->validate_and_normalize( $canonical_report );
		if ( is_wp_error( $validated ) ) {
			BadAround_Audit_Log::technical_error( 'report', 0, 'intake_validation_failed', $validated->get_error_code(), $request_id );
			return $validated;
		}
		$canonical_report = $validated;

		$source = $this->normalize_source( $source, $canonical_report );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$locked = $this->acquire_source_lock( $source );
		if ( ! $locked ) {
			BadAround_Audit_Log::technical_error( 'report', 0, 'intake_lock_failed', 'source_lock_timeout', $request_id );
			return new WP_Error( 'ba_report_source_lock_timeout', __( 'La segnalazione è già in elaborazione. Riprova.', 'badaround-core' ) );
		}

		try {
			$report_id = $this->create_source_record( $source );
			if ( is_wp_error( $report_id ) ) {
				BadAround_Audit_Log::technical_error( 'report', 0, 'intake_record_failed', $report_id->get_error_code(), $request_id );
				return $report_id;
			}
			$report_id = absint( $report_id );
			if ( ! $report_id ) {
				BadAround_Audit_Log::technical_error( 'report', 0, 'intake_record_failed', 'report_insert_failed', $request_id );
				return new WP_Error( 'ba_report_insert_failed', __( 'Impossibile creare la segnalazione.', 'badaround-core' ) );
			}

			$existing_event_id = $this->repository->event_id_for_report( $report_id );
			if ( $existing_event_id ) {
				BadAround_Audit_Log::record( 'report', $report_id, 'intake_duplicate_ignored', $source['type'], null, $request_id );
				return array(
					'report_id' => $report_id,
					'event_id'  => absint( $existing_event_id ),
					'duplicate' => true,
				);
			}

			$content_payload = isset( $source['legacy_payload'] ) && is_array( $source['legacy_payload'] )
				? $source['legacy_payload']
				: null;

			if ( ! $this->repository->persist_canonical_private_data( $report_id, $canonical_report, $content_payload ) ) {
				BadAround_Audit_Log::technical_error( 'report', $report_id, 'private_persistence_failed', 'report_update_failed', $request_id );
				return new WP_Error( 'ba_report_private_persistence_failed', __( 'Impossibile salvare i dati riservati della segnalazione.', 'badaround-core' ) );
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => BadAround_Event_Post_Type::POST_TYPE,
					'post_status'  => 'pending',
					'post_title'   => sprintf( __( 'Segnalazione da moderare #%d', 'badaround-core' ), $report_id ),
					'post_content' => '',
					'post_excerpt' => '',
					'meta_input'   => array_merge(
						$this->source_post_meta( $source, $report_id ),
						$this->event_meta( $canonical_report )
					),
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				BadAround_Audit_Log::technical_error( 'report', $report_id, 'event_creation_failed', $post_id->get_error_code(), $request_id );
				return $post_id;
			}
			$post_id = absint( $post_id );

			$taxonomy = $this->taxonomy_map->assign( $post_id, $canonical_report );
			if ( is_wp_error( $taxonomy ) ) {
				BadAround_Audit_Log::technical_error( 'event', $post_id, 'event_type_mapping_failed', $taxonomy->get_error_code(), $request_id );
			}

			$location     = isset( $canonical_report['location'] ) ? $canonical_report['location'] : array();
			$territory_id = $this->territory_resolver->resolve(
				isset( $location['area_label'] ) ? $location['area_label'] : '',
				isset( $location['exact_address'] ) ? $location['exact_address'] : ''
			);
			if ( $territory_id ) {
				wp_set_object_terms( $post_id, array( $territory_id ), BadAround_Event_Post_Type::TERRITORY_TAX, false );
			} else {
				BadAround_Audit_Log::technical_error( 'event', $post_id, 'territory_mapping_failed', 'canonical_territory_unresolved', $request_id );
			}

			$projection = $this->publication_service->prepare_public_projection( $post_id );
			if ( is_wp_error( $projection ) ) {
				BadAround_Audit_Log::technical_error( 'event', $post_id, 'public_projection_prepare_failed', $projection->get_error_code(), $request_id );
			}

			$this->repository->link_event( $report_id, $post_id );

			BadAround_Audit_Log::record( 'report', $report_id, 'submission_normalized', $source['type'], null, $request_id );
			BadAround_Audit_Log::record( 'event', $post_id, 'event_created_pending_moderation', $source['type'], null, $request_id );

			return array(
				'report_id' => $report_id,
				'event_id'  => $post_id,
				'duplicate' => false,
			);
		} finally {
			$this->release_source_lock( $source );
		}
	}

	public function event_meta( $report ) {
		$location = isset( $report['location'] ) ? $report['location'] : array();
		$time     = isset( $report['time'] ) ? $report['time'] : array();
		$vehicle  = isset( $report['vehicle'] ) ? $report['vehicle'] : array();
		$reward   = isset( $report['reward'] ) ? $report['reward'] : array();
		$category = isset( $report['event']['category'] ) ? $report['event']['category'] : '';

		$meta = array(
			'_ba_moderation_status'         => 'new',
			'_ba_event_status'              => 'open',
			'_ba_time_precision'            => $this->time_precision_storage_code( isset( $time['mode'] ) ? $time['mode'] : '' ),
			'_ba_public_location_precision' => $this->location_precision_storage_code( isset( $location['public_precision'] ) ? $location['public_precision'] : '' ),
			'_ba_public_place_name'         => isset( $location['area_label'] ) ? sanitize_text_field( $location['area_label'] ) : '',
			'_ba_vehicle_involved'          => 'vehicle' === $category,
			'_ba_vehicle_type'              => $this->vehicle_type_storage_code( isset( $vehicle['type'] ) ? $vehicle['type'] : '' ),
			'_ba_vehicle_make'              => isset( $vehicle['make'] ) ? sanitize_text_field( $vehicle['make'] ) : '',
			'_ba_vehicle_model'             => isset( $vehicle['model'] ) ? sanitize_text_field( $vehicle['model'] ) : '',
			'_ba_vehicle_color'             => $this->vehicle_color_storage_label( isset( $vehicle['color'] ) ? $vehicle['color'] : '' ),
			'_ba_reward_available'          => in_array( isset( $reward['status'] ) ? $reward['status'] : 'none', array( 'fixed', 'negotiable' ), true ),
			'_ba_reward_amount'             => isset( $reward['amount'] ) && is_numeric( $reward['amount'] ) ? (float) $reward['amount'] : null,
			'_ba_expires_at'                => isset( $reward['expires_on'] ) ? $this->storage_date( $reward['expires_on'] ) : '',
		);

		if ( ! empty( $time['date'] ) ) {
			$meta['_ba_occurred_date'] = $this->storage_date( $time['date'] );
		}
		if ( ! empty( $time['exact_time'] ) ) {
			$meta['_ba_occurred_time'] = sanitize_text_field( $time['exact_time'] );
		}

		return array_filter(
			$meta,
			static function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);
	}

	private function normalize_source( $source, $report ) {
		$source = is_array( $source ) ? $source : array();
		$type   = isset( $source['type'] ) ? sanitize_key( $source['type'] ) : 'native';

		if ( 'wpforms' === $type ) {
			$form_id  = isset( $source['form_id'] ) ? absint( $source['form_id'] ) : 0;
			$entry_id = isset( $source['entry_id'] ) ? absint( $source['entry_id'] ) : 0;
			if ( ! $form_id || ! $entry_id ) {
				return new WP_Error( 'ba_report_invalid_source', __( 'Identità sorgente WPForms non valida.', 'badaround-core' ) );
			}
			$source['type']     = 'wpforms';
			$source['form_id']  = $form_id;
			$source['entry_id'] = $entry_id;
			return $source;
		}

		if ( 'native' !== $type ) {
			return new WP_Error( 'ba_report_invalid_source', __( 'Sorgente della segnalazione non supportata.', 'badaround-core' ) );
		}

		$submission_id = isset( $report['submission_id'] ) ? strtolower( trim( (string) $report['submission_id'] ) ) : '';
		$identity      = $this->repository->native_source_identity( $submission_id );
		if ( is_wp_error( $identity ) ) {
			return $identity;
		}

		return array(
			'type'          => 'native',
			'submission_id' => $submission_id,
			'form_id'       => $identity['form_id'],
			'entry_id'      => $identity['entry_id'],
		);
	}

	private function create_source_record( $source ) {
		if ( 'wpforms' === $source['type'] ) {
			return $this->repository->create_intake_record( $source['form_id'], $source['entry_id'] );
		}
		return $this->repository->create_native_intake_record( $source['submission_id'] );
	}

	private function acquire_source_lock( $source ) {
		if ( 'wpforms' === $source['type'] ) {
			return $this->repository->acquire_source_lock( $source['form_id'], $source['entry_id'] );
		}
		return $this->repository->acquire_native_lock( $source['submission_id'] );
	}

	private function release_source_lock( $source ) {
		if ( 'wpforms' === $source['type'] ) {
			$this->repository->release_source_lock( $source['form_id'], $source['entry_id'] );
			return;
		}
		$this->repository->release_native_lock( $source['submission_id'] );
	}

	private function source_post_meta( $source, $report_id ) {
		$meta = array(
			'_ba_primary_report_id' => absint( $report_id ),
			'_ba_source_type'       => $source['type'],
			'_ba_imported_at'       => current_time( 'mysql', true ),
		);

		if ( 'wpforms' === $source['type'] ) {
			$meta['_ba_source_form_id']  = absint( $source['form_id'] );
			$meta['_ba_source_entry_id'] = absint( $source['entry_id'] );
		} else {
			$meta['_ba_source_submission_id'] = sanitize_text_field( $source['submission_id'] );
		}
		return $meta;
	}

	private function time_precision_storage_code( $value ) {
		$map = array(
			'exact'       => 'f16-c4',
			'approximate' => 'f16-c5',
			'ongoing'     => 'f16-c6',
			'repeated'    => 'f16-c7',
			'unknown'     => 'f16-c8',
		);
		return isset( $map[ $value ] ) ? $map[ $value ] : '';
	}

	private function location_precision_storage_code( $value ) {
		$map = array(
			'point'        => 'f32-c4',
			'street'       => 'f32-c5',
			'area'         => 'f32-c6',
			'municipality' => 'f32-c7',
		);
		return isset( $map[ $value ] ) ? $map[ $value ] : '';
	}

	private function vehicle_type_storage_code( $value ) {
		$map = array(
			'car'               => 'f38-c9',
			'motorcycle'        => 'f38-c10',
			'scooter'           => 'f38-c11',
			'van'               => 'f38-c12',
			'truck'             => 'f38-c13',
			'camper'            => 'f38-c14',
			'bus'               => 'f38-c15',
			'bicycle'           => 'f38-c16',
			'scooter_device'    => 'f38-c17',
			'agricultural_work' => 'f38-c18',
			'other'             => 'f38-c19',
		);
		return isset( $map[ $value ] ) ? $map[ $value ] : '';
	}

	private function vehicle_color_storage_label( $value ) {
		$map = array(
			'white'      => 'Bianco',
			'black'      => 'Nero',
			'gray'       => 'Grigio',
			'silver'     => 'Argento',
			'blue'       => 'Blu',
			'light_blue' => 'Azzurro',
			'red'        => 'Rosso',
			'green'      => 'Verde',
			'yellow'     => 'Giallo',
			'orange'     => 'Arancione',
			'brown'      => 'Marrone',
			'beige'      => 'Beige',
			'purple'     => 'Viola',
			'pink'       => 'Rosa',
			'multicolor' => 'Multicolore',
			'other'      => 'Altro colore',
			'unknown'    => 'Non lo so',
		);
		return isset( $map[ $value ] ) ? $map[ $value ] : '';
	}

	private function storage_date( $value ) {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $value );
		return $date ? $date->format( 'd/m/Y' ) : sanitize_text_field( $value );
	}
}
