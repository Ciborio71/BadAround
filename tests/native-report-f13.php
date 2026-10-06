<?php
/**
 * F1.3 Native Validation & Intake Foundation tests without WordPress/WPForms.
 */
define( 'ABSPATH', __DIR__ . '/' );

function __( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/\s+/u', ' ', strip_tags( (string) $value ) ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return false !== filter_var( (string) $value, FILTER_VALIDATE_EMAIL ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_time( $type, $gmt = false ) { return 'timestamp' === $type ? strtotime( '2026-10-06 12:00:00 UTC' ) : '2026-10-06 12:00:00'; }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ?: strtotime( '2026-10-06 12:00:00 UTC' ) ); }
function wp_generate_uuid4() { static $i=0; $i++; return sprintf('aaaaaaaa-aaaa-4aaa-8aaa-%012d',$i); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

class WP_Error {
	private $code; private $message; private $data;
	public function __construct( $code, $message = '', $data = null ) { $this->code=$code; $this->message=$message; $this->data=$data; }
	public function get_error_code(){ return $this->code; }
	public function get_error_message(){ return $this->message; }
	public function get_error_data(){ return $this->data; }
}

class BadAround_Audit_Log {
	public static $records=array();
	public static function record(){ self::$records[]=func_get_args(); return true; }
	public static function technical_error(){ self::$records[]=func_get_args(); return true; }
}

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-schema.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-normalizer.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-validator.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-result.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-idempotency-service.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-native-report-intake-service.php';

function f13_assert($ok,$message){ if(!$ok){ fwrite(STDERR,"FAIL: {$message}\n"); exit(1);} echo "PASS: {$message}\n"; }

function f13_fixture(){
	return array(
		'schema_version'=>'badaround-report/v1',
		'submission_id'=>'123e4567-e89b-42d3-a456-426614174000',
		'event'=>array('category'=>'hazard','subtype'=>'hazard_road_obstruction'),
		'reporter'=>array(
			'relationship'=>'territory_observer','first_name'=>' Mario ','last_name'=>' Rossi ',
			'email'=>'MARIO@example.invalid','phone'=>'+39 333 1234567',
			'public_identity_mode'=>'anonymous','contact_preference'=>'none'
		),
		'location'=>array(
			'exact_address'=>'Via Roma 10, Pomezia','exact_lat'=>41.669,'exact_lng'=>12.501,
			'place_id'=>'place-test','area_label'=>'Pomezia','public_precision'=>'area',
			'place_type'=>'road_sidewalk','immediate_danger'=>'no'
		),
		'time'=>array('mode'=>'exact','date'=>'2026-10-01','knowledge'=>'unknown'),
		'content'=>array('description'=>'Ostacolo presente sulla carreggiata.'),
		'damage'=>array('status'=>'no'),
		'authority'=>array('status'=>'no'),
		'media'=>array('availability'=>'no','items'=>array()),
		'reward'=>array('status'=>'none'),
		'consents'=>array(
			'truthfulness'=>true,'media_rights'=>true,'publication_rules'=>true,
			'terms'=>true,'privacy'=>true,'version'=>'native-v1'
		),
	);
}

class F13_Fake_Repository {
	public $report_exists=false;
	public $report_id=77;
	public $status='';
	public $locked=false;
	public $force_lock_fail=false;
	public $create_count=0;
	public $persist_count=0;
	public $stored=null;
	public $fail_next_persist=false;

	public function acquire_native_lock($submission_id,$timeout=0){
		if($this->force_lock_fail || $this->locked) return false;
		$this->locked=true; return true;
	}
	public function release_native_lock($submission_id){ $this->locked=false; }
	public function find_by_native_submission($submission_id){ return $this->report_exists ? $this->report_id : 0; }
	public function create_native_intake_record($submission_id){
		if(!$this->report_exists){ $this->report_exists=true; $this->create_count++; $this->status='received'; }
		return $this->report_id;
	}
	public function intake_status($report_id){ return $this->status; }
	public function mark_intake_status($report_id,$status){ $this->status=$status; return true; }
	public function payload_hash_for_report($report_id){ return null===$this->stored ? '' : hash('sha256',json_encode($this->stored)); }
	public function native_payload_matches_report($report_id,$canonical){ return $this->stored === $canonical; }
	public function persist_canonical_private_data($report_id,$canonical,$payload=null){
		$this->persist_count++;
		if($this->fail_next_persist){ $this->fail_next_persist=false; return false; }
		$this->stored=$canonical; return true;
	}
}

function f13_service($repo){
	$idempotency=new BadAround_Report_Idempotency_Service($repo);
	return new BadAround_Native_Report_Intake_Service(
		new BadAround_Report_Normalizer(),
		new BadAround_Report_Validator(),
		$repo,
		$idempotency
	);
}

$validator=new BadAround_Report_Validator();
$normalizer=new BadAround_Report_Normalizer();

$valid=f13_fixture();
$raw=$validator->validate_raw_contract($valid);
f13_assert(true===$raw,'valid raw payload passes contract preflight');
$n1=$normalizer->normalize($valid);
$n2=$normalizer->normalize($valid);
f13_assert($n1===$n2,'normalization is deterministic');
f13_assert('Mario'===$n1['reporter']['first_name'] && 'mario@example.invalid'===$n1['reporter']['email'],'string/email normalization follows F1.2');
f13_assert(true===$validator->validate($n1),'normalized valid payload passes validator');

$bad=f13_fixture(); $bad['schema_version']='badaround-report/v2';
$r=BadAround_Report_Result::from_wp_error($validator->validate($normalizer->normalize($bad)));
f13_assert('invalid_schema_version'===$r['error']['code'],'unknown schema version rejected');

$bad=f13_fixture(); $bad['submission_id']='not-a-uuid';
$r=BadAround_Report_Result::from_wp_error($validator->validate($normalizer->normalize($bad)));
f13_assert('invalid_submission_id'===$r['error']['code'],'malformed UUID rejected');

$bad=f13_fixture(); unset($bad['reporter']['email']);
$r=BadAround_Report_Result::from_wp_error($validator->validate($normalizer->normalize($bad)));
f13_assert('missing_required_field'===$r['error']['code'],'missing required field rejected');

$bad=f13_fixture(); $bad['reporter']['first_name']=array('wrong');
$r=BadAround_Report_Result::from_wp_error($validator->validate_raw_contract($bad));
f13_assert('invalid_type'===$r['error']['code'],'invalid raw type rejected');

$bad=f13_fixture(); $bad['event']['category']='bogus';
$r=BadAround_Report_Result::from_wp_error($validator->validate($normalizer->normalize($bad)));
f13_assert('invalid_enum'===$r['error']['code'],'invalid enum rejected');

$bad=f13_fixture(); $bad['event']['subtype']='vehicle_stolen';
$r=BadAround_Report_Result::from_wp_error($validator->validate($normalizer->normalize($bad)));
f13_assert('invalid_category_subtype'===$r['error']['code'],'invalid category/subtype pair rejected');

$bad=f13_fixture(); $bad['reward']=array('status'=>'fixed','conditions'=>'Restituzione verificata','expires_on'=>'2026-11-01','confirmed'=>true);
$r=BadAround_Report_Result::from_wp_error($validator->validate($normalizer->normalize($bad)));
f13_assert('conditional_field_required'===$r['error']['code'],'conditional required field enforced');

$bad=f13_fixture(); $bad['client_public_override']=true;
$r=BadAround_Report_Result::from_wp_error($validator->validate_raw_contract($bad));
f13_assert('unknown_field'===$r['error']['code'],'unknown domain field rejected');

$bad=f13_fixture(); $bad['reporter']['email']='invalid-email';
$r=BadAround_Report_Result::from_wp_error($validator->validate($normalizer->normalize($bad)));
f13_assert('invalid_email'===$r['error']['code'],'invalid email rejected');

$bad=f13_fixture(); $bad['time']['date']='not-a-date';
$r=BadAround_Report_Result::from_wp_error($validator->validate($normalizer->normalize($bad)));
f13_assert('invalid_date_time'===$r['error']['code'],'invalid date rejected');

$bad=f13_fixture(); unset($bad['location']['exact_lng']);
$r=BadAround_Report_Result::from_wp_error($validator->validate($normalizer->normalize($bad)));
f13_assert('invalid_location'===$r['error']['code'],'incomplete coordinate pair rejected');

$item=array('media_id'=>'m1','mime_type'=>'image/jpeg','file_size'=>1000,'extension'=>'jpg');
$bad=f13_fixture(); $bad['media']['items']=array($item,$item,$item,$item,$item,$item);
$r=BadAround_Report_Result::from_wp_error($validator->validate($normalizer->normalize($bad)));
f13_assert('media_limit_exceeded'===$r['error']['code'],'media count over five rejected');

$bad=f13_fixture(); $large=$item; $large['file_size']=5242881; $bad['media']['items']=array($large);
$r=BadAround_Report_Result::from_wp_error($validator->validate($normalizer->normalize($bad)));
f13_assert('media_limit_exceeded'===$r['error']['code'],'media over 5 MB rejected');

$repo=new F13_Fake_Repository();
$service=f13_service($repo);
$first=$service->intake(f13_fixture());
f13_assert('success'===$first['status'] && false===$first['duplicate'],'first native intake accepted');
f13_assert(1===$repo->create_count && 1===$repo->persist_count,'first run creates exactly one report record');
f13_assert('validated'===$repo->status,'successful intake reaches validated state');
f13_assert(isset($first['privacy']['location.exact_address']) && 'private'===$first['privacy']['location.exact_address']['privacy'],'privacy classification retained in intake result');

$second=$service->intake(f13_fixture());
f13_assert('success'===$second['status'] && true===$second['duplicate'],'double click/retry after completion is idempotent');
f13_assert(1===$repo->create_count && 1===$repo->persist_count,'duplicate completed submission creates no second report');

$conflict=f13_fixture(); $conflict['content']['description']='Payload diverso';
$third=$service->intake($conflict);
f13_assert('error'===$third['status'] && 'duplicate_submission'===$third['error']['code'],'same submission_id with different payload rejected');
f13_assert(1===$repo->create_count,'payload conflict creates no duplicate report');

$busyRepo=new F13_Fake_Repository(); $busyRepo->force_lock_fail=true;
$busy=f13_service($busyRepo)->intake(f13_fixture());
f13_assert('error'===$busy['status'] && 'submission_in_progress'===$busy['error']['code'],'concurrent lock contention returns IN_PROGRESS');
f13_assert(0===$busyRepo->create_count,'concurrent loser creates no report');

$retryRepo=new F13_Fake_Repository(); $retryRepo->fail_next_persist=true;
$retryService=f13_service($retryRepo);
$failed=$retryService->intake(f13_fixture());
f13_assert('error'===$failed['status'] && 'failed'===$retryRepo->status,'failed persistence leaves retryable intake state');
$retried=$retryService->intake(f13_fixture());
f13_assert('success'===$retried['status'] && 1===$retryRepo->create_count,'retry reuses reservation and completes without duplicate');

f13_assert(!class_exists('BadAround_WPForms_Report_Adapter',false),'F1.3 tests run without WPForms adapter/runtime');

$native_source=file_get_contents(dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-native-report-intake-service.php');
f13_assert(false===strpos($native_source,'wp_insert_post'),'native F1.3 intake performs no event creation');
f13_assert(false===strpos($native_source,'wp_set_object_terms'),'native F1.3 intake performs no taxonomy mutation');
f13_assert(false===strpos($native_source,'prepare_public_projection('),'native F1.3 intake performs no public projection');

$installer=file_get_contents(dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-installer.php');
$repository_source=file_get_contents(dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-report-repository.php');
f13_assert(false!==strpos($installer,'UNIQUE KEY source_entry (source_type,source_form_id,source_entry_id)'),'storage has unique source identity constraint');
f13_assert(false!==strpos($repository_source,'GET_LOCK'),'repository uses database named lock for concurrent native reservation');

echo "F1.3_REPORT_COUNT_DELTA=1\n";
echo "F1.3_DUPLICATE_REPORTS_CREATED=0\n";
echo "F1.3 Native Validation & Intake Foundation tests complete.\n";
