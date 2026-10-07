<?php
if (!defined('ABSPATH')) { exit; }

class BadAround_Native_Media_Cleanup {
	private $service;
	private $recovery_factory;
	public function __construct($service=null,$recovery_factory=null) { $this->service=$service ?: new BadAround_Native_Media_Service();$this->recovery_factory=$recovery_factory; }
	public function run($batch=100) {
		$s=$this->service;$l=$s->ledger;$now=$s->now();$batch=max(1,min(100,(int)$batch));
		if (!$s->config->enabled() || !$s->storage->root()) { return false; }
		foreach((array)$l->stale_operations($now,$batch) as $operation) {
			$l->locked(array('operation:'.$operation['operation_id']),function()use($l,$operation){ $l->release_operation($operation['operation_id']); });
		}
		foreach((array)$l->expired_candidates($now,$batch) as $candidate) {
			$l->locked(array('session:'.$candidate['session_id']),function()use($l,$s,$candidate,$now){
				$row=$l->session($candidate['session_id']);
				if(!$row || !in_array($row['state'],array('open','pinned','binding','committed'),true) || (int)$row['orphan_expires_at']>$now) { return; }
				$items=$l->items($row['session_id']);
				if (!is_array($items)) { return; }
				foreach($items as $item) {
					if(BadAround_Native_Media_Service::protects_item($row,$item['media_id']) || !empty($item['report_id']) || in_array($item['state'],array('binding','bound_pending_review'),true)) { continue; }
					$deleted=$l->locked(array('writer:'.$item['media_id']),function()use($s,$item){ return $s->storage->remove($item['storage_key']); });
					if(!is_bool($deleted)) { return; }
					if(!$l->update_item($item,array('state'=>'expired','storage_deleted'=>$deleted ? 1 : 0))) { return; }
				}
				if($row['state']==='open' && $l->update_session($row,array('state'=>'expired'))) { BadAround_Native_Media_Config::audit('session_expired'); }
			});
		}
		foreach((array)$l->interrupted_candidates($now,$batch) as $candidate) {
			$l->locked(array('session:'.$candidate['session_id'],'writer:'.$candidate['media_id']),function()use($l,$s,$candidate,$now){
				$row=$l->session($candidate['session_id']);$item=$l->item($candidate['media_id']);
				if(!$row || !$item || BadAround_Native_Media_Service::protects_item($row,$item['media_id']) || !empty($item['report_id']) || $item['state']!=='receiving' || (int)$item['lease_expires_at']>$now) { return; }
				$deleted=$s->storage->remove($item['storage_key']);$l->update_item($item,array('state'=>'invalid','storage_deleted'=>$deleted ? 1 : 0));
			});
		}
		foreach((array)$l->pending_deletions($batch) as $candidate) {
			$l->locked(array('session:'.$candidate['session_id'],'writer:'.$candidate['media_id']),function()use($l,$s,$candidate){
				$row=$l->session($candidate['session_id']);$item=$l->item($candidate['media_id']);
				if($row && $item && !BadAround_Native_Media_Service::protects_item($row,$item['media_id']) && empty($item['report_id']) && in_array($item['state'],array('invalid','expired','removed'),true) && $s->storage->remove($item['storage_key'])) { $l->update_item($item,array('storage_deleted'=>1)); }
			});
		}
		$l->cleanup_limits($now,$batch);return true;
	}
	/** Internal durable intent replay, never an anonymous expiry bypass. Bounded and locked by F1.3/F1.4. */
	public function recover($batch=10) {
		$s=$this->service;$l=$s->ledger;$count=0;
		if (!$s->ready()) { return 0; }
		foreach((array)$l->stale_intents($s->now(),max(1,min(25,(int)$batch))) as $row) {
			$canonical=json_decode($row['intent_json'],true);
			if(!is_array($canonical) || !hash_equals($row['canonical_hash'],hash('sha256',BadAround_Native_Media_Service::json($canonical)))) { BadAround_Native_Media_Config::audit('recovery_invariant_failed',0,'intent_corrupt');continue; }
			$repository=new BadAround_Report_Repository();
			$golden=$this->recovery_factory ? call_user_func($this->recovery_factory,$s,$repository) : new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$repository),new BadAround_Native_Media_Persistence($s,$repository),$repository);
			$result=$golden->submit($canonical);
			BadAround_Native_Media_Config::audit('binding_recovery',0,isset($result['status']) ? $result['status'] : 'failed');++$count;
		}
		return $count;
	}
	public static function register_hooks() {
		add_action('badaround_native_media_maintenance',static function(){ $c=new self();$c->run();$c->recover(); });
		add_action('init',static function(){
			if ((new BadAround_Native_Media_Config())->enabled() && !wp_next_scheduled('badaround_native_media_maintenance')) { wp_schedule_event(time()+3600,'hourly','badaround_native_media_maintenance'); }
		});
	}
}
