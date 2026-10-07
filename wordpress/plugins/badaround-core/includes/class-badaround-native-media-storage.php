<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** All keys are server generated. No URL, attachment or client path input. */
class BadAround_Native_Media_Storage {
	private $config;
	public function __construct($config) { $this->config = $config; }
	public function root() {
		$input = $this->config->get('private_root');
		if (!is_string($input) || '' === $input || $input[0] !== '/' || is_link($input)) { return false; }
		$root = realpath($input);
		if (!$root || !is_dir($root) || !is_writable($root) || rtrim($input,'/') !== $root) { return false; }
		$roots = array_merge($this->config->get('document_roots') ?: array(), array(ABSPATH));
		if (defined('WP_CONTENT_DIR')) { $roots[] = WP_CONTENT_DIR; }
		foreach ($roots as $public) {
			$public = realpath($public);
			if (!$public || $root === $public || strpos($root.'/', rtrim($public,'/').'/') === 0) { return false; }
		}
		// Reject symlink ancestors, not just a symlink final directory.
		$part = '';
		foreach (explode('/', trim($input,'/')) as $segment) {
			$part .= '/'.$segment;
			if (is_link($part)) { return false; }
		}
		if ((fileperms($root) & 0077) !== 0) { return false; }
		return $root;
	}
	public function path($key, $create_parent = false, $allow_missing = false) {
		$root = $this->root();
		if (!$root || !is_string($key) || !preg_match('#^(native-staging/[0-9a-f-]{36}/[0-9a-f-]{36}\\.bin|report-[1-9][0-9]*/[0-9a-f-]{36}\\.(jpg|jpeg|png|webp|heic))$#D', $key)) {
			return BadAround_Native_Media_Config::error('media_service_unavailable');
		}
		$segments = explode('/', $key); $file = array_pop($segments); $parent = $root;
		foreach ($segments as $segment) {
			$parent .= '/'.$segment;
			if (is_link($parent)) { return BadAround_Native_Media_Config::error('media_service_unavailable'); }
			if ($allow_missing && !file_exists($parent)) { continue; }
			if (!is_dir($parent) && (!$create_parent || !mkdir($parent,0700))) { return BadAround_Native_Media_Config::error('media_service_unavailable'); }
			if (realpath($parent) !== $parent || (fileperms($parent) & 0077) !== 0) { return BadAround_Native_Media_Config::error('media_service_unavailable'); }
		}
		$path = $parent.'/'.$file;
		if (is_link($path)) { return BadAround_Native_Media_Config::error('media_service_unavailable'); }
		return $path;
	}
	public function stage($session, $media, $stream, $on_bytes = null) {
		$key = 'native-staging/'.$session.'/'.$media.'.bin';
		$path = $this->path($key, true);
		if (is_wp_error($path)) { return $path; }
		$out = fopen($path, 'x+b');
		if (!$out) { return BadAround_Native_Media_Config::error('media_service_unavailable'); }
		if (!chmod($path, 0600)) { fclose($out); @unlink($path); return BadAround_Native_Media_Config::error('media_service_unavailable'); }
		$hash = hash_init('sha256'); $bytes = 0;
		try {
			while (!feof($stream)) {
				$chunk = fread($stream,65536);
				if (false === $chunk || ('' === $chunk && !feof($stream))) { throw new RuntimeException('media_binding_failed'); }
				$bytes += strlen($chunk);
				if ($on_bytes && false === call_user_func($on_bytes, strlen($chunk))) { throw new RuntimeException('media_rate_limited'); }
				if ($bytes > BadAround_Native_Media_Config::constraints()['max_bytes_per_item']) { throw new RuntimeException('media_file_too_large'); }
				hash_update($hash, $chunk);
				if (strlen($chunk) !== fwrite($out,$chunk)) { throw new RuntimeException('media_service_unavailable'); }
			}
			if (0 === $bytes) { throw new RuntimeException('media_image_invalid'); }
			if (!fflush($out) || (function_exists('fsync') && !fsync($out))) { throw new RuntimeException('media_service_unavailable'); }
			fclose($out);
			return array('storage_key'=>$key, 'file_size'=>$bytes, 'checksum'=>hash_final($hash));
		} catch (Throwable $e) {
			fclose($out); @unlink($path);
			return BadAround_Native_Media_Config::error($e->getMessage());
		}
	}
	public function verify($key, $bytes, $checksum) {
		$path = $this->path($key);
		return !is_wp_error($path) && is_file($path) && (int)filesize($path)===(int)$bytes
			&& hash_equals($checksum, hash_file('sha256',$path));
	}
	public function promote($item, $report_id) {
		if ((int)$report_id <= 0) { return BadAround_Native_Media_Config::error('media_binding_invariant_failed'); }
		$key = 'report-'.(int)$report_id.'/'.$item['media_id'].'.'.$item['extension'];
		$target = $this->path($key, true);
		if (is_wp_error($target)) { return $target; }
		if (is_file($target)) {
			return $this->verify($key,$item['file_size'],$item['checksum']) ? $key : BadAround_Native_Media_Config::error('media_binding_invariant_failed');
		}
		$source = $this->path($item['storage_key']);
		if (is_wp_error($source) || !$this->verify($item['storage_key'],$item['file_size'],$item['checksum'])) { return BadAround_Native_Media_Config::error('media_binding_failed'); }
		if (stat(dirname($source))['dev'] !== stat(dirname($target))['dev'] || !rename($source,$target)) { return BadAround_Native_Media_Config::error('media_binding_failed'); }
		if (!chmod($target,0600)) { return BadAround_Native_Media_Config::error('media_binding_failed'); }
		return $this->verify($key,$item['file_size'],$item['checksum']) ? $key : BadAround_Native_Media_Config::error('media_binding_failed');
	}
	public function remove($key) {
		$path = $this->path($key, false, true);
		if (is_wp_error($path)) { return false; }
		return !file_exists($path) || (is_file($path) && unlink($path));
	}
	public function free_capacity($bytes) {
		$root = $this->root();
		$free = $root ? disk_free_space($root) : false;
		return false !== $free && $free >= $bytes + (int)$this->config->get('reserve_free_bytes');
	}
}
