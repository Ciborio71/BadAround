<?php
if (!defined('ABSPATH')) { exit; }

/** Native-only admission and immutable commit intents; no report/event creation here. */
class BadAround_Native_Media_Service {
	public $config; public $ledger; public $storage; public $validator;
	private $capability; private $clock;
	public function __construct($config=null,$ledger=null,$storage=null,$validator=null,$clock=null) {
		$this->config=$config ?: new BadAround_Native_Media_Config();
		$this->ledger=$ledger ?: new BadAround_Native_Media_Ledger();
		$this->storage=$storage ?: new BadAround_Native_Media_Storage($this->config);
		$this->validator=$validator ?: new BadAround_Native_Media_Validator($this->config);
		$this->capability=new BadAround_Native_Media_Capability($this->config);
		$this->clock=$clock ?: 'time';
	}
	public function now() { return (int)call_user_func($this->clock); }
	public static function json($value) { return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
	public function ready() {
		if (!$this->config->enabled() || !$this->storage->root()) { return false; }
		$legacy=defined('BADAROUND_PRIVATE_MEDIA_PATH') ? BADAROUND_PRIVATE_MEDIA_PATH : dirname(rtrim(ABSPATH,'/')).'/badaround-private-media';
		if(function_exists('apply_filters')) { $legacy=apply_filters('badaround_private_media_path',$legacy); }
		// The existing capability-gated moderator inspector must resolve the same archive.
		if(!is_string($legacy) || realpath($legacy)!==$this->storage->root()) { return false; }
		if (PHP_SAPI!=='cli' && (!$this->ini_bytes(ini_get('upload_max_filesize'),5242880) || !$this->ini_bytes(ini_get('post_max_size'),5308416) || (int)ini_get('max_file_uploads')<1)) { return false; }
		foreach(array('upload_ttl','commit_ttl','orphan_ttl','lease_ttl','window_seconds','max_sessions','max_session_records','decoder_processes','concurrent_session','concurrent_ip') as $key) {
			if ((int)$this->config->get($key)<=0) { return false; }
		}
		return true===$this->ledger->locked(array('decoder'),function(){ return count($this->validator->codecs())>0; });
	}
	private function ini_bytes($value,$minimum) {
		if(!is_string($value) || !preg_match('/^([0-9]+)\s*([KMG]?)$/i',trim($value),$m)) { return false; }
		$factor=array(''=>1,'K'=>1024,'M'=>1048576,'G'=>1073741824);
		$bytes=(float)$m[1]*$factor[strtoupper($m[2])];return $bytes>=$minimum;
	}
	private function error($code,$field=null) { return BadAround_Native_Media_Config::error($code,$field); }
	private function counter($scope,$kind,$amount,$setting) {
		return $this->ledger->consume($scope,$kind,$amount,(int)$this->config->get($setting),(int)$this->config->get('window_seconds'),$this->now());
	}
	public function create($submission,$nonce,$ip) {
		if (!$this->ready()) { return $this->error('media_service_unavailable'); }
		if (!BadAround_Native_Media_Config::uuid($submission) || !BadAround_Native_Media_Capability::canonical_secret($nonce)) { return $this->error('media_manifest_invalid'); }
		if (!$this->counter('sessions:'.$ip,'hits',1,'session_ip_limit')) { return $this->error('media_rate_limited'); }
		return $this->ledger->locked(array('admission'),function()use($submission,$nonce){
			$now=$this->now(); $hash=BadAround_Native_Media_Capability::verifier($nonce);
			$row=$this->ledger->submission($submission); $duplicate=(bool)$row;
			if ($row) {
				if (!hash_equals($row['creation_nonce_hash'],$hash)) { return $this->error('media_manifest_conflict'); }
				if (in_array($row['state'],array('expired','blocked'),true) || $now>=(int)$row['upload_expires_at']) { return $this->error('media_session_expired'); }
			} else {
				$usage=$this->ledger->usage();
				if (!$usage || (int)$usage['active']>=(int)$this->config->get('max_sessions') || (int)$usage['records']>=(int)$this->config->get('max_session_records')) { return $this->error('media_service_unavailable'); }
				$row=array('session_id'=>BadAround_Native_Media_Config::new_uuid(),'submission_id'=>$submission,'creation_nonce_hash'=>$hash,
					'key_id'=>$this->config->get('active_key_id'),'server_salt'=>bin2hex(random_bytes(32)),'state'=>'open',
					'upload_expires_at'=>$now+(int)$this->config->get('upload_ttl'),'commit_expires_at'=>$now+(int)$this->config->get('commit_ttl'),
					'orphan_expires_at'=>$now+(int)$this->config->get('orphan_ttl'),'created_at'=>$now,'updated_at'=>$now,'version'=>1);
				$bearer=$this->capability->issue($row);
				if (is_wp_error($bearer)) { return $bearer; }
				$row['capability_hash']=BadAround_Native_Media_Capability::verifier($bearer);
				if (!$this->ledger->insert_session($row)) { return $this->error('media_service_unavailable'); }
				BadAround_Native_Media_Config::audit('session_created');
			}
			$bearer=$this->capability->issue($row);
			if (is_wp_error($bearer)) { return $bearer; }
			$codecs=$this->validator->codecs();$limits=BadAround_Native_Media_Config::constraints();
			$limits['allowed_extensions']=array_values(array_filter($limits['allowed_extensions'],static function($extension)use($codecs){return in_array(in_array($extension,array('jpg','jpeg'),true) ? 'jpeg' : $extension,$codecs,true);}));
			$limits['allowed_mime']=array_values(array_filter($limits['allowed_mime'],static function($mime)use($codecs){return in_array(substr($mime,6),$codecs,true);}));
			return array('media_session_id'=>$row['session_id'],'capability'=>$bearer,'upload_expires_at'=>(int)$row['upload_expires_at'],
				'commit_expires_at'=>(int)$row['commit_expires_at'],'limits'=>$limits,
				'supported_codecs'=>$codecs,'duplicate'=>$duplicate);
		});
	}
	public function owner($id,$bearer,$operation='status') {
		$row=BadAround_Native_Media_Config::uuid($id) ? $this->ledger->session($id) : null;
		if (!$row || !$this->capability->verify($row,$bearer)) { return $this->error('media_capability_invalid'); }
		if (in_array($row['state'],array('expired','blocked'),true)) { return $this->error('media_session_expired'); }
		$deadline=$operation==='write' ? $row['upload_expires_at'] : $row['commit_expires_at'];
		if ($row['state']!=='committed' && $this->now()>=(int)$deadline) { return $this->error('media_session_expired'); }
		return $row;
	}
	public static function descriptor($item) {
		return array('media_id'=>$item['media_id'],'mime_type'=>$item['mime_type'],'file_size'=>(int)$item['file_size'],'extension'=>$item['extension']);
	}
	/** A pinned session may also contain uploads deliberately excluded from its manifest. */
	public static function protects_item($session,$media) {
		if (!in_array($session['state'],array('pinned','binding','committed','blocked'),true)) { return false; }
		$manifest=json_decode($session['manifest_json'] ?? '',true);
		// Corrupt intent is never evidence that an original is disposable.
		if (!is_array($manifest)) { return true; }
		foreach($manifest as $descriptor) {
			if (!is_array($descriptor) || !isset($descriptor['media_id'])) { return true; }
			if ($descriptor['media_id']===$media) { return true; }
		}
		return false;
	}
	public function status($id,$bearer,$media=null) {
		$row=$this->owner($id,$bearer); if(is_wp_error($row)) { return $row; }
		$items=array();
		$rows=$this->ledger->items($id);
		if (!is_array($rows)) { return $this->error('media_service_unavailable'); }
		foreach($rows as $item) {
			if ($media && $item['media_id']!==$media) { continue; }
			$items[]=array('media_id'=>$item['media_id'],'state'=>$item['state'],'descriptor'=>in_array($item['state'],array('accepted_quarantined','binding','bound_pending_review'),true) ? self::descriptor($item) : null);
		}
		if ($media && !$items) { return $this->error('media_reference_invalid'); }
		return array('state'=>$row['state'],'items'=>$items,'receipt'=>$row['state']==='committed' ? json_decode($row['receipt_json'],true) : null);
	}
	public function remove($id,$media,$bearer) {
		return $this->ledger->locked(array('session:'.$id,'item:'.$media),function()use($id,$media,$bearer){
			$row=$this->owner($id,$bearer,'write'); if(is_wp_error($row)) { return $row; }
			$item=$this->ledger->item($media);
			if (!$item || $item['session_id']!==$id) { return $this->error('media_reference_invalid'); }
			if (self::protects_item($row,$media) || !empty($item['report_id']) || !empty($item['report_media_id']) || !in_array($item['state'],array('accepted_quarantined','removed'),true)) { return $this->error('media_manifest_conflict'); }
			if (!$this->storage->remove($item['storage_key'])) { return $this->error('media_service_unavailable'); }
			if ($item['state']!=='removed' || !$item['storage_deleted']) {
				if (!$this->ledger->update_item($item,array('state'=>'removed','storage_deleted'=>1))) { return $this->error('media_service_unavailable'); }
			}
			BadAround_Native_Media_Config::audit('upload_removed'); return array('media_id'=>$media,'state'=>'removed');
		});
	}
	private function upload_reply($item,$duplicate=false) { return array('state'=>$item['state'],'descriptor'=>self::descriptor($item),'duplicate'=>$duplicate); }
	public function upload($id,$client,$bearer,$stream,$filename,$ip) {
		if (!$this->ready()) { return $this->error('media_service_unavailable'); }
		$operation=BadAround_Native_Media_Config::new_uuid();
		// Replay byte readers consume concurrency too, without creating another media item.
		return $this->ledger->locked(array('operation:'.$operation),function()use($operation,$id,$client,$bearer,$stream,$filename,$ip){
			$admitted=$this->ledger->locked(array('admission','session:'.$id),function()use($operation,$id,$bearer,$ip){
				$row=$this->owner($id,$bearer,'write'); if(is_wp_error($row)) { return $row; }
				$active=$this->ledger->active_operations($id,$ip);
				if (!$active) { return $this->error('media_service_unavailable'); }
				if ((int)$active['session_count']>=(int)$this->config->get('concurrent_session') || (int)$active['ip_count']>=(int)$this->config->get('concurrent_ip')) { return $this->error('media_rate_limited'); }
				return $this->ledger->insert_operation(array('operation_id'=>$operation,'session_id'=>$id,'ip_hash'=>$ip,'lease_expires_at'=>$this->now()+(int)$this->config->get('lease_ttl'))) ? true : $this->error('media_service_unavailable');
			});
			if(is_wp_error($admitted)) { return $admitted; }
			try { return $this->perform_upload($id,$client,$bearer,$stream,$filename,$ip); }
			finally { $this->ledger->release_operation($operation); }
		});
	}
	private function perform_upload($id,$client,$bearer,$stream,$filename,$ip) {
		if (!$this->ready()) { return $this->error('media_service_unavailable'); }
		if (!BadAround_Native_Media_Config::uuid($client) || !is_resource($stream)) { return $this->error('media_manifest_invalid'); }
		if (!$this->counter('uploads:'.$ip,'hits',1,'upload_ip_limit') || !$this->counter('uploads:'.$id,'hits',1,'upload_session_limit')) { return $this->error('media_rate_limited'); }
		$admission=$this->ledger->locked(array('admission','session:'.$id),function()use($id,$client,$bearer,$ip){
			$row=$this->owner($id,$bearer,'write'); if(is_wp_error($row)) { return $row; }
			if ($row['state']!=='open') { return $this->error('media_manifest_conflict'); }
			if (!$this->counter('failures:'.$ip,'hits',0,'failed_ip_limit') || !$this->counter('failures:'.$id,'hits',0,'failed_session_limit')) { return $this->error('media_rate_limited'); }
			$old=$this->ledger->upload_key($id,$client);
			if ($old) {
				if ($old['state']==='receiving') { return $this->error('media_upload_incomplete'); }
				if ($old['state']!=='accepted_quarantined') { return $this->error('media_manifest_conflict'); }
				return array('replay'=>$old);
			}
			if ((int)$row['attempts']>=(int)$this->config->get('upload_session_limit')) { return $this->error('media_rate_limited'); }
			if ((int)$row['failed_validations']>=(int)$this->config->get('failed_session_limit')) { return $this->error('media_rate_limited'); }
			$active=0;$slots=0;
			$rows=$this->ledger->items($id);
			if (!is_array($rows)) { return $this->error('media_service_unavailable'); }
			foreach($rows as $item) {
				if (in_array($item['state'],array('receiving','accepted_quarantined'),true)) { ++$slots; }
				if ($item['state']==='receiving') { ++$active; }
			}
			$max=(int)BadAround_Native_Media_Config::constraints()['max_bytes_per_item'];
			$usage=$this->ledger->usage();
			if (!$usage || $slots>=BadAround_Native_Media_Config::constraints()['max_items']) { return $this->error('media_manifest_invalid','media.items'); }
			if ($active>=(int)$this->config->get('concurrent_session') || $this->ledger->active_ip($ip,$this->now())>=(int)$this->config->get('concurrent_ip')) { return $this->error('media_rate_limited'); }
			if ((int)$usage['temporary_bytes']>(int)$this->config->get('temporary_budget')-$max || !$this->storage->free_capacity($max)) { return $this->error('media_service_unavailable'); }
			$now=$this->now();$media=BadAround_Native_Media_Config::new_uuid();
			$item=array('media_id'=>$media,'session_id'=>$id,'client_upload_id'=>$client,'ip_hash'=>$ip,'file_size'=>$max,
				'storage_key'=>'native-staging/'.$id.'/'.$media.'.bin','state'=>'receiving','lease_expires_at'=>$now+(int)$this->config->get('lease_ttl'),
				'created_at'=>$now,'updated_at'=>$now,'version'=>1,'storage_deleted'=>0);
			if (!$this->ledger->insert_item($item)) { return $this->error('media_service_unavailable'); }
			if (!$this->ledger->update_session($row,array('attempts'=>(int)$row['attempts']+1))) { return $this->error('media_service_unavailable'); }
			return array('item'=>$item);
		});
		if (is_wp_error($admission)) { return $admission; }
		$on_bytes=function($bytes)use($id,$ip){
			if (!$this->counter('bytes:'.$ip,'bytes',$bytes,'ip_bytes_limit')) { return false; }
			return $this->ledger->add_received_bytes($id,$bytes,(int)$this->config->get('session_bytes_limit'));
		};
		if (isset($admission['replay'])) {
			$hash=hash_init('sha256');$bytes=0;$max=BadAround_Native_Media_Config::constraints()['max_bytes_per_item'];
			while(!feof($stream)) {
				$chunk=fread($stream,65536); if(false===$chunk || ($chunk==='' && !feof($stream))) { return $this->error('media_service_unavailable'); }
				$bytes+=strlen($chunk); if(!$on_bytes(strlen($chunk))) { return $this->error('media_rate_limited'); }
				if($bytes>$max) { return $this->error('media_manifest_conflict'); } hash_update($hash,$chunk);
			}
			$item=$admission['replay'];
			if($bytes!==(int)$item['file_size'] || !hash_equals($item['checksum'],hash_final($hash))) { return $this->error('media_manifest_conflict'); }
			return $this->ledger->locked(array('session:'.$id),function()use($id,$bearer,$item){
				$row=$this->owner($id,$bearer,'write');$fresh=$this->ledger->item($item['media_id']);
				if(is_wp_error($row)) { return $row; }
				if($row['state']!=='open' || !$fresh || $fresh['state']!=='accepted_quarantined') { return $this->error('media_manifest_conflict'); }
				return $this->upload_reply($fresh,true);
			});
		}
		$item=$admission['item'];
		// Upload lease lock stays outside session locks; cleanup cannot delete an active writer.
		$result=$this->ledger->locked(array('writer:'.$item['media_id']),function()use($item,$stream,$filename,$on_bytes){
			$fresh=$this->ledger->item($item['media_id']);
			if (!$fresh || $fresh['state']!=='receiving' || $fresh['storage_deleted']) { return $this->error('media_upload_incomplete'); }
			$staged=$this->storage->stage($item['session_id'],$item['media_id'],$stream,$on_bytes);
			if(is_wp_error($staged)) { return $staged; }
			$path=$this->storage->path($staged['storage_key']); if(is_wp_error($path)) { return $path; }
			$decoded=$this->ledger->locked(array('decoder'),function()use($path,$filename){ return $this->validator->validate($path,$filename); });
			return is_wp_error($decoded) ? $decoded : array_merge($staged,$decoded);
		});
		return $this->ledger->locked(array('session:'.$id),function()use($id,$bearer,$client,$ip,$item,$result){
			$fresh=$this->ledger->item($item['media_id']);$row=$this->owner($id,$bearer,'write');
			if(!$fresh || $fresh['state']!=='receiving') { return $this->error('media_upload_incomplete'); }
			if(is_wp_error($result) || is_wp_error($row) || $row['state']!=='open') {
				$deleted=$this->storage->remove($fresh['storage_key']);
				$this->ledger->update_item($fresh,array('state'=>'invalid','storage_deleted'=>$deleted ? 1 : 0));
				$this->counter('failures:'.$ip,'hits',1,'failed_ip_limit');$this->counter('failures:'.$id,'hits',1,'failed_session_limit');
				if(!is_wp_error($row)) { $this->ledger->update_session($row,array('failed_validations'=>(int)$row['failed_validations']+1)); }
				return is_wp_error($result) ? $result : (is_wp_error($row) ? $row : $this->error('media_manifest_conflict'));
			}
			$duplicate=$this->ledger->accepted_checksum($id,$result['checksum'],$item['media_id']);
			if($duplicate) {
				if(!$this->storage->remove($fresh['storage_key']) || !$this->ledger->alias($id,$client,$duplicate['media_id'],$this->now()) || !$this->ledger->update_item($fresh,array('state'=>'removed','storage_deleted'=>1))) { return $this->error('media_service_unavailable'); }
				return $this->upload_reply($duplicate,true);
			}
			$changes=array_merge($result,array('state'=>'accepted_quarantined','lease_expires_at'=>null));
			if(!$this->ledger->update_item($fresh,$changes)) { return $this->error('media_service_unavailable'); }
			BadAround_Native_Media_Config::audit('upload_accepted');return $this->upload_reply(array_merge($fresh,$changes));
		});
	}
	public function pin($canonical,$bearer,$repository=null) {
		$id=$canonical['submission_id'];$json=self::json($canonical);$manifest=$canonical['media']['items'];
		// Readiness may spawn the bounded decoder. Never run it under commit/admission locks.
		// Completed replay skips readiness entirely; only its durable verifier and receipt matter.
		$observed=$this->ledger->submission($id);
		if(!$observed || !$this->capability->verify($observed,$bearer)) { return $this->error('media_capability_invalid'); }
		if($observed['state']!=='committed' && !$this->ready()) { return $this->error('media_service_unavailable'); }
		return $this->ledger->locked(array('admission','submission:'.$id),function()use($id,$json,$manifest,$bearer,$repository,$canonical){
			$row=$this->ledger->submission($id);
			if(!$row || !$this->capability->verify($row,$bearer)) { return $this->error('media_capability_invalid'); }
			return $this->ledger->locked(array('session:'.$row['session_id']),function()use($row,$json,$manifest,$repository,$canonical){
				$row=$this->ledger->session($row['session_id']);$hash=hash('sha256',$json);$mh=hash('sha256',self::json($manifest));
				if(in_array($row['state'],array('pinned','binding','committed'),true)) {
					if(!hash_equals($row['canonical_hash'],$hash) || !hash_equals($row['manifest_hash'],$mh)) { return $this->error('media_manifest_conflict','media.items'); }
					if($row['state']!=='committed' && $this->now()>=(int)$row['commit_expires_at']) { return $this->error('media_session_expired'); }
					return $row;
				}
				if($row['state']!=='open' || $this->now()>=(int)$row['commit_expires_at']) { return $this->error('media_session_expired'); }
				if($repository) {
					$existing=$repository->find_by_native_submission($canonical['submission_id']);
					if($existing && $repository->payload_hash_for_report($existing) && !$repository->native_payload_matches_report($existing,$canonical)) { return $this->error('duplicate_submission','submission_id'); }
				}
				if (count($manifest)<1 || count($manifest)>BadAround_Native_Media_Config::constraints()['max_items']) { return $this->error('media_manifest_invalid','media.items'); }
				$seen=array();$bytes=0;
				foreach($manifest as $descriptor) {
					$uuid=$descriptor['media_id'];
					if(!BadAround_Native_Media_Config::uuid($uuid) || isset($seen[$uuid])) { return $this->error('media_manifest_invalid','media.items'); } $seen[$uuid]=true;
					$item=$this->ledger->item($uuid);
					if(!$item || $item['session_id']!==$row['session_id']) { return $this->error('media_reference_invalid','media.items'); }
					if($item['state']==='receiving') { return $this->error('media_upload_incomplete','media.items'); }
					if($item['state']!=='accepted_quarantined') { return $this->error('media_reference_invalid','media.items'); }
					if(count($descriptor)!==4 || self::descriptor($item)!=$descriptor) { return $this->error('media_descriptor_mismatch','media.items'); }
					if(!$this->storage->verify($item['storage_key'],$item['file_size'],$item['checksum'])) { return $this->error('media_binding_failed','media.items'); }
					$bytes+=(int)$item['file_size'];
				}
				// Unselected accepted uploads stay unbound; an active writer must finish first.
				$rows=$this->ledger->items($row['session_id']);
				if (!is_array($rows)) { return $this->error('media_service_unavailable'); }
				foreach($rows as $item) {
					if($item['state']==='receiving') { return $this->error('media_upload_incomplete','media.items'); }
				}
				$usage=$this->ledger->usage();
				if(!$usage || (int)$usage['archive_reserved']>(int)$this->config->get('archive_budget')-$bytes) { return $this->error('media_service_unavailable'); }
				$changes=array('state'=>'pinned','canonical_hash'=>$hash,'manifest_hash'=>$mh,'manifest_json'=>self::json($manifest),'intent_json'=>$json,'archive_reserved'=>$bytes,'lease_expires_at'=>$this->now()+(int)$this->config->get('lease_ttl'));
				if(!$this->ledger->update_session($row,$changes)) { return $this->error('media_service_unavailable'); }
				BadAround_Native_Media_Config::audit('manifest_pinned');return $this->ledger->session($row['session_id']);
			});
		});
	}
}
