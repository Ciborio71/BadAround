<?php
/** Synthetic local HTTP fixture only. Never loaded by the application. */
require __DIR__.'/native-media-harness.php';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$method=$_SERVER['REQUEST_METHOD'];
$headers=function_exists('getallheaders') ? getallheaders() : array();
$params=array('files'=>$_FILES,'body'=>$_POST,'method'=>$method,'route'=>$path);
$api=new BadAround_Native_Media_REST_Controller($service);
if ($path==='/badaround/v1/report-media-sessions' && $method==='POST') { $handler='rest_create'; }
elseif (preg_match('#^/badaround/v1/report-media-sessions/([a-f0-9-]{36})(?:/items(?:/([a-f0-9-]{36}))?)?$#D',$path,$m)) {
 $params['session']=$m[1];$params['media']=$m[2] ?? null;
 $handler=isset($m[2]) ? 'rest_item' : (str_ends_with($path,'/items') ? 'rest_upload' : 'rest_status');
} else { http_response_code(404);exit; }
$request=new WP_REST_Request(file_get_contents('php://input'),$headers,$params);
$result=$api->$handler($request);
// Simulate the default REST reflected CORS; the native protocol must remove it.
header('Access-Control-Allow-Origin: *');header('Access-Control-Allow-Credentials: true');
$api->private_transport_headers(false,$result,$request,null);
http_response_code($result->status);header('Content-Type: application/json');
foreach($result->headers as $name=>$value)header($name.': '.$value);
echo json_encode($result->data);
