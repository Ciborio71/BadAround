<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Native_Media_Capability {
	private $config;
	public function __construct($config) { $this->config = $config; }
	public static function encode($bytes) { return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='); }
	public static function canonical_secret($value) {
		if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $value)) { return false; }
		$bytes = base64_decode(strtr($value, '-_', '+/').'=', true);
		return is_string($bytes) && strlen($bytes) === 32 && hash_equals(self::encode($bytes), $value);
	}
	public static function verifier($value) { return hash('sha256', $value); }
	public function issue($session) {
		if (in_array($session['key_id'],(array)$this->config->get('revoked_key_ids'),true)) { return BadAround_Native_Media_Config::error('media_capability_invalid'); }
		$key = $this->config->key($session['key_id']);
		if (!$key) { return BadAround_Native_Media_Config::error('media_service_unavailable'); }
		$context = array(
			'badaround-native-media-capability/v1', 'upload-remove-status-commit',
			$session['key_id'], $session['session_id'], $session['submission_id'],
			$session['creation_nonce_hash'], $session['server_salt'],
			(int)$session['upload_expires_at'], (int)$session['commit_expires_at'], (int)$session['orphan_expires_at'],
		);
		return self::encode(hash_hmac('sha256', json_encode($context, JSON_UNESCAPED_SLASHES), $key, true));
	}
	public function verify($session, $bearer) {
		return !in_array($session['key_id'],(array)$this->config->get('revoked_key_ids'),true) && self::canonical_secret($bearer) && isset($session['capability_hash'])
			&& hash_equals($session['capability_hash'], self::verifier($bearer));
	}
}
