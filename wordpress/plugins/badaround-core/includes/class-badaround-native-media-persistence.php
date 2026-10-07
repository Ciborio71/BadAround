<?php
if (!defined('ABSPATH')) { exit; }

/** UUID translation is internal, immediately before the existing private linker. */
class BadAround_Native_Media_Linker {
	private $ledger; private $legacy;
	public function __construct($ledger,$legacy=null) { $this->ledger=$ledger;$this->legacy=$legacy ?: new BadAround_Media_Repository(); }
	public function link_validated_private_media($report,$event,$items) {
		$numeric=array();
		foreach($items as $descriptor) {
			$item=$this->ledger->item($descriptor['media_id']);
			if(!$item || (int)$item['report_id']!==(int)$report || !(int)$item['report_media_id']) { return BadAround_Native_Media_Config::error('media_binding_invariant_failed'); }
			$copy=$descriptor;$copy['media_id']=(int)$item['report_media_id'];$numeric[]=$copy;
		}
		return $this->legacy->link_validated_private_media($report,$event,$numeric);
	}
	public function unlink_event($report,$event) { return $this->legacy->unlink_event($report,$event); }
}

/** Called only inside frozen F1.4's native submission lock. */
class BadAround_Native_Media_Persistence {
	private $service; private $repository; private $persistence; private $linker;
	public function __construct($service,$repository,$persistence=null,$linker=null) {
		$this->service=$service;$this->repository=$repository;
		$this->linker=$linker ?: new BadAround_Native_Media_Linker($service->ledger);
		$this->persistence=$persistence ?: new BadAround_Report_Persistence_Service($repository,null,null,$this->linker);
	}
	private function fail($code='media_binding_failed',$stage='binding') { BadAround_Native_Media_Config::audit('binding_failed',0,$stage.':'.$code);return BadAround_Native_Media_Config::error($code); }
	public function persist($report,$canonical,$source=array(),$options=array()) {
		$l=$this->service->ledger;$session=$l->submission($canonical['submission_id']);
		if($session && $session['state']==='committed') {
			// An admitted intent may finish in another worker before this native lock is acquired.
			$receipt=(new BadAround_Native_Media_Report_Adapter($this->service,$this->repository,new stdClass()))->completed($session,$canonical);
			if($receipt['status']!=='success' || (int)$receipt['report_id']!==(int)$report || !hash_equals($session['canonical_hash'],hash('sha256',BadAround_Native_Media_Service::json($canonical)))) { return $this->fail('media_binding_invariant_failed'); }
			return array('report_id'=>(int)$report,'event_id'=>(int)$receipt['event_id'],'duplicate'=>true,'recovered'=>true);
		}
		if(!$session || !in_array($session['state'],array('pinned','binding'),true) || !hash_equals($session['canonical_hash'],hash('sha256',BadAround_Native_Media_Service::json($canonical)))) { return $this->fail('media_binding_invariant_failed','pinned_intent'); }
		$ids=array_column($canonical['media']['items'],'media_id');sort($ids,SORT_STRING);
		$locks=array('session:'.$session['session_id']);foreach($ids as $id) { $locks[]='item:'.$id; }
		return $l->locked($locks,function()use($l,$session,$report,$canonical,$source,$options){
			$session=$l->session($session['session_id']);
			if(!$this->repository->native_payload_matches_report($report,$canonical) || (!empty($session['report_id']) && (int)$session['report_id']!==(int)$report)) { return $this->fail('media_binding_invariant_failed','report_owner'); }
			if(!$l->update_session($session,array('state'=>'binding','report_id'=>(int)$report,'lease_expires_at'=>$this->service->now()+(int)$this->service->config->get('lease_ttl')))) { return $this->fail(); }
			foreach($canonical['media']['items'] as $descriptor) {
				$item=$l->item($descriptor['media_id']);
				if(!$item || $item['session_id']!==$session['session_id'] || !in_array($item['state'],array('accepted_quarantined','binding','bound_pending_review'),true) || (!empty($item['report_id']) && (int)$item['report_id']!==(int)$report)) { return $this->fail('media_binding_invariant_failed','item_owner'); }
				$target='report-'.(int)$report.'/'.$item['media_id'].'.'.$item['extension'];
				if($item['state']==='accepted_quarantined') {
					if(!$l->update_item($item,array('state'=>'binding','report_id'=>(int)$report,'promotion_target'=>$target))) { return $this->fail(); }
					$item=$l->item($item['media_id']);
				}
				$promoted=$this->service->storage->promote($item,$report);if(is_wp_error($promoted)) { return $promoted; }
				$basename=basename($promoted);
				$rows=$l->final_candidates($report,$basename);
				if(count((array)$rows)>1) { return $this->fail('media_binding_invariant_failed','duplicate_final_mapping'); }
				if($rows) { $final=(int)$rows[0]['id']; }
				else { $final=$l->create_final($report,$item,$basename); }
				if(!$final || (!empty($item['report_media_id']) && (int)$item['report_media_id']!==$final)) { return $this->fail(); }
				$row=$l->final_row($final);
				if(!$this->private_row_matches($row,$item,$report)) { return $this->fail('media_binding_invariant_failed','private_original'); }
				if(!$l->update_item($item,array('storage_key'=>$promoted,'report_media_id'=>$final,'report_id'=>(int)$report))) { return $this->fail(); }
			}
			// Frozen persistence may return an existing event: reconcile even in that case.
			$result=$this->persistence->persist($report,$canonical,$source,$options);if(is_wp_error($result)) { return $result; }
			$event=(int)$result['event_id'];
			if(!$event || get_post_status($event)!=='pending' || get_post_meta($event,'_ba_moderation_status',true)!==BadAround_Moderation_Service::STATUS_NEW) { return $this->fail('media_binding_invariant_failed','initial_moderation'); }
			$linked=$this->linker->link_validated_private_media($report,$event,$canonical['media']['items']);if(is_wp_error($linked)) { return $this->fail(); }
			foreach($canonical['media']['items'] as $descriptor) {
				$item=$l->item($descriptor['media_id']);$row=$l->final_row($item['report_media_id']);
				if(!$this->private_row_matches($row,$item,$report) || (int)$row['event_id']!==$event || !$this->service->storage->verify($item['storage_key'],$item['file_size'],$item['checksum'])) { return $this->fail('media_binding_invariant_failed','linked_original'); }
				if(!$l->update_item($item,array('state'=>'bound_pending_review','event_id'=>$event))) { return $this->fail(); }
			}
			$set=$l->final_set($report);$expected=array_map('intval',$linked);$actual=array_map('intval',array_column($set,'id'));sort($expected);sort($actual);
			if($expected!==$actual || count($expected)!==count($canonical['media']['items']) || (int)$this->repository->event_id_for_report($report)!==$event) { return $this->fail('media_binding_invariant_failed','exact_final_set'); }
			$events=get_posts(array('post_type'=>'ba_evento','post_status'=>'any','fields'=>'ids','posts_per_page'=>2,'meta_key'=>'_ba_primary_report_id','meta_value'=>(int)$report));
			if(count($events)!==1 || (int)$events[0]!==$event) { return $this->fail('media_binding_invariant_failed','event_identity'); }
			$receipt=array('submission_id'=>$canonical['submission_id'],'report_id'=>(int)$report,'event_id'=>$event,'manifest_hash'=>$session['manifest_hash'],'media_rows'=>$expected);
			$session=$l->session($session['session_id']);
			if(!$l->update_session($session,array('state'=>'committed','event_id'=>$event,'receipt_json'=>BadAround_Native_Media_Service::json($receipt),'lease_expires_at'=>null))) { return $this->fail(); }
			BadAround_Native_Media_Config::audit('commit_completed',$report);return $result;
		});
	}
	private function private_row_matches($row,$item,$report) {
		return $row && (int)$row['report_id']===(int)$report && $row['sensitivity']==='private' && $row['review_status']==='pending_native_review'
			&& empty($row['public_attachment_id']) && empty($row['deleted_at']) && $row['original_storage_key']===$item['media_id'].'.'.$item['extension']
			&& hash_equals($row['checksum'],$item['checksum']) && (int)$row['file_size']===(int)$item['file_size'] && $row['mime_type']===$item['mime_type'];
	}
}

/** Read-only frozen validation before any media ownership lookup. */
class BadAround_Native_Media_Report_Adapter {
	private $service; private $repository; private $golden;
	public function __construct($service=null,$repository=null,$golden=null) {
		$this->service=$service ?: new BadAround_Native_Media_Service();$this->repository=$repository ?: new BadAround_Report_Repository();
		$this->golden=$golden ?: new BadAround_Native_Report_Golden_Path_Service(
			new BadAround_Native_Report_Intake_Service(null,null,$this->repository),new BadAround_Native_Media_Persistence($this->service,$this->repository),$this->repository);
	}
	public function submit($raw,$bearer) {
		$v=new BadAround_Report_Validator();$valid=$v->validate_raw_contract($raw);if(is_wp_error($valid)) { return BadAround_Report_Result::from_wp_error($valid); }
		$canonical=(new BadAround_Report_Normalizer())->normalize($raw);$valid=$v->validate($canonical);if(is_wp_error($valid)) { return BadAround_Report_Result::from_wp_error($valid); }
		$pin=$this->service->pin($canonical,$bearer,$this->repository);if(is_wp_error($pin)) { return BadAround_Report_Result::from_wp_error($pin); }
		if($pin['state']==='committed') { return $this->completed($pin,$canonical); }
		$result=$this->golden->submit($raw);
		if(isset($result['error']['details']['source_code']) && strpos($result['error']['details']['source_code'],'media_')===0) {
			return BadAround_Report_Result::error($result['error']['details']['source_code'],'media.items');
		}
		return $result;
	}
	public function completed($session,$canonical) {
		$l=$this->service->ledger;$receipt=json_decode($session['receipt_json'],true);
		if(!$receipt || $receipt['manifest_hash']!==$session['manifest_hash'] || (int)$session['report_id']!==(int)$receipt['report_id'] || (int)$session['event_id']!==(int)$receipt['event_id'] || !$this->repository->native_payload_matches_report($receipt['report_id'],$canonical) || (int)$this->repository->event_id_for_report($receipt['report_id'])!==(int)$receipt['event_id']) { return BadAround_Report_Result::error('media_binding_invariant_failed'); }
		$ids=array();
		foreach($canonical['media']['items'] as $d) {
			$i=$l->item($d['media_id']);$r=$i ? $l->final_row($i['report_media_id']) : null;
			$states=array('pending_native_review','native_redaction_required','native_redaction_verified','native_approved_private','native_rejected_private','native_derivative_verified','native_published');
			if(!$i || !$r || $i['session_id']!==$session['session_id'] || $i['state']!=='bound_pending_review' || (int)$r['report_id']!==(int)$receipt['report_id'] || (int)$r['event_id']!==(int)$receipt['event_id'] || $r['sensitivity']!=='private' || !empty($r['public_attachment_id']) || !empty($r['deleted_at']) || !in_array($r['review_status'],$states,true) || BadAround_Native_Media_Service::descriptor($i)!=$d || $r['checksum']!==$i['checksum'] || $r['mime_type']!==$i['mime_type'] || (int)$r['file_size']!==(int)$i['file_size']) { return BadAround_Report_Result::error('media_binding_invariant_failed'); }
			$ids[]=(int)$r['id'];
		}
		$actual=array_map('intval',array_column($l->final_set($receipt['report_id']),'id'));sort($actual);sort($ids);
		if($ids!==$actual || $ids!==$receipt['media_rows']) { return BadAround_Report_Result::error('media_binding_invariant_failed'); }
		// No filesystem reads, report writes, TTL renewal or moderation changes.
		return array('status'=>'success','submission_id'=>$canonical['submission_id'],'report_id'=>$receipt['report_id'],'event_id'=>$receipt['event_id'],'duplicate'=>true);
	}
}
