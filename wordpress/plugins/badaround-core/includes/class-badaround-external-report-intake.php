<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * EXT-INTAKE-01M: manual, administrator-only external intake.
 * Deliberately separate from Native v1/v2: no simulated reporter or consent.
 * No remote downloads, public original media, AI calls, or publication.
 */
class BadAround_External_Report_Contract {
	const VERSION = 'badaround-report/external-v1';
	const ENVELOPE = 'badaround-external-report/v1';

	public static function error( $code ) {
		return new WP_Error( 'ba_external_' . $code, 'Importazione esterna non completata: ' . $code, array( 'status' => 400 ) );
	}

	public static function prepare( $input ) {
		if ( ! is_array( $input ) ) { return self::error( 'invalid_input' ); }
		$keys = array( 'source_platform', 'source_url', 'source_reference', 'evidence_sha256', 'original_text', 'title', 'description', 'category', 'subtype', 'territory_id', 'area_label', 'date_start', 'date_end', 'period', 'date_note', 'exact_address', 'plate', 'vehicle_type', 'vehicle_make', 'vehicle_model', 'vehicle_color', 'existing_event_id', 'expected_event_hash', 'adoption_reset_approval' );
		if ( array_diff( array_keys( $input ), $keys ) ) { return self::error( 'unknown_field' ); }
		$limits = array( 'source_platform'=>32, 'source_url'=>2048, 'source_reference'=>255, 'evidence_sha256'=>64, 'original_text'=>12000, 'title'=>180, 'description'=>6000, 'category'=>64, 'subtype'=>64, 'area_label'=>191, 'date_start'=>10, 'date_end'=>10, 'period'=>32, 'date_note'=>500, 'exact_address'=>500, 'plate'=>32, 'vehicle_type'=>32, 'vehicle_make'=>100, 'vehicle_model'=>100, 'vehicle_color'=>32, 'expected_event_hash'=>64 );
		$data = array();
		foreach ( $limits as $key => $max ) {
			$value = $input[$key] ?? '';
			if ( ! is_string( $value ) || strlen( $value ) > $max * 4 || ( function_exists('mb_strlen') ? mb_strlen($value) : strlen($value) ) > $max ) { return self::error( 'invalid_' . $key ); }
			$data[$key] = in_array( $key, array( 'original_text', 'description', 'date_note' ), true ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		}
		foreach ( array( 'territory_id', 'existing_event_id' ) as $key ) {
			$value = $input[$key] ?? 0;
			if ( ! is_int( $value ) || $value < 0 ) { return self::error( 'invalid_' . $key ); }
			$data[$key] = $value;
		}
		$data['adoption_reset_approval']=$input['adoption_reset_approval']??false;
		if(!is_bool($data['adoption_reset_approval']) || ($data['adoption_reset_approval'] && !$data['existing_event_id'])){return self::error('adoption_reset_flag');}
		foreach ( array( 'source_url', 'source_reference', 'title', 'description', 'original_text', 'area_label' ) as $key ) {
			if ( '' === trim( $data[$key] ) ) { return self::error( 'missing_' . $key ); }
		}
		if ( ! in_array( $data['source_platform'], array( 'facebook', 'instagram', 'web', 'rss', 'open_data', 'partner' ), true ) ) { return self::error( 'platform' ); }
		$url = wp_parse_url( $data['source_url'] );
		if ( ! is_array( $url ) || 'https' !== ( $url['scheme'] ?? '' ) || empty( $url['host'] ) || isset( $url['user'], $url['pass'] ) || isset( $url['user'] ) || isset( $url['pass'] ) || isset( $url['port'] ) ) { return self::error( 'source_url' ); }
		// Facebook MVP accepts a stable group permalink, not a tracking/share URL.
		if ( 'facebook' === $data['source_platform'] ) {
			if ( ! in_array( strtolower( $url['host'] ), array( 'facebook.com', 'www.facebook.com', 'm.facebook.com' ), true )
				|| ! preg_match( '~^/groups/([0-9]+)/permalink/([0-9]+)/?$~D', $url['path'] ?? '', $match ) ) { return self::error( 'facebook_permalink' ); }
			if ( 'group:' . $match[1] . ':post:' . $match[2] !== $data['source_reference'] ) { return self::error( 'source_mismatch' ); }
			$data['source_url'] = 'https://www.facebook.com/groups/' . $match[1] . '/permalink/' . $match[2] . '/';
		} else {
			if ( ! empty( $url['query'] ) ) { return self::error( 'source_query_not_supported' ); }
			$data['source_url'] = 'https://' . strtolower( $url['host'] ) . ( $url['path'] ?? '/' );
		}
		if ( '' !== $data['evidence_sha256'] && ! preg_match( '/^[a-f0-9]{64}$/D', $data['evidence_sha256'] ) ) { return self::error( 'evidence_hash' ); }
		$categories = BadAround_Report_Schema::category_subtypes();
		if ( ! isset( $categories[$data['category']] ) || ! in_array( $data['subtype'], $categories[$data['category']], true ) ) { return self::error( 'category_subtype' ); }
		foreach ( array( 'vehicle_type'=>'vehicle.type', 'vehicle_color'=>'vehicle.color' ) as $key=>$field ) {
			$def = BadAround_Report_Schema::field_definition( $field );
			if ( '' !== $data[$key] && ! in_array( $data[$key], $def['constraints']['enum'], true ) ) { return self::error( $key ); }
		}
		if ( 'vehicle' === $data['category'] && '' === $data['vehicle_type'] ) { return self::error( 'vehicle_type' ); }
		if ( 'vehicle' !== $data['category'] && implode( '', array_intersect_key( $data, array_flip( array( 'vehicle_type', 'vehicle_make', 'vehicle_model', 'vehicle_color', 'plate' ) ) ) ) ) { return self::error( 'inactive_vehicle_fields' ); }
		$has_dates = '' !== $data['date_start'] || '' !== $data['date_end'];
		if ( $has_dates ) {
			foreach ( array( 'date_start', 'date_end' ) as $key ) {
				$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $data[$key], wp_timezone() );
				if ( ! $date || $date->format( 'Y-m-d' ) !== $data[$key] || $data[$key] > wp_date( 'Y-m-d' ) ) { return self::error( 'event_date' ); }
			}
			if ( $data['date_end'] < $data['date_start'] ) { return self::error( 'date_order' ); }
		}
		if ( ! in_array( $data['period'], array( '', 'night', 'day', 'unspecified' ), true ) ) { return self::error( 'period' ); }
		if ( $data['existing_event_id'] && ! preg_match( '/^[a-f0-9]{64}$/D', $data['expected_event_hash'] ) ) { return self::error( 'event_revision_required' ); }
		$data['plate'] = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $data['plate'] ) );
		if ( '' !== $data['plate'] && strlen($data['plate']) < 5 ) { return self::error('plate_incomplete'); }
		// None of the private identifiers may enter the proposed public text.
		foreach ( array( $data['plate'], $data['exact_address'], $data['source_url'], $data['source_reference'] ) as $private ) {
			if ( strlen( $private ) >= 5 && false !== stripos( $data['title'] . "\n" . $data['description'] . "\n" . $data['area_label'], $private ) ) { return self::error( 'private_public_text' ); }
		}
		$data['identity'] = $data['source_platform'] . ':' . $data['source_reference'];
		return $data;
	}

	public static function event_hash( $event_id ) {
		$post = get_post( $event_id );
		return $post ? hash( 'sha256', wp_json_encode( array( $post->ID, $post->post_type, $post->post_status, $post->post_title, $post->post_content, get_post_meta( $event_id, '_ba_primary_report_id', true ), get_post_meta( $event_id, '_ba_moderation_status', true ) ) ) ) : '';
	}

	public static function public_hash( $event_id ) {
		$post = get_post( $event_id );
		if ( ! $post ) { return ''; }
		$meta = array();
		foreach ( array( '_ba_public_place_name', '_ba_public_address', '_ba_occurred_date', '_ba_occurred_time', '_ba_time_precision', '_ba_vehicle_make', '_ba_vehicle_model', '_ba_vehicle_color', '_ba_vehicle_plate_masked' ) as $key ) { $meta[$key] = (string) get_post_meta( $event_id, $key, true ); }
		return hash( 'sha256', wp_json_encode( array( trim( wp_strip_all_tags( $post->post_title ) ), trim( wp_strip_all_tags( $post->post_content ) ), $meta, wp_get_object_terms( $event_id, 'ba_territorio', array( 'fields'=>'ids' ) ), wp_get_object_terms( $event_id, 'ba_tipo_evento', array( 'fields'=>'ids' ) ) ) ) );
	}
}

/** All external report table writes belong to this repository boundary. */
class BadAround_External_Report_Repository extends BadAround_Report_Repository {
	public function identity_parts( $identity ) {
		$hash = hash( 'sha256', $identity );
		return array( (int) hexdec( substr( $hash, 0, 7 ) ), (int) hexdec( substr( $hash, 7, 15 ) ) );
	}
	public function lock( $identity, $release = false ) {
		global $wpdb;
		$key = 'ba:external:' . hash( 'sha256', $identity );
		return $release ? $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) )
			: 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $key ) );
	}
	public function find( $identity ) {
		global $wpdb;
		list( $form, $entry ) = $this->identity_parts( $identity );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ba_reports WHERE source_type='external' AND source_form_id=%d AND source_entry_id=%d FOR UPDATE", $form, $entry ) );
	}
	public function insert_external( $data, $canonical, $envelope ) {
		global $wpdb;
		list( $form, $entry ) = $this->identity_parts( $data['identity'] );
		$payload = wp_json_encode( $envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$now = current_time( 'mysql', true );
		// Consent columns remain SQL NULL; no native consent timestamp or IP is fabricated.
		$result = $wpdb->insert( $wpdb->prefix . 'ba_reports', array(
			'report_type'=>'initial', 'source_type'=>'external', 'source_form_id'=>$form, 'source_entry_id'=>$entry,
			'user_id'=>get_current_user_id(), 'status'=>'validated', 'created_at'=>$now, 'updated_at'=>$now,
			'content_original'=>$payload, 'payload_hash'=>hash( 'sha256', $payload ),
			'exact_address'=>$data['exact_address'] ?: null, 'full_plate'=>$data['plate'] ?: null,
			'contact_preference'=>'f78-c10',
		) );
		return false === $result ? 0 : (int) $wpdb->insert_id;
	}
}

class BadAround_External_Report_Intake {
	public static function permitted() {
		return get_current_user_id() > 0 && current_user_can( 'manage_options' ) && current_user_can( 'ba_moderate_events' ) && current_user_can( 'ba_view_private_reports' );
	}
	public function ingest( $input ) {
		global $wpdb;
		if ( ! self::permitted() ) { return new WP_Error( 'ba_external_forbidden', 'Accesso riservato agli amministratori.', array( 'status'=>403 ) ); }
		$data = BadAround_External_Report_Contract::prepare( $input );
		if ( is_wp_error( $data ) ) { return $data; }
		$map = new BadAround_Event_Taxonomy_Map();
		$category = $map->resolve_category( $data['category'] );
		$subtype = $map->resolve_subtype( $data['subtype'], $category );
		$territory = get_term( $data['territory_id'], 'ba_territorio' );
		if ( ! $category || ! $subtype || ! $territory || is_wp_error( $territory ) || ! $territory->parent ) { return BadAround_External_Report_Contract::error( 'taxonomy' ); }
		// A verified municipality (and its ancestors) is required; never create terms or centroids.
		$chain = array_merge(array_reverse(get_ancestors($territory->term_id,'ba_territorio','taxonomy')),array($territory->term_id));
		$municipality = count($chain) >= 3 ? get_term((int)$chain[2],'ba_territorio') : null;
		if ( ! $municipality || is_wp_error( $municipality ) ) { return BadAround_External_Report_Contract::error( 'municipality' ); }
		$repo = new BadAround_External_Report_Repository();
		if ( ! $repo->lock( $data['identity'] ) ) { return BadAround_External_Report_Contract::error( 'busy' ); }
		$event_id = 0; $committed = false; $transaction = false;
		try {
			foreach ( array( $wpdb->posts, $wpdb->postmeta, $wpdb->term_relationships, $wpdb->term_taxonomy, $wpdb->prefix.'ba_reports', $wpdb->prefix.'ba_audit_log' ) as $table ) {
				if ( 'InnoDB' !== $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) ) ) { return BadAround_External_Report_Contract::error( 'transaction_unavailable' ); }
			}
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return BadAround_External_Report_Contract::error( 'transaction_unavailable' ); }
			$transaction = true;
			$existing = $repo->find( $data['identity'] );
			if ( ! empty( $wpdb->last_error ) ) { return BadAround_External_Report_Contract::error( 'storage' ); }
			$fingerprint = hash( 'sha256', wp_json_encode( array_diff_key( $data, array_flip( array( 'existing_event_id', 'expected_event_hash', 'adoption_reset_approval' ) ) ) ) );
			if ( $existing ) {
				$stored = json_decode( $existing->content_original, true );
				if ( ! empty( $existing->deleted_at ) || ( $stored['provenance']['identity'] ?? '' ) !== $data['identity'] ) { return BadAround_External_Report_Contract::error( 'identity_conflict' ); }
				if ( ( $stored['input_hash'] ?? '' ) !== $fingerprint ) { return BadAround_External_Report_Contract::error( 'source_changed_review_required' ); }
				$event_id = (int) $existing->event_id;
				if ( ! $event_id || (int) get_post_meta( $event_id, '_ba_primary_report_id', true ) !== (int) $existing->id
					|| ( $data['existing_event_id'] && $event_id !== $data['existing_event_id'] ) ) { return BadAround_External_Report_Contract::error( 'link_conflict' ); }
				return array( 'report_id'=>(int)$existing->id, 'event_id'=>$event_id, 'duplicate'=>true );
			}
			$event_id = $data['existing_event_id'];
			$reset_approval=false;
			if ( $event_id ) {
				$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE", $event_id ) );
				clean_post_cache( $event_id );
				$previous_moderation=get_post_meta($event_id,'_ba_moderation_status',true);
				$reset_approval='approved'===$previous_moderation && $data['adoption_reset_approval'];
				if ( ! $post || 'ba_evento' !== $post->post_type || ! in_array( $post->post_status, array('draft','pending'), true )
					|| ! current_user_can( 'edit_post', $event_id ) || get_post_meta( $event_id, '_ba_primary_report_id', true )
					|| (!in_array($previous_moderation,array('','new'),true) && !$reset_approval)
					|| ! hash_equals( $data['expected_event_hash'], BadAround_External_Report_Contract::event_hash( $event_id ) )
					|| (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ba_reports WHERE event_id=%d", $event_id ) ) ) { return BadAround_External_Report_Contract::error( 'adoption_conflict' ); }
				// Explicit adoption only: existing source note must match the supplied URL.
				$note = get_post_meta( $event_id, '_ba_admin_test_provenance', true );
				if ( ! is_array( $note ) || ( $note['source_url'] ?? '' ) !== $data['source_url'] ) { return BadAround_External_Report_Contract::error( 'adoption_source_mismatch' ); }
			}
			$now = gmdate( 'Y-m-d\TH:i:s\Z' );
			$canonical = array( 'schema_version'=>BadAround_External_Report_Contract::VERSION,
				'event'=>array('category'=>$data['category'],'subtype'=>$data['subtype']),
				'location'=>array('exact_address'=>$data['exact_address'], 'exact_lat'=>null, 'exact_lng'=>null, 'area_label'=>$data['area_label'], 'public_precision'=>'area'),
				'time'=>array('mode'=>'' === $data['date_start'] ? 'unknown' : 'approximate', 'date_range'=>array('start'=>$data['date_start'] ?: null,'end'=>$data['date_end'] ?: null,'period'=>$data['period'],'note'=>$data['date_note'])),
				'vehicle'=>array('type'=>$data['vehicle_type'],'make'=>$data['vehicle_make'],'model'=>$data['vehicle_model'],'color'=>$data['vehicle_color'],'plate_raw'=>$data['plate']),
				'content'=>array('description'=>$data['original_text']), 'reporter'=>array('contact_preference'=>'none'),
				'consents'=>array('status'=>'not_collected'), 'media'=>array('items'=>array()), 'social'=>array('status'=>'blocked') );
			$envelope = array( 'schema_version'=>BadAround_External_Report_Contract::ENVELOPE, 'canonical'=>$canonical, 'input_hash'=>$fingerprint,
				'provenance'=>array('source_type'=>'external','source_platform'=>$data['source_platform'],'source_url'=>$data['source_url'],'source_reference'=>$data['source_reference'],'identity'=>$data['identity'],'import_mode'=>'manual_capture','imported_at'=>$now,'imported_by'=>(int)get_current_user_id(),'evidence_sha256'=>$data['evidence_sha256'],'source_access'=>'unknown','evidence_access'=>'supplied_capture'),
				'external_governance'=>array('version'=>'external-editorial/v1','state'=>'needs_review','media'=>'excluded','social'=>'blocked','orphan_approval_reset'=>$reset_approval),
				'retention'=>array('review_due_at'=>gmdate('Y-m-d\TH:i:s\Z', time()+90*DAY_IN_SECONDS)) );
			$report_id = $repo->insert_external( $data, $canonical, $envelope );
			if ( ! $report_id ) { return BadAround_External_Report_Contract::error( 'report_storage' ); }
			if ( ! $event_id ) {
				$event_id = wp_insert_post( array('post_type'=>'ba_evento','post_status'=>'pending','post_title'=>$data['title'],'post_content'=>$data['description']), true );
			} else {
				$event_id = wp_update_post( array('ID'=>$event_id,'post_status'=>'pending','post_title'=>$data['title'],'post_content'=>$data['description']), true );
			}
			if ( is_wp_error( $event_id ) || ! $event_id ) { $event_id=0; return BadAround_External_Report_Contract::error( 'event_storage' ); }
			$event_id=(int)$event_id;
			$meta = ( new BadAround_Report_Persistence_Service() )->event_meta( $canonical );
			unset($meta['_ba_reward_available'],$meta['_ba_reward_amount'],$meta['_ba_expires_at']);
			$meta = array_merge( $meta, array('_ba_source_type'=>'external','_ba_primary_report_id'=>$report_id,'_ba_imported_at'=>current_time('mysql',true),'_ba_external_contract'=>BadAround_External_Report_Contract::VERSION) );
			if ( $data['plate'] ) { $meta['_ba_vehicle_plate_masked']=substr($data['plate'],0,2).'***'.substr($data['plate'],-2); }
			foreach ( $meta as $key=>$value ) {
				update_post_meta( $event_id, $key, $value );
				if ( get_post_meta($event_id,$key,true) != $value ) { return BadAround_External_Report_Contract::error('event_meta'); }
			}
			if ( is_wp_error( $map->assign($event_id,$canonical) ) || is_wp_error( wp_set_object_terms($event_id,array($data['territory_id']),'ba_territorio',false) ) || ! $repo->link_event($report_id,$event_id) ) { return BadAround_External_Report_Contract::error('event_link'); }
			// Explicit, conservative GEO decision, never a fabricated author request.
			$decision=array('version'=>BadAround_Public_Geo_Contract::VERSION,'policy_version'=>BadAround_Public_Geo_Contract::VERSION,'mode'=>'territory_only','reason'=>'Fonte esterna: nessuna coordinata verificata; solo territorio.','reason_code'=>'unreliable_coordinates','decided_at'=>$now,'decided_by'=>(int)get_current_user_id(),'exact_confirmed'=>false);
			if ( is_wp_error( BadAround_Public_Geo_Contract::validate_decision($canonical['location'],$decision) ) ) { return BadAround_External_Report_Contract::error('geo'); }
			update_post_meta($event_id,BadAround_Public_Geo_Contract::DECISION_META,$decision);
			if ( get_post_meta($event_id,BadAround_Public_Geo_Contract::DECISION_META,true)!==$decision ) { return BadAround_External_Report_Contract::error('geo_storage'); }
			if ( ! BadAround_Audit_Log::record('report',$report_id,'external_report_received','external_manual') || ! BadAround_Audit_Log::record('event',$event_id,'external_event_pending','external_manual') ) { return BadAround_External_Report_Contract::error('audit'); }
			if($reset_approval && !BadAround_Audit_Log::transition('event',$event_id,'external_orphan_approval_reset','approved','new','external_manual','Nuovo report sorgente: precedente approvazione priva di report non riutilizzata.')){return BadAround_External_Report_Contract::error('audit');}
			// Provenance now belongs solely to the reserved report, not event metadata.
			delete_post_meta($event_id,'_ba_admin_test_provenance');
			if ( get_post_status($event_id)!=='pending' || false===$wpdb->query('COMMIT') ) { return BadAround_External_Report_Contract::error('commit'); }
			$committed=true;
			return array('report_id'=>$report_id,'event_id'=>$event_id,'duplicate'=>false,'status'=>'pending','review'=>'required');
		} catch ( Throwable $error ) { return BadAround_External_Report_Contract::error('storage'); }
		finally {
			if ( $transaction && ! $committed ) { $wpdb->query('ROLLBACK'); }
			if ( $event_id ) { clean_post_cache($event_id); clean_object_term_cache($event_id,'ba_evento'); }
			clean_term_cache(array($category->term_id,$subtype->term_id),'ba_tipo_evento');
			clean_term_cache(array($territory->term_id),'ba_territorio');
			wp_cache_delete($territory->term_id,'ba_territorio_relationships');
			$repo->lock($data['identity'],true);
		}
	}
}

class BadAround_External_Report_Admin {
	const REVIEW_META='_ba_external_editorial_review';
	public function register_hooks() {
		add_action('rest_api_init',array($this,'routes'));
		add_action('add_meta_boxes_ba_evento',array($this,'box'));
		add_action('admin_footer-post.php',array($this,'review_form'));
		add_action('admin_post_ba_external_review',array($this,'review_action'));
	}
	public function routes() {
		register_rest_route('badaround/v1','/external-reports',array('methods'=>'POST','permission_callback'=>array('BadAround_External_Report_Intake','permitted'),'callback'=>function($request){return (new BadAround_External_Report_Intake())->ingest($request->get_json_params());}));
		register_rest_route('badaround/v1','/external-reports/(?P<event>[0-9]+)/preflight',array('methods'=>'GET','permission_callback'=>array('BadAround_External_Report_Intake','permitted'),'callback'=>function($request){
			$id=(int)$request['event'];$post=get_post($id);
			if(!$post || 'ba_evento'!==$post->post_type || !current_user_can('edit_post',$id)){return BadAround_External_Report_Contract::error('event');}
			return array('event_id'=>$id,'status'=>$post->post_status,'moderation_status'=>get_post_meta($id,'_ba_moderation_status',true),'event_hash'=>BadAround_External_Report_Contract::event_hash($id),'report_id'=>(int)get_post_meta($id,'_ba_primary_report_id',true));
		}));
	}
	public static function report($event_id) {
		global $wpdb;
		$id=(int)get_post_meta($event_id,'_ba_primary_report_id',true);
		return $id ? $wpdb->get_row($wpdb->prepare("SELECT id,event_id,source_type,content_original,payload_hash FROM {$wpdb->prefix}ba_reports WHERE id=%d AND event_id=%d AND source_type='external' AND deleted_at IS NULL",$id,$event_id)) : null;
	}
	public function publication_gate($allowed,$event_id) {
		global $wpdb;
		if ( is_wp_error($allowed) ) { return $allowed; }
		$has = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ba_reports WHERE event_id=%d AND source_type='external' AND deleted_at IS NULL",$event_id));
		if ( ! empty($wpdb->last_error) ) { return BadAround_External_Report_Contract::error('source_unavailable'); }
		if ( ! $has && 'external'!==get_post_meta($event_id,'_ba_source_type',true) ) { return $allowed; }
		if ( ! current_user_can('ba_view_private_reports') ) { return BadAround_External_Report_Contract::error('private_access'); }
		$row=self::report($event_id);
		if ( ! $row || ! hash_equals((string)$row->payload_hash,hash('sha256',$row->content_original)) ) { return BadAround_External_Report_Contract::error('private_report_invalid'); }
		$payload=json_decode($row->content_original,true);
		if ( ($payload['schema_version']??'')!==BadAround_External_Report_Contract::ENVELOPE || ($payload['canonical']['schema_version']??'')!==BadAround_External_Report_Contract::VERSION ) { return BadAround_External_Report_Contract::error('version'); }
		$review=get_post_meta($event_id,self::REVIEW_META,true);
		if ( ! is_array($review) || ($review['source_hash']??'')!==$row->payload_hash || ($review['public_hash']??'')!==BadAround_External_Report_Contract::public_hash($event_id) || empty($review['basis']) || empty($review['reason']) ) { return BadAround_External_Report_Contract::error('editorial_review_required'); }
		$decision=get_post_meta($event_id,BadAround_Public_Geo_Contract::DECISION_META,true);
		$media_count=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ba_report_media WHERE event_id=%d",$event_id));
		if(!empty($wpdb->last_error)){return BadAround_External_Report_Contract::error('media_unavailable');}
		if ( ($decision['mode']??'')!=='territory_only' || has_post_thumbnail($event_id)
			|| (int)$media_count ) { return BadAround_External_Report_Contract::error('geo_or_media_review'); }
		return $allowed;
	}
	public function box($post) {
		if ( BadAround_External_Report_Intake::permitted() && self::report($post->ID) ) {
			add_meta_box('ba_external_source','Fonte esterna e verifica editoriale',array($this,'render_box'),'ba_evento','normal','high');
		}
	}
	public function render_box($post) {
		if ( ! BadAround_External_Report_Intake::permitted() ) { return; }
		$row=self::report($post->ID); if(!$row){return;}
		$p=json_decode($row->content_original,true);$source=$p['provenance']??array();
		echo '<p><strong>Fonte esterna acquisita manualmente. Consensi dell’autore non acquisiti. Fotografie escluse; diffusione social bloccata.</strong></p>';
		echo '<p>Origine: '.esc_html($source['source_platform']??'').' — <a target="_blank" rel="noopener noreferrer" href="'.esc_url($source['source_url']??'').'">Apri la fonte</a></p>';
		echo '<p>Acquisita: '.esc_html($source['imported_at']??'').' — ID report: '.absint($row->id).'</p>';
		$range=$p['canonical']['time']['date_range']??array();
		echo '<p>Periodo dichiarato: '.esc_html(($range['start']??'non noto').' / '.($range['end']??'non noto').' — '.($range['period']??'')).'</p>';
		echo '<p>'.esc_html($range['note']??'').'</p>';
		echo '<p>Localizzazione: solo territorio, senza marker. Prima di pubblicare, salva le modifiche e registra una valutazione editoriale motivata della provenienza, dei dati pubblicabili e del presupposto di riutilizzo.</p>';
		if ( !in_array(get_post_status($post->ID),array('draft','pending'),true) ){return;}
		$review=get_post_meta($post->ID,self::REVIEW_META,true);
		if(is_array($review)){echo '<p>Ultima verifica: '.esc_html($review['at']??'').' — '.esc_html($review['basis']??'').'</p>';}
		echo '<p><label>Presupposto documentato di riutilizzo<br><select name="basis" form="ba-external-review-form" required><option value="">Da valutare</option><option value="permission_evidenced">Autorizzazione documentata</option><option value="legitimate_interest_assessed">Interesse legittimo valutato e documentato</option><option value="other_documented_basis">Altro presupposto documentato</option></select></label></p>';
		echo '<p><label>Motivazione e riferimento alla valutazione<br><textarea class="widefat" name="reason" form="ba-external-review-form" maxlength="2000" required></textarea></label></p>';
		echo '<button class="button" type="submit" form="ba-external-review-form">Registra verifica editoriale</button><p>Questa azione non approva e non pubblica. Usa poi i normali comandi di moderazione e pubblicazione.</p>';
	}
	public function review_form() {
		global $post;
		if(!$post || 'ba_evento'!==$post->post_type || !BadAround_External_Report_Intake::permitted() || !self::report($post->ID)){return;}
		echo '<form id="ba-external-review-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ba_external_review"><input type="hidden" name="event_id" value="'.absint($post->ID).'"><input type="hidden" name="public_hash" value="'.esc_attr(BadAround_External_Report_Contract::public_hash($post->ID)).'">';
		wp_nonce_field('ba_external_review_'.$post->ID,'ba_external_nonce');echo '</form>';
	}
	public function review_action() {
		$id=absint($_POST['event_id']??0);
		if(!BadAround_External_Report_Intake::permitted() || !current_user_can('edit_post',$id)){wp_die('Accesso non autorizzato.','',array('response'=>403));}
		check_admin_referer('ba_external_review_'.$id,'ba_external_nonce');
		$result=$this->save_review($id,wp_unslash($_POST));
		if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()));}
		wp_safe_redirect(get_edit_post_link($id,'raw'));exit;
	}
	public function save_review($id,$input) {
		global $wpdb;
		if(!BadAround_External_Report_Intake::permitted() || !current_user_can('edit_post',$id) || !in_array(get_post_status($id),array('draft','pending'),true)){return BadAround_External_Report_Contract::error('review_forbidden');}
		$basis=$input['basis']??'';$reason=$input['reason']??'';$revision=$input['public_hash']??'';
		if(!is_string($basis) || !in_array($basis,array('permission_evidenced','legitimate_interest_assessed','other_documented_basis'),true) || !is_string($reason) || ''===trim(sanitize_textarea_field($reason)) || strlen($reason)>8000 || !is_string($revision)){return BadAround_External_Report_Contract::error('review_stale_or_incomplete');}
		foreach(array($wpdb->posts,$wpdb->postmeta,$wpdb->prefix.'ba_audit_log') as $table){
			if('InnoDB'!==$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table))){return BadAround_External_Report_Contract::error('transaction_unavailable');}
		}
		if(false===$wpdb->query('START TRANSACTION')){return BadAround_External_Report_Contract::error('transaction_unavailable');}
		$committed=false;
		try{
			$post=$wpdb->get_row($wpdb->prepare("SELECT ID,post_status FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$id));clean_post_cache($id);
			$row=self::report($id);
			if(!$post || !in_array($post->post_status,array('draft','pending'),true) || !$row || !hash_equals(BadAround_External_Report_Contract::public_hash($id),$revision)){return BadAround_External_Report_Contract::error('review_stale_or_incomplete');}
			$review=array('version'=>'external-editorial/v1','source_hash'=>$row->payload_hash,'public_hash'=>$revision,'basis'=>$basis,'reason'=>sanitize_textarea_field($reason),'by'=>(int)get_current_user_id(),'at'=>gmdate('Y-m-d\TH:i:s\Z'));
			update_post_meta($id,self::REVIEW_META,$review);
			if(get_post_meta($id,self::REVIEW_META,true)!==$review || !BadAround_Audit_Log::record('event',$id,'external_editorial_review','external_manual',$basis) || false===$wpdb->query('COMMIT')){return BadAround_External_Report_Contract::error('review_storage');}
			$committed=true;return true;
		}finally{
			if(!$committed){$wpdb->query('ROLLBACK');}clean_post_cache($id);
		}
	}
}

