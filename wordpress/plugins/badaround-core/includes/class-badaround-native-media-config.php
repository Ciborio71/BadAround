<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Operational enablement is explicit; no secrets or assumed storage capacity. */
class BadAround_Native_Media_Config {
	private $settings;
	public function __construct( $settings = null ) {
		if ( null === $settings ) {
			$settings = defined( 'BADAROUND_NATIVE_MEDIA_CONFIG' ) ? BADAROUND_NATIVE_MEDIA_CONFIG : array();
		}
		$this->settings = array_merge( array(
			'enabled' => false, 'keys' => array(), 'active_key_id' => '',
			'private_root' => '', 'document_roots' => array(), 'hosting_verified' => false,
			'temporary_budget' => 0, 'archive_budget' => 0, 'reserve_free_bytes' => 16777216,
			'upload_ttl' => 7200, 'commit_ttl' => 86400, 'orphan_ttl' => 86400, 'lease_ttl' => 900,
			'max_sessions' => 1000, 'session_ip_limit' => 12, 'upload_ip_limit' => 30,
			'upload_session_limit' => 20, 'failed_ip_limit' => 10, 'failed_session_limit' => 5,
			'ip_bytes_limit' => 104857600, 'session_bytes_limit' => 104857600,
			'concurrent_session' => 2, 'concurrent_ip' => 2, 'window_seconds' => 900,
			'decoder_processes' => 1, 'max_session_records' => 10000,
		), is_array( $settings ) ? $settings : array() );
	}
	public function get( $key ) { return isset( $this->settings[$key] ) ? $this->settings[$key] : null; }
	public function key( $id ) {
		$keys = $this->get( 'keys' );
		$value = is_array( $keys ) && isset( $keys[$id] ) && is_string( $keys[$id] ) ? base64_decode( $keys[$id], true ) : false;
		return is_string( $value ) && strlen( $value ) >= 32 ? $value : false;
	}
	public function enabled() {
		$id = $this->get('active_key_id');
		return true === $this->get('enabled') && true === $this->get('hosting_verified')
			&& is_string($id) && preg_match('/^[a-zA-Z0-9_-]{1,32}$/D', $id) && $this->key($id)
			&& (int)$this->get('temporary_budget') > 0 && (int)$this->get('archive_budget') > 0
			&& is_string($this->get('private_root')) && '' !== $this->get('private_root')
			&& is_array($this->get('document_roots')) && count($this->get('document_roots')) > 0;
	}
	public static function constraints() {
		$field = BadAround_Report_Schema::fields()['media.items'];
		return $field['constraints'];
	}
	public static function uuid( $id ) {
		return is_string($id) && 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $id);
	}
	public static function new_uuid() {
		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 15) | 64);
		$bytes[8] = chr((ord($bytes[8]) & 63) | 128);
		$hex = bin2hex($bytes);
		return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
	}
	public static function error( $code, $field = null ) {
		return new WP_Error($code, $code, array('field' => $field));
	}
	public static function audit( $action, $object_id = 0, $reason = null ) {
		BadAround_Audit_Log::record('native_media', (int)$object_id, $action, 'native_media', $reason);
	}
}
