<?php
/** Standalone, constrained GD worker; never a web endpoint. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
function ba_media_worker_error($code) { echo json_encode(array('ok'=>false,'code'=>$code)); exit(1); }
if (!extension_loaded('gd') || !function_exists('posix_setrlimit')) { ba_media_worker_error('media_decoder_unavailable'); }
if (!posix_setrlimit(POSIX_RLIMIT_AS,536870912,536870912)
	|| !posix_setrlimit(POSIX_RLIMIT_CPU,8,9)
	|| !posix_setrlimit(POSIX_RLIMIT_FSIZE,0,0)
	|| !posix_setrlimit(POSIX_RLIMIT_NOFILE,32,32)) { ba_media_worker_error('media_decoder_unavailable'); }
set_error_handler(function(){throw new RuntimeException('invalid_image');});
try {
	$input=json_decode(stream_get_contents(STDIN,8193),true);
	if (!is_array($input)) { ba_media_worker_error('media_image_invalid'); }
	if (isset($input['mode']) && $input['mode']==='capabilities') {
		$codecs=array();
		foreach(array('jpeg'=>IMG_JPG,'png'=>IMG_PNG,'webp'=>IMG_WEBP) as $codec=>$flag) {
			if (!(imagetypes()&$flag)) { continue; }
			$image=imagecreatetruecolor(1,1);
			ob_start(); $function='image'.$codec; $function($image);$binary=ob_get_clean();
			$check=imagecreatefromstring($binary);
			if ($check && imagesx($check)===1) { $codecs[]=$codec; }
			unset($check,$image);
		}
		echo json_encode(array('ok'=>true,'codecs'=>$codecs)); exit;
	}
	$path=isset($input['path'])?$input['path']:'';
	if (!is_string($path) || $path==='' || $path[0]!=='/' || is_link($path) || realpath($path)!==$path
		|| !is_file($path) || filesize($path)>5242880) { ba_media_worker_error('media_image_invalid'); }
	ini_set('open_basedir',dirname($path));
	$types=array('image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp');
	$mime=isset($input['mime'])?$input['mime']:'';
	if (!isset($types[$mime])) { ba_media_worker_error('media_image_invalid'); }
	$size=getimagesize($path);
	if (!$size || $size[0]>10000 || $size[1]>10000 || $size[1]<=0 || $size[0]>intdiv(25000000,$size[1])
		|| $size[0]!==$input['width'] || $size[1]!==$input['height'] || $size['mime']!==$mime) { ba_media_worker_error('media_image_invalid'); }
	$image=$types[$mime]($path);
	if (!$image || imagesx($image)!==$size[0] || imagesy($image)!==$size[1]) { ba_media_worker_error('media_image_invalid'); }
	unset($image);
	echo json_encode(array('ok'=>true,'width'=>$size[0],'height'=>$size[1]));
} catch (Throwable $e) { ba_media_worker_error('media_image_invalid'); }

