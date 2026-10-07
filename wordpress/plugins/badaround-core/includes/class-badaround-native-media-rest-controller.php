<?php
if (!defined('ABSPATH')) { exit; }

class BadAround_Native_Media_REST_Controller {
	const MARKER='badaround-report-media/v1';
	private $service;
	public function __construct($service=null) { $this->service=$service; }
	public function register_hooks() {
		add_action('rest_api_init',array($this,'register_rest_routes'));
		add_filter('rest_pre_serve_request',array($this,'private_transport_headers'),20,4);
	}
	/** Remove WordPress's reflected CORS on this private protocol, including OPTIONS/errors. */
	public function private_transport_headers($served,$result,$request,$server) {
		$route=$request->get_route();
		if ($route==='/badaround/v1/report-media-sessions' || strpos($route,'/badaround/v1/report-media-sessions/')===0) {
			header_remove('Access-Control-Allow-Origin');
			header_remove('Access-Control-Allow-Credentials');
			header_remove('Access-Control-Allow-Methods');
			header_remove('Access-Control-Allow-Headers');
			header('Cache-Control: no-store, private');
			header('X-Content-Type-Options: nosniff');
		}
		return $served;
	}
	public function register_rest_routes() {
		$id='(?P<session>[0-9a-f-]{36})';$media='(?P<media>[0-9a-f-]{36})';
		foreach(array('/report-media-sessions'=>array('POST','create'),'/report-media-sessions/'.$id=>array('GET','status'),'/report-media-sessions/'.$id.'/items'=>array('POST','upload'),'/report-media-sessions/'.$id.'/items/'.$media=>array(array('GET','DELETE'),'item')) as $route=>$handler) {
			register_rest_route('badaround/v1',$route,array('methods'=>$handler[0],'callback'=>array($this,'rest_'.$handler[1]),'permission_callback'=>'__return_true'));
		}
	}
	public static function status_code($code) {
		$map=array('media_capability_invalid'=>401,'media_session_expired'=>410,'media_reference_invalid'=>404,'media_descriptor_mismatch'=>422,
			'media_upload_incomplete'=>409,'media_commit_in_progress'=>409,'media_binding_failed'=>500,'media_manifest_invalid'=>422,
			'media_manifest_conflict'=>409,'media_binding_invariant_failed'=>500,'media_rate_limited'=>429,'media_service_unavailable'=>503,
			'media_file_too_large'=>413,'media_type_unsupported'=>415,'media_image_invalid'=>422,'media_decoder_unavailable'=>503);
		return isset($map[$code]) ? $map[$code] : 500;
	}
	public static function retryable($code) { return in_array($code,array('media_upload_incomplete','media_commit_in_progress','media_binding_failed','media_rate_limited','media_service_unavailable','media_decoder_unavailable'),true); }
	public static function report_error($code) {
		return in_array($code,array('media_capability_invalid','media_session_expired','media_reference_invalid','media_descriptor_mismatch','media_upload_incomplete','media_commit_in_progress','media_binding_failed','media_manifest_invalid','media_manifest_conflict','media_binding_invariant_failed','media_rate_limited','media_service_unavailable'),true);
	}
	private function response($result,$success=200) {
		if(is_wp_error($result)) {
			$code=$result->get_error_code();$data=$result->get_error_data();
			$status=self::status_code($code);$body=array('status'=>'error','error'=>array('code'=>$code,'field'=>isset($data['field']) ? $data['field'] : null,'message_key'=>$code,'retryable'=>self::retryable($code)));
		} else { $body=array_merge(array('status'=>'success'),$result);$status=$success; }
		$response=new WP_REST_Response($body,$status);
		$response->header('Cache-Control','no-store, private');$response->header('X-Content-Type-Options','nosniff');$response->header('X-BadAround-Request-ID',wp_generate_uuid4());
		if($status===429) { $response->header('Retry-After','900'); }
		return $response;
	}
	private function integrity($request) {
		$home=wp_parse_url(home_url('/'));
		if(!is_ssl() || !isset($home['scheme']) || $home['scheme']!=='https' || trim((string)$request->get_header('x-badaround-media'))!==self::MARKER) { return BadAround_Native_Media_Config::error('media_capability_invalid'); }
		$site=strtolower(trim((string)$request->get_header('sec-fetch-site')));
		if($site!=='' && $site!=='same-origin' && $site!=='none') { return BadAround_Native_Media_Config::error('media_capability_invalid'); }
		$origin=trim((string)$request->get_header('origin'));
		if($origin!=='') {
			$o=wp_parse_url($origin);
			if(!$o || isset($o['user']) || isset($o['query']) || isset($o['fragment']) || (isset($o['path']) && $o['path']!=='' && $o['path']!=='/') || !isset($o['scheme'],$o['host']) || strtolower($o['scheme'])!=='https' || strtolower($o['host'])!==strtolower($home['host']) || (isset($o['port']) ? (int)$o['port'] : 443)!==(isset($home['port']) ? (int)$home['port'] : 443)) { return BadAround_Native_Media_Config::error('media_capability_invalid'); }
		}
		return true;
	}
	private function invoke($request,$operation) {
		$valid=$this->integrity($request);if(is_wp_error($valid)) { return $this->response($valid); }
		$s=$this->service ?: new BadAround_Native_Media_Service();
		if(!$s->config->enabled()) { return $this->response(BadAround_Native_Media_Config::error('media_service_unavailable')); }
		$ip=hash_hmac('sha256',isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : 'unknown',wp_salt('nonce'));
		$bearer=$request->get_header('x-badaround-media-capability');
		try {
			if($operation==='create') {
				if(strtolower(trim(explode(';',(string)$request->get_header('content-type'))[0]))!=='application/json' || strlen((string)$request->get_body())>1024) { return $this->response(BadAround_Native_Media_Config::error('media_manifest_invalid')); }
				$body=json_decode($request->get_body(),true);
				if(!is_array($body) || array_diff(array_keys($body),array('submission_id','creation_nonce')) || !isset($body['submission_id'],$body['creation_nonce'])) { return $this->response(BadAround_Native_Media_Config::error('media_manifest_invalid')); }
				$result=$s->create($body['submission_id'],$body['creation_nonce'],$ip);
				return $this->response($result,!is_wp_error($result) && empty($result['duplicate']) ? 201 : 200);
			}
			if($operation==='status') { return $this->response($s->status($request['session'],$bearer,$request['media'])); }
			if($operation==='remove') { return $this->response($s->remove($request['session'],$request['media'],$bearer)); }
			$files=$request->get_file_params();$params=$request->get_body_params();
			if(stripos((string)$request->get_header('content-type'),'multipart/form-data;')!==0 || array_keys($files)!==array('file') || array_keys($params)!==array('client_upload_id')) { return $this->response(BadAround_Native_Media_Config::error('media_manifest_invalid')); }
			$file=$files['file'];
			if(!is_array($file) || !isset($file['error'],$file['tmp_name'],$file['name']) || !is_string($file['tmp_name']) || !is_string($file['name'])) { return $this->response(BadAround_Native_Media_Config::error('media_manifest_invalid')); }
			if($file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) { return $this->response(BadAround_Native_Media_Config::error(in_array($file['error'],array(UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE),true) ? 'media_file_too_large' : 'media_manifest_invalid')); }
			$stream=fopen($file['tmp_name'],'rb');if(!$stream) { return $this->response(BadAround_Native_Media_Config::error('media_service_unavailable')); }
			try { $result=$s->upload($request['session'],$params['client_upload_id'],$bearer,$stream,$file['name'],$ip); }
			finally { fclose($stream); }
			return $this->response($result,!is_wp_error($result) && empty($result['duplicate']) ? 201 : 200);
		} catch(Throwable $e) { BadAround_Native_Media_Config::audit('media_api_failed',0,'unexpected_exception');return $this->response(BadAround_Native_Media_Config::error('media_service_unavailable')); }
	}
	public function rest_create($r) { return $this->invoke($r,'create'); }
	public function rest_upload($r) { return $this->invoke($r,'upload'); }
	public function rest_status($r) { return $this->invoke($r,'status'); }
	public function rest_remove($r) { return $this->invoke($r,'remove'); }
	public function rest_item($r) { return $this->invoke($r,$r->get_method()==='DELETE' ? 'remove' : 'status'); }
}
