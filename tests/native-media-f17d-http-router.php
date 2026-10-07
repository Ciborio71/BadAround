<?php
/** Isolated F1.7D HTTP integration only. No WordPress/live configuration. */
if (parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)!=='/badaround/v1/reports') { require __DIR__.'/native-media-http-router.php';exit; }
require __DIR__.'/native-media-harness.php';
$state=getenv('BA_TEST_HTTP_STATE');
$GLOBALS['posts']=is_file($state) ? json_decode(file_get_contents($state),true) : array();
$repository=new BadAround_Report_Repository();
$linker=new BadAround_Native_Media_Linker($ledger);
$frozen=new BadAround_Report_Persistence_Service($repository,new TestTax(),new TestTerritory(),$linker,new TestProjection());
$media=new BadAround_Native_Media_Persistence($service,$repository,$frozen,$linker);
$gold=new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$repository),$media,$repository);
$api=new BadAround_Native_Report_REST_Controller($gold,fn()=>new BadAround_Native_Media_Report_Adapter($service,$repository,$gold));
$request=new WP_REST_Request(file_get_contents('php://input'),getallheaders(),array('method'=>$_SERVER['REQUEST_METHOD'],'route'=>'/badaround/v1/reports'));
$response=$api->rest_create($request);
file_put_contents($state,json_encode($GLOBALS['posts']),LOCK_EX);chmod($state,0600);
http_response_code($response->status);header('Content-Type: application/json');
foreach($response->headers as $name=>$value)header($name.': '.$value);
echo json_encode($response->data);
