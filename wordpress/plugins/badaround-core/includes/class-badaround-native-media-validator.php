<?php
if ( ! defined('ABSPATH') ) { exit; }

/** GD-only isolated CLI worker: no ImageMagick/network delegates, no HEIC claims. */
class BadAround_Native_Media_Validator {
	private $config;
	private $verified_codecs=null;
	public function __construct($config) { $this->config=$config; }
	private function tool($setting, $fallback='') {
		$path=$this->config->get($setting) ?: $fallback;
		$real=is_string($path) ? realpath($path) : false;
		return $real && is_file($real) && (fileperms($real)&0022)===0 ? $real : false;
	}
	public function codecs() {
		if (is_array($this->verified_codecs)) { return $this->verified_codecs; }
		$out=$this->run(array('mode'=>'capabilities'));
		$this->verified_codecs=!is_wp_error($out) && isset($out['codecs']) && is_array($out['codecs']) ? $out['codecs'] : array();
		return $this->verified_codecs;
	}
	private function run($input) {
		if (!function_exists('proc_open') || !function_exists('proc_terminate')) { return BadAround_Native_Media_Config::error('media_decoder_unavailable'); }
		$cli=PHP_SAPI==='cli';
		$php=$this->tool('decoder_php_binary',$cli ? PHP_BINARY : '');
		$ext=ini_get('extension_dir');
		$gd=$this->tool('decoder_gd_extension',$cli ? $ext.'/gd.so' : '');
		$posix=$this->tool('decoder_posix_extension',$cli ? $ext.'/posix.so' : '');
		if (!$php || !$gd || !$posix || !is_executable($php)) { return BadAround_Native_Media_Config::error('media_decoder_unavailable'); }
		$worker=__DIR__.'/native-media-image-worker.php';
		$cmd=array($php,'-n','-d','extension='.$gd,'-d','extension='.$posix,'-d','memory_limit=256M',
			'-d','allow_url_fopen=0','-d','allow_url_include=0','-d','display_errors=0','-d','log_errors=0',
			'-d','disable_functions=exec,shell_exec,system,passthru,popen,proc_open,curl_exec,fsockopen,pfsockopen,stream_socket_client,stream_socket_server,socket_connect',
			$worker);
		$pipes=array();
		$environment=array();
		$libraries=$this->config->get('decoder_library_path');
		if ($libraries) {
			if (!is_string($libraries) || realpath($libraries)!==$libraries || !is_dir($libraries) || (fileperms($libraries)&0022)!==0) { return BadAround_Native_Media_Config::error('media_decoder_unavailable'); }
			$environment['LD_LIBRARY_PATH']=$libraries;
		}
		$process=@proc_open($cmd,array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes,__DIR__,$environment);
		if (!is_resource($process)) { return BadAround_Native_Media_Config::error('media_decoder_unavailable'); }
		$encoded=json_encode($input);$written=@fwrite($pipes[0],$encoded);fclose($pipes[0]);
		if($written!==strlen($encoded)) {
			proc_terminate($process,9);fclose($pipes[1]);fclose($pipes[2]);proc_close($process);
			return BadAround_Native_Media_Config::error('media_decoder_unavailable');
		}
		stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false);
		$output='';$error='';$deadline=microtime(true)+10;
		do {
			$output.=stream_get_contents($pipes[1]); $error.=stream_get_contents($pipes[2]);
			$status=proc_get_status($process);
			if (strlen($output)>8192 || strlen($error)>8192 || microtime(true)>$deadline) {
				proc_terminate($process,9); $output=''; break;
			}
			if (!$status['running']) { $output.=stream_get_contents($pipes[1]); break; }
			usleep(10000);
		} while(true);
		fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
		$result=json_decode($output,true);
		return is_array($result) && !empty($result['ok']) ? $result : BadAround_Native_Media_Config::error(
			isset($result['code']) && $result['code']==='media_image_invalid' ? 'media_image_invalid' : 'media_decoder_unavailable');
	}
	public function validate($path,$filename) {
		if (!is_string($filename) || strlen($filename)>255 || preg_match('#[\\x00-\\x1f\\x7f/\\\\:]#',$filename)
			|| preg_match('/\\.(php[0-9]*|phtml|phar|exe|js|html?|svg)(\\.|$)/i',$filename)) {
			return BadAround_Native_Media_Config::error('media_image_invalid');
		}
		$extension=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
		$constraints=BadAround_Native_Media_Config::constraints();
		if (!in_array($extension,$constraints['allowed_extensions'],true)) { return BadAround_Native_Media_Config::error('media_type_unsupported'); }
		$bytes=filesize($path);
		if ($bytes<=0 || $bytes>$constraints['max_bytes_per_item']) { return BadAround_Native_Media_Config::error('media_file_too_large'); }
		$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
		$types=array('image/jpeg'=>array('jpg','jpeg'),'image/png'=>array('png'),'image/webp'=>array('webp'));
		if (!isset($types[$mime]) || !in_array($extension,$types[$mime],true) || !in_array($mime,$constraints['allowed_mime'],true)) {
			return BadAround_Native_Media_Config::error('media_type_unsupported');
		}
		$data=file_get_contents($path);
		if ('image/jpeg'===$mime && !$this->static_jpeg($data)) { return BadAround_Native_Media_Config::error('media_image_invalid'); }
		if ('image/png'===$mime && !$this->static_png($data)) { return BadAround_Native_Media_Config::error('media_image_invalid'); }
		if ('image/webp'===$mime && !$this->static_webp($data)) { return BadAround_Native_Media_Config::error('media_image_invalid'); }
		$size=@getimagesize($path);
		if (!$size || $size[0]<=0 || $size[1]<=0 || $size[0]>10000 || $size[1]>10000 || $size[0]>intdiv(25000000,$size[1])) {
			return BadAround_Native_Media_Config::error('media_image_invalid');
		}
		$decoded=$this->run(array('mode'=>'decode','path'=>$path,'mime'=>$mime,'width'=>$size[0],'height'=>$size[1]));
		if (is_wp_error($decoded)) { return $decoded; }
		return array('mime_type'=>$mime,'extension'=>$extension,'file_size'=>$bytes,'width'=>$size[0],'height'=>$size[1]);
	}
	/** Walk segment/entropy boundaries; embedded EXIF thumbnails are not extra frames. */
	private function static_jpeg($data) {
		$end=strlen($data);$pos=2;$scan=false;$scans=0;
		if (substr($data,0,2)!=="\xff\xd8") { return false; }
		while ($pos<$end) {
			if ($scan) {
				$next=strpos($data,"\xff",$pos);
				if ($next===false) { return false; }
				$pos=$next;
			}
			if ($data[$pos]!=="\xff") { return false; }
			while ($pos<$end && $data[$pos]==="\xff") { ++$pos; }
			if ($pos>=$end) { return false; }
			$marker=ord($data[$pos++]);
			if ($scan && ($marker===0 || ($marker>=0xd0 && $marker<=0xd7))) { continue; }
			$scan=false;
			if ($marker===0xd9) { return $scans>0 && strspn($data,"\0 \r\n\t",$pos)===$end-$pos; }
			if ($marker===0 || $marker===0xd8 || ($marker>=0xd0 && $marker<=0xd7) || $pos+2>$end) { return false; }
			$length=unpack('n',substr($data,$pos,2))[1];
			if ($length<2 || $length>$end-$pos) { return false; }
			if ($marker===0xe2 && substr($data,$pos+2,4)==="MPF\0") { return false; }
			$pos+=$length;
			if ($marker===0xda) { $scan=true;++$scans; }
		}
		return false;
	}
	private function static_png($data) {
		if (substr($data,0,8)!=="\x89PNG\r\n\x1a\n") { return false; }
		$pos=8;$end=strlen($data);$finished=false;
		while ($pos+12<=$end) {
			$length=unpack('N',substr($data,$pos,4))[1]; $type=substr($data,$pos+4,4);
			if ($length>$end-$pos-12 || in_array($type,array('acTL','fcTL','fdAT'),true)) { return false; }
			$pos+=12+$length;
			if ($type==='IEND') { $finished=true;break; }
		}
		return $finished && $pos===$end;
	}
	private function static_webp($data) {
		$end=strlen($data);
		if ($end<12 || substr($data,0,4)!=='RIFF' || substr($data,8,4)!=='WEBP' || unpack('V',substr($data,4,4))[1]+8!==$end) { return false; }
		$pos=12;$images=0;
		while($pos+8<=$end) {
			$type=substr($data,$pos,4); $length=unpack('V',substr($data,$pos+4,4))[1];
			if ($length>$end-$pos-8 || $type==='ANIM' || $type==='ANMF') { return false; }
			if ($type==='VP8X' && ($length<10 || (ord($data[$pos+8])&2))) { return false; }
			if ($type==='VP8 ' || $type==='VP8L') { if (++$images>1) { return false; } }
			$pos+=8+$length+($length%2);
		}
		return $pos===$end && $images===1;
	}
}
