<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/class-badaround-public-geo-contract.php';

/** Explicit private moderation decision only. No projection or publication API. */
class BadAround_Geo_Moderation_Service {
	public static function labels() {
		return array(
			'exact_point' => 'Punto esatto', 'approximate_area' => 'Area approssimativa',
			'street' => 'Via / tratto stradale', 'municipality' => 'Solo Comune',
			'territory_only' => 'Solo territorio, senza posizione sulla mappa',
			'legacy_generalized_point' => 'Punto approssimato (criterio legacy)',
		);
	}

	public static function can_edit( $event_id ) {
		return get_current_user_id() > 0 && current_user_can( 'ba_moderate_events' )
			&& current_user_can( 'ba_view_private_reports' ) && current_user_can( 'edit_post', $event_id );
	}

	public static function read_only( $event_id ) {
		return ! in_array( get_post_status( $event_id ), array( 'pending', 'draft' ), true )
			|| 'published' === get_post_meta( $event_id, '_ba_moderation_status', true );
	}

	/** Read only request/version; never retrieve private address or coordinates. */
	public function request_context( $event_id ) {
		global $wpdb;
		if ( ! self::can_edit( $event_id ) ) { return $this->error( 'forbidden' ); }
		$primary = absint( get_post_meta( $event_id, '_ba_primary_report_id', true ) );
		$primary_sql = $primary ? $wpdb->prepare( ' AND id = %d', $primary ) : '';
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, source_type, JSON_VALID(content_original) AS valid_json,
			 CASE WHEN JSON_VALID(content_original) THEN JSON_CONTAINS_PATH(content_original, 'one', '$.canonical.location.public_location_request', '$.canonical.location.public_location_request_version') ELSE 0 END AS has_request,
			 CASE WHEN JSON_VALID(content_original) THEN JSON_UNQUOTE(JSON_EXTRACT(content_original, '$.canonical.location.public_location_request')) ELSE NULL END AS request_mode,
			 CASE WHEN JSON_VALID(content_original) THEN JSON_UNQUOTE(JSON_EXTRACT(content_original, '$.canonical.location.public_location_request_version')) ELSE NULL END AS request_version
			 FROM {$wpdb->prefix}ba_reports WHERE event_id = %d AND deleted_at IS NULL {$primary_sql} ORDER BY id ASC LIMIT 1",
			$event_id
		) );
		if ( ! $row || ! empty( $wpdb->last_error ) || ( 'native' === $row->source_type && ! (int) $row->valid_json ) ) { return $this->error( 'report_unavailable' ); }
		$location = array();
		if ( (int) $row->has_request ) {
			$location = array( 'public_location_request' => $row->request_mode, 'public_location_request_version' => $row->request_version );
		}
		if ( is_wp_error( BadAround_Public_Geo_Contract::validate_request( $location ) ) ) { return $this->error( 'request_invalid' ); }
		$request = $location['public_location_request'] ?? null;
		return array( 'report_id' => (int) $row->id, 'location' => $location, 'request' => $request,
			'allowed' => BadAround_Public_Geo_Contract::allowed_decisions( $request ) );
	}

	public static function revision( $decision ) { return hash( 'sha256', wp_json_encode( $decision ?: null ) ); }

	/** POST nonce is verified by the admin handler; authority is rechecked here. */
	public function save( $event_id, $input ) {
		global $wpdb;
		$event_id = absint( $event_id );
		if ( ! $event_id || ! self::can_edit( $event_id ) ) { return $this->error( 'forbidden' ); }
		if ( ! is_array( $input ) || ! isset( $input['mode'], $input['revision'] )
			|| ! is_string( $input['mode'] ) || ! is_string( $input['revision'] )
			|| ( isset( $input['reason_code'] ) && ! is_string( $input['reason_code'] ) ) ) { return $this->error( 'invalid_input' ); }
		// Audit and metadata must commit together. Never convert table engines here.
		$engines = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND ENGINE = 'InnoDB' AND TABLE_NAME IN (%s,%s,%s)",
			$wpdb->posts, $wpdb->postmeta, $wpdb->prefix . 'ba_audit_log'
		) );
		if ( 3 !== $engines || false === $wpdb->query( 'START TRANSACTION' ) ) { return $this->error( 'transaction_unavailable' ); }
		$committed = false;
		try {
			$post = $wpdb->get_row( $wpdb->prepare( "SELECT post_type,post_status FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE", $event_id ) );
			wp_cache_delete( $event_id, 'post_meta' );
			if ( ! $post || 'ba_evento' !== $post->post_type || ! in_array( $post->post_status, array( 'pending', 'draft' ), true )
				|| 'published' === get_post_meta( $event_id, '_ba_moderation_status', true ) ) { return $this->error( 'read_only' ); }
			$context = $this->request_context( $event_id );
			if ( is_wp_error( $context ) ) { return $context; }
			$before = get_post_meta( $event_id, BadAround_Public_Geo_Contract::DECISION_META, true );
			if ( ! hash_equals( self::revision( $before ), $input['revision'] ) ) { return $this->error( 'stale' ); }
			$mode = $input['mode'];
			if ( ! in_array( $mode, $context['allowed'], true ) ) { return $this->error( 'precision_forbidden' ); }
			$reduced = null !== $context['request'] && $context['request'] !== $mode;
			$code = $input['reason_code'] ?? '';
			$reasons = BadAround_Public_Geo_Contract::reason_codes();
			if ( ( $reduced && '' === $code ) || ( '' !== $code && ! isset( $reasons[ $code ] ) ) ) { return $this->error( 'reason_required' ); }
			$confirmed = in_array( $input['exact_confirmed'] ?? null, array( true, '1' ), true );
			if ( 'exact_point' === $mode && ! $confirmed ) { return $this->error( 'exact_confirmation_required' ); }
			$decision = array(
				'version' => BadAround_Public_Geo_Contract::VERSION, 'mode' => $mode,
				'reason' => $code ? $reasons[ $code ] : '', 'reason_code' => $code,
				'policy_version' => BadAround_Public_Geo_Contract::VERSION,
				'decided_at' => str_replace( ' ', 'T', current_time( 'mysql', true ) ) . 'Z',
				'decided_by' => (int) get_current_user_id(), 'exact_confirmed' => 'exact_point' === $mode,
			);
			$valid = BadAround_Public_Geo_Contract::validate_decision( $context['location'], $decision );
			if ( is_wp_error( $valid ) ) { return $valid; }
			if ( is_array( $before ) && ! is_wp_error( BadAround_Public_Geo_Contract::validate_decision( $context['location'], $before ) )
				&& $before['mode'] === $mode && ( $before['reason_code'] ?? '' ) === $code
				&& $before['decided_by'] === $decision['decided_by']
				&& ( $before['exact_confirmed'] ?? false ) === $decision['exact_confirmed'] ) { return 'unchanged'; }
			if ( ! update_post_meta( $event_id, BadAround_Public_Geo_Contract::DECISION_META, $decision )
				|| get_post_meta( $event_id, BadAround_Public_Geo_Contract::DECISION_META, true ) !== $decision ) { return $this->error( 'storage_failed' ); }
			if ( ! BadAround_Audit_Log::geographic_decision( $event_id, $context['location'], $before, $decision ) ) { return $this->error( 'audit_failed' ); }
			if ( false === $wpdb->query( 'COMMIT' ) ) { return $this->error( 'storage_failed' ); }
			$committed = true;
			return 'saved';
		} catch ( Throwable $error ) {
			return $this->error( 'storage_failed' );
		} finally {
			if ( ! $committed ) { $wpdb->query( 'ROLLBACK' ); }
			wp_cache_delete( $event_id, 'post_meta' );
		}
	}

	private function error( $code ) { return new WP_Error( 'ba_geo_decision_' . $code, __( 'La decisione geografica non è stata salvata.', 'badaround-core' ) ); }
}
