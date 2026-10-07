<?php
if (!defined('ABSPATH')) { exit; }

/** Durable native-only identities. All mutations use named locks plus UNIQUE keys. */
class BadAround_Native_Media_Ledger {
	private $db;
	public function __construct($db=null) { global $wpdb; $this->db=$db ?: $wpdb; }
	public function db() { return $this->db; }
	public function table($name) { return $this->db->prefix.'ba_native_media_'.$name; }
	public static function schema_sql($prefix,$collate) {
		$s=$prefix.'ba_native_media_sessions';$i=$prefix.'ba_native_media_items';
		$k=$prefix.'ba_native_media_upload_keys';$l=$prefix.'ba_native_media_limits';
		return "CREATE TABLE {$s} (
session_id char(36) NOT NULL,
submission_id char(36) NOT NULL,
creation_nonce_hash char(64) NOT NULL,
capability_hash char(64) NOT NULL,
key_id varchar(32) NOT NULL,
server_salt char(64) NOT NULL,
state varchar(32) NOT NULL DEFAULT 'open',
upload_expires_at bigint(20) unsigned NOT NULL,
commit_expires_at bigint(20) unsigned NOT NULL,
orphan_expires_at bigint(20) unsigned NOT NULL,
manifest_hash char(64) DEFAULT NULL,
manifest_json longtext DEFAULT NULL,
canonical_hash char(64) DEFAULT NULL,
intent_json longtext DEFAULT NULL,
archive_reserved bigint(20) unsigned NOT NULL DEFAULT 0,
receipt_json longtext DEFAULT NULL,
report_id bigint(20) unsigned DEFAULT NULL,
event_id bigint(20) unsigned DEFAULT NULL,
attempts bigint(20) unsigned NOT NULL DEFAULT 0,
failed_validations bigint(20) unsigned NOT NULL DEFAULT 0,
received_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
lease_expires_at bigint(20) unsigned DEFAULT NULL,
version bigint(20) unsigned NOT NULL DEFAULT 1,
created_at bigint(20) unsigned NOT NULL,
updated_at bigint(20) unsigned NOT NULL,
PRIMARY KEY  (session_id),
UNIQUE KEY submission_id (submission_id),
KEY state_expiry (state,orphan_expires_at)
) {$collate};
CREATE TABLE {$i} (
media_id char(36) NOT NULL,
session_id char(36) NOT NULL,
client_upload_id char(36) NOT NULL,
checksum char(64) DEFAULT NULL,
file_size bigint(20) unsigned NOT NULL DEFAULT 0,
mime_type varchar(64) DEFAULT NULL,
extension varchar(8) DEFAULT NULL,
width int unsigned DEFAULT NULL,
height int unsigned DEFAULT NULL,
storage_key varchar(255) NOT NULL,
promotion_target varchar(255) DEFAULT NULL,
state varchar(32) NOT NULL DEFAULT 'receiving',
ip_hash char(64) NOT NULL,
storage_deleted tinyint(1) NOT NULL DEFAULT 0,
lease_expires_at bigint(20) unsigned DEFAULT NULL,
report_media_id bigint(20) unsigned DEFAULT NULL,
report_id bigint(20) unsigned DEFAULT NULL,
event_id bigint(20) unsigned DEFAULT NULL,
version bigint(20) unsigned NOT NULL DEFAULT 1,
created_at bigint(20) unsigned NOT NULL,
updated_at bigint(20) unsigned NOT NULL,
PRIMARY KEY  (media_id),
UNIQUE KEY session_client (session_id,client_upload_id),
UNIQUE KEY final_media_row (report_media_id),
KEY session_state (session_id,state),
KEY ip_lease (ip_hash,state,lease_expires_at),
KEY report_id (report_id),
KEY event_id (event_id)
) {$collate};
CREATE TABLE {$k} (
session_id char(36) NOT NULL,
client_upload_id char(36) NOT NULL,
media_id char(36) NOT NULL,
created_at bigint(20) unsigned NOT NULL,
PRIMARY KEY  (session_id,client_upload_id),
KEY media_id (media_id)
) {$collate};
CREATE TABLE {$l} (
limit_key char(64) NOT NULL,
hits bigint(20) unsigned NOT NULL DEFAULT 0,
bytes bigint(20) unsigned NOT NULL DEFAULT 0,
expires_at bigint(20) unsigned NOT NULL,
PRIMARY KEY  (limit_key),
KEY expires_at (expires_at)
) {$collate};";
	}
	public function locked($names,$callback) {
		$held=array();
		try {
			foreach($names as $name) {
				$key='ba_media_'.substr(hash('sha256',$name),0,48);
				if (1!==(int)$this->db->get_var($this->db->prepare('SELECT GET_LOCK(%s,0)',$key))) {
					return BadAround_Native_Media_Config::error('media_commit_in_progress');
				}
				$held[]=$key;
			}
			return $callback();
		} finally {
			foreach(array_reverse($held) as $key) { $this->db->get_var($this->db->prepare('SELECT RELEASE_LOCK(%s)',$key)); }
		}
	}
	public function session($id) {
		return $this->db->get_row($this->db->prepare("SELECT * FROM {$this->table('sessions')} WHERE session_id=%s",$id),ARRAY_A);
	}
	public function submission($id) {
		return $this->db->get_row($this->db->prepare("SELECT * FROM {$this->table('sessions')} WHERE submission_id=%s",$id),ARRAY_A);
	}
	public function item($id) {
		return $this->db->get_row($this->db->prepare("SELECT * FROM {$this->table('items')} WHERE media_id=%s",$id),ARRAY_A);
	}
	public function upload_key($session,$key) {
		$alias=$this->db->get_var($this->db->prepare("SELECT media_id FROM {$this->table('upload_keys')} WHERE session_id=%s AND client_upload_id=%s",$session,$key));
		if ($alias) { return $this->item($alias); }
		return $this->db->get_row($this->db->prepare("SELECT * FROM {$this->table('items')} WHERE session_id=%s AND client_upload_id=%s",$session,$key),ARRAY_A);
	}
	public function alias($session,$key,$media,$now) {
		$table=$this->table('upload_keys');
		$existing=$this->db->get_var($this->db->prepare("SELECT media_id FROM {$table} WHERE session_id=%s AND client_upload_id=%s",$session,$key));
		if ($existing) { return hash_equals($existing,$media); }
		return false!==$this->db->insert($table,array('session_id'=>$session,'client_upload_id'=>$key,'media_id'=>$media,'created_at'=>$now));
	}
	public function items($session) {
		return $this->db->get_results($this->db->prepare("SELECT * FROM {$this->table('items')} WHERE session_id=%s ORDER BY media_id",$session),ARRAY_A);
	}
	public function accepted_checksum($session,$checksum,$exclude) {
		return $this->db->get_row($this->db->prepare("SELECT * FROM {$this->table('items')} WHERE session_id=%s AND checksum=%s AND media_id<>%s AND state='accepted_quarantined' AND storage_deleted=0 LIMIT 1",$session,$checksum,$exclude),ARRAY_A);
	}
	public function insert_session($row) { return false!==$this->db->insert($this->table('sessions'),$row); }
	public function insert_item($row) { return false!==$this->db->insert($this->table('items'),$row); }
	public function update_session($row,$changes) { return $this->update('sessions','session_id',$row,$changes); }
	public function update_item($row,$changes) { return $this->update('items','media_id',$row,$changes); }
	private function update($table,$identity,$row,$changes) {
		$changes['version']=(int)$row['version']+1;$changes['updated_at']=time();
		return 1===$this->db->update($this->table($table),$changes,array($identity=>$row[$identity],'version'=>(int)$row['version']));
	}
	public function usage() {
		$row=$this->db->get_row("SELECT
COALESCE(SUM(CASE WHEN state<>'bound_pending_review' AND storage_deleted=0 THEN file_size ELSE 0 END),0) AS temporary_bytes,
COALESCE(SUM(CASE WHEN state='bound_pending_review' AND storage_deleted=0 THEN file_size ELSE 0 END),0) AS archive_bytes
FROM {$this->table('items')}",ARRAY_A);
		$sessions=$this->db->get_row("SELECT COUNT(*) AS records, SUM(CASE WHEN state IN ('open','pinned','binding') THEN 1 ELSE 0 END) AS active, COALESCE(SUM(archive_reserved),0) AS archive_reserved FROM {$this->table('sessions')}",ARRAY_A);
		return $row && $sessions ? array_merge($row,$sessions) : false;
	}
	public function active_ip($ip,$now) {
		return (int)$this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM {$this->table('items')} WHERE ip_hash=%s AND state='receiving' AND lease_expires_at>%d",$ip,$now));
	}
	public function consume($scope,$counter,$amount,$limit,$window,$now) {
		if (!in_array($counter,array('hits','bytes'),true) || $amount<0 || $limit<=0 || $window<=0) { return false; }
		$key=hash('sha256',$scope.':'.intdiv($now,$window));
		$out=$this->locked(array('limit:'.$key),function()use($key,$counter,$amount,$limit,$window,$now){
			$table=$this->table('limits');
			$row=$this->db->get_row($this->db->prepare("SELECT * FROM {$table} WHERE limit_key=%s",$key),ARRAY_A);
			$current=$row ? (int)$row[$counter] : 0;
			if ($amount===0 && $current>=$limit) { return false; }
			if ($current>$limit-$amount) { return false; }
			if (!$row) {
				return false!==$this->db->insert($table,array('limit_key'=>$key,$counter=>$amount,'expires_at'=>(intdiv($now,$window)+1)*$window));
			}
			return false!==$this->db->update($table,array($counter=>$current+$amount),array('limit_key'=>$key));
		});
		return true===$out;
	}
	public function final_row($id) {
		return $this->db->get_row($this->db->prepare("SELECT * FROM {$this->db->prefix}ba_report_media WHERE id=%d",(int)$id),ARRAY_A);
	}
	public function final_candidates($report,$basename) {
		return $this->db->get_results($this->db->prepare("SELECT * FROM {$this->db->prefix}ba_report_media WHERE report_id=%d AND original_storage_key=%s AND deleted_at IS NULL",(int)$report,$basename),ARRAY_A);
	}
	public function create_final($report,$item,$basename) {
		if ((int)$report<=0) { return false; }
		$ok=$this->db->insert($this->db->prefix.'ba_report_media',array(
			'report_id'=>(int)$report,'original_storage_key'=>$basename,'original_filename'=>$item['media_id'].'.'.$item['extension'],
			'mime_type'=>$item['mime_type'],'file_size'=>(int)$item['file_size'],'checksum'=>$item['checksum'],
			'media_type'=>'image','review_status'=>'pending_native_review','sensitivity'=>'private',
			'created_at'=>gmdate('Y-m-d H:i:s')
		));
		return false!==$ok ? (int)$this->db->insert_id : false;
	}
	public function final_set($report) {
		return $this->db->get_results($this->db->prepare("SELECT * FROM {$this->db->prefix}ba_report_media WHERE report_id=%d AND deleted_at IS NULL ORDER BY id",(int)$report),ARRAY_A);
	}
	public function expired_candidates($now,$limit=100) {
		return $this->db->get_results($this->db->prepare("SELECT * FROM {$this->table('sessions')} WHERE state='open' AND orphan_expires_at<=%d ORDER BY orphan_expires_at LIMIT %d",$now,$limit),ARRAY_A);
	}
	public function interrupted_candidates($now,$limit=100) {
		return $this->db->get_results($this->db->prepare("SELECT i.* FROM {$this->table('items')} i INNER JOIN {$this->table('sessions')} s ON i.session_id=s.session_id WHERE s.state='open' AND i.state='receiving' AND i.lease_expires_at<=%d ORDER BY i.lease_expires_at LIMIT %d",$now,$limit),ARRAY_A);
	}
	public function pending_deletions($limit=100) {
		return $this->db->get_results($this->db->prepare("SELECT i.* FROM {$this->table('items')} i INNER JOIN {$this->table('sessions')} s ON i.session_id=s.session_id WHERE i.state IN ('removed','invalid','expired') AND i.storage_deleted=0 AND s.state IN ('open','expired') LIMIT %d",$limit),ARRAY_A);
	}
	public function stale_intents($now,$limit=25) {
		return $this->db->get_results($this->db->prepare("SELECT * FROM {$this->table('sessions')} WHERE state IN ('pinned','binding') AND lease_expires_at<=%d ORDER BY updated_at LIMIT %d",$now,$limit),ARRAY_A);
	}
	public function cleanup_limits($now,$limit=100) {
		return $this->db->query($this->db->prepare("DELETE FROM {$this->table('limits')} WHERE expires_at<%d LIMIT %d",$now,$limit));
	}
}
