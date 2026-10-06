<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Neutral validated-report -> Golden Path persistence boundary.
 *
 * Accepts canonical semantic data only. No WPForms field/choice identities.
 */
class BadAround_Report_Persistence_Service {
	private $repository;
	private $taxonomy_map;
	private $territory_resolver;
	private $media_repository;
	private $publication_service;

	public function __construct( $repository = null, $taxonomy_map = null, $territory_resolver = null, $media_repository = null, $publication_service = null ) {
		$this->repository          = $repository ?: new BadAround_Report_Repository();
		$this->taxonomy_map        = $taxonomy_map ?: new BadAround_Event_Taxonomy_Map();
		$this->territory_resolver  = $territory_resolver ?: new BadAround_Territory_Resolver();
		$this->media_repository    = $media_repository ?: new BadAround_Media_Repository();
		$this->publication_service = $publication_service ?: new BadAround_Publication_Service();
	}

	public function persist( $report_id, $canonical_report, $source = array(), $options = array() ) {
		$report_id = absint( $report_id );
		$source    = is_array( $source ) ? $source : array();
		$options   = is_array( $options ) ? $options : array();

		if ( ! $report_id || ! is_array( $canonical_report ) ) {
			return new WP_Error( 'ba_persistence_invalid_input', __( 'Report validato non disponibile.', 'badaround-core' ) );
		}

		$existing_event = $this->repository->event_id_for_report( $report_id );
		if ( $existing_event ) {
			return array( 'report_id' => $report_id, 'event_id' => absint( $existing_event ), 'duplicate' => true, 'recovered' => false );
		}

		$recovered_event = $this->find_recoverable_event( $report_id );
		if ( $recovered_event ) {
			if ( ! $this->repository->link_event( $report_id, $recovered_event ) ) {
				return new WP_Error( 'ba_persistence_recovery_link_failed', __( 'Impossibile ripristinare il collegamento report-evento.', 'badaround-core' ) );
			}
			BadAround_Audit_Log::record( 'report', $report_id, 'persistence_completed', 'native', 'recovered_existing_event' );
			return array( 'report_id' => $report_id, 'event_id' => $recovered_event, 'duplicate' => true, 'recovered' => true );
		}

		$request_id = wp_generate_uuid4();
		BadAround_Audit_Log::record( 'report', $report_id, 'native_report_persist_started', isset( $source['type'] ) ? $source['type'] : 'native', null, $request_id );
		BadAround_Audit_Log::record( 'report', $report_id, 'native_report_created', isset( $source['type'] ) ? $source['type'] : 'native', null, $request_id );
		BadAround_Audit_Log::record( 'report', $report_id, 'event_creation_started', isset( $source['type'] ) ? $source['type'] : 'native', null, $request_id );

		$event_id = wp_insert_post(
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

		if ( is_wp_error( $event_id ) ) {
			return $this->fail( $report_id, 0, 'event_creation_failed', $event_id->get_error_code(), $request_id, $event_id );
		}
		$event_id = absint( $event_id );
		BadAround_Audit_Log::record( 'event', $event_id, 'event_created', isset( $source['type'] ) ? $source['type'] : 'native', null, $request_id );

		$taxonomy = $this->taxonomy_map->assign( $event_id, $canonical_report );
		if ( is_wp_error( $taxonomy ) ) {
			return $this->rollback_event( $report_id, $event_id, 'taxonomy_assignment_failed', $taxonomy->get_error_code(), $request_id, $taxonomy );
		}
		BadAround_Audit_Log::record( 'event', $event_id, 'taxonomy_assigned', isset( $source['type'] ) ? $source['type'] : 'native', null, $request_id );

		$location = isset( $canonical_report['location'] ) && is_array( $canonical_report['location'] ) ? $canonical_report['location'] : array();
		$territory_id = $this->resolve_territory( $location );
		if ( ! $territory_id ) {
			return $this->rollback_event(
				$report_id,
				$event_id,
				'territory_mapping_failed',
				'canonical_territory_unresolved',
				$request_id,
				new WP_Error( 'ba_persistence_territory_unresolved', __( 'Territorio canonical non risolvibile.', 'badaround-core' ) )
			);
		}
		$territory_result = wp_set_object_terms( $event_id, array( $territory_id ), BadAround_Event_Post_Type::TERRITORY_TAX, false );
		if ( is_wp_error( $territory_result ) ) {
			return $this->rollback_event( $report_id, $event_id, 'territory_assignment_failed', $territory_result->get_error_code(), $request_id, $territory_result );
		}

		$media_items = isset( $canonical_report['media']['items'] ) && is_array( $canonical_report['media']['items'] ) ? $canonical_report['media']['items'] : array();
		if ( $media_items && method_exists( $this->media_repository, 'link_validated_private_media' ) ) {
			$media_result = $this->media_repository->link_validated_private_media( $report_id, $event_id, $media_items );
			if ( is_wp_error( $media_result ) ) {
				return $this->rollback_event( $report_id, $event_id, 'media_link_failed', $media_result->get_error_code(), $request_id, $media_result, true );
			}
		}

		if ( ! $this->repository->link_event( $report_id, $event_id ) ) {
			return $this->rollback_event( $report_id, $event_id, 'report_event_link_failed', 'report_update_failed', $request_id, new WP_Error( 'ba_persistence_report_link_failed', __( 'Impossibile collegare report ed evento.', 'badaround-core' ) ), true );
		}

		if ( ! empty( $options['prepare_public_projection'] ) ) {
			$projection = $this->publication_service->prepare_public_projection( $event_id );
			if ( is_wp_error( $projection ) ) {
				// Legacy compatibility: preserve the event/report link and report the projection issue for moderation.
				BadAround_Audit_Log::technical_error( 'event', $event_id, 'public_projection_prepare_failed', $projection->get_error_code(), $request_id );
			}
		}

		BadAround_Audit_Log::record( 'report', $report_id, 'persistence_completed', isset( $source['type'] ) ? $source['type'] : 'native', null, $request_id );
		return array( 'report_id' => $report_id, 'event_id' => $event_id, 'duplicate' => false, 'recovered' => false );
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
		if ( ! empty( $time['date'] ) ) $meta['_ba_occurred_date'] = $this->storage_date( $time['date'] );
		if ( ! empty( $time['exact_time'] ) ) $meta['_ba_occurred_time'] = sanitize_text_field( $time['exact_time'] );

		return array_filter( $meta, static function ( $value ) { return null !== $value && '' !== $value; } );
	}

	private function source_post_meta( $source, $report_id ) {
		$type = isset( $source['type'] ) ? sanitize_key( $source['type'] ) : 'native';
		$meta = array(
			'_ba_primary_report_id' => absint( $report_id ),
			'_ba_source_type'       => $type,
			'_ba_imported_at'       => current_time( 'mysql', true ),
		);
		if ( 'wpforms' === $type ) {
			$meta['_ba_source_form_id']  = isset( $source['form_id'] ) ? absint( $source['form_id'] ) : 0;
			$meta['_ba_source_entry_id'] = isset( $source['entry_id'] ) ? absint( $source['entry_id'] ) : 0;
		} elseif ( ! empty( $source['submission_id'] ) ) {
			$meta['_ba_source_submission_id'] = sanitize_text_field( $source['submission_id'] );
		}
		return $meta;
	}

	private function resolve_territory( $location ) {
		foreach ( array( 'locality', 'municipality', 'province', 'region', 'area_label' ) as $key ) {
			if ( empty( $location[ $key ] ) ) continue;
			$resolved = $this->territory_resolver->resolve(
				$location[ $key ],
				isset( $location['exact_address'] ) ? $location['exact_address'] : ''
			);
			if ( $resolved ) return absint( $resolved );
		}
		return 0;
	}

	private function find_recoverable_event( $report_id ) {
		$posts = get_posts(
			array(
				'post_type'      => BadAround_Event_Post_Type::POST_TYPE,
				'post_status'    => array( 'pending', 'draft', 'publish', 'private' ),
				'posts_per_page' => 2,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => '_ba_primary_report_id', 'value' => absint( $report_id ), 'compare' => '=' ),
				),
			)
		);
		return 1 === count( $posts ) ? absint( $posts[0] ) : 0;
	}

	private function rollback_event( $report_id, $event_id, $action, $code, $request_id, $error, $unlink_media = false ) {
		if ( $unlink_media && method_exists( $this->media_repository, 'unlink_event' ) ) {
			$this->media_repository->unlink_event( $report_id, $event_id );
		}
		wp_delete_post( $event_id, true );
		return $this->fail( $report_id, $event_id, $action, $code, $request_id, $error );
	}

	private function fail( $report_id, $event_id, $action, $code, $request_id, $error ) {
		BadAround_Audit_Log::technical_error( $event_id ? 'event' : 'report', $event_id ?: $report_id, $action, $code, $request_id );
		BadAround_Audit_Log::record( 'report', $report_id, 'persistence_failed', 'native', $code, $request_id );
		return $error;
	}

	private function time_precision_storage_code( $value ) {
		$map=array('exact'=>'f16-c4','approximate'=>'f16-c5','ongoing'=>'f16-c6','repeated'=>'f16-c7','unknown'=>'f16-c8');
		return isset($map[$value])?$map[$value]:'';
	}
	private function location_precision_storage_code( $value ) {
		$map=array('point'=>'f32-c4','street'=>'f32-c5','area'=>'f32-c6','municipality'=>'f32-c7');
		return isset($map[$value])?$map[$value]:'';
	}
	private function vehicle_type_storage_code( $value ) {
		$map=array('car'=>'f38-c9','motorcycle'=>'f38-c10','scooter'=>'f38-c11','van'=>'f38-c12','truck'=>'f38-c13','camper'=>'f38-c14','bus'=>'f38-c15','bicycle'=>'f38-c16','scooter_device'=>'f38-c17','agricultural_work'=>'f38-c18','other'=>'f38-c19');
		return isset($map[$value])?$map[$value]:'';
	}
	private function vehicle_color_storage_label( $value ) {
		$map=array('white'=>'Bianco','black'=>'Nero','gray'=>'Grigio','silver'=>'Argento','blue'=>'Blu','light_blue'=>'Azzurro','red'=>'Rosso','green'=>'Verde','yellow'=>'Giallo','orange'=>'Arancione','brown'=>'Marrone','beige'=>'Beige','purple'=>'Viola','pink'=>'Rosa','multicolor'=>'Multicolore','other'=>'Altro colore','unknown'=>'Non lo so');
		return isset($map[$value])?$map[$value]:'';
	}
	private function storage_date( $value ) {
		$date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$value);
		return $date?$date->format('d/m/Y'):sanitize_text_field($value);
	}
}
