<?php
/** Exercise the unchanged service against isolated in-memory state only. */
define('ABSPATH', __DIR__);
class BadAround_Event_Post_Type { const POST_TYPE = 'ba_evento'; }
class WP_Error { public $code; function __construct($code,$message){$this->code=$code;} }
class BadAround_Audit_Log { static function transition(...$args){$GLOBALS['audit'][]=$args;} }
function absint($v){return abs((int)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));}
function sanitize_textarea_field($v){return trim(strip_tags($v));}
function current_user_can(...$args){return true;}
function __($v){return $v;}
function get_post($id){return (object)['post_type'=>'ba_evento'];}
function get_post_status($id){return 'pending';}
function get_post_meta($id,$key,$single){return $GLOBALS['meta'][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$key]=$value;return true;}
require __DIR__.'/../wordpress/plugins/badaround-core/includes/class-badaround-moderation-service.php';
$service=new BadAround_Moderation_Service();
$GLOBALS['meta']=['_ba_moderation_status'=>'new','_ba_vehicle_plate_masked'=>'AB***CD'];$GLOBALS['audit']=[];
$before=serialize([$GLOBALS['meta'],$GLOBALS['audit']]);
$result=$service->transition(9001,'rejected','');
if(!$result instanceof WP_Error||$result->code!=='ba_moderation_reason_required'||serialize([$GLOBALS['meta'],$GLOBALS['audit']])!==$before){throw new RuntimeException('Reject without reason must fail without mutation');}
if($service->transition(9001,'rejected','Synthetic offline QA')!==true||$GLOBALS['meta']['_ba_moderation_status']!=='rejected'){throw new RuntimeException('Reject with reason failed');}
$GLOBALS['meta']['_ba_moderation_status']='new';
if($service->transition(9001,'approved','')!==true||$GLOBALS['meta']['_ba_moderation_status']!=='approved'){throw new RuntimeException('Approval failed');}
echo "PASS: unchanged service rejects empty reason, accepts reason, approves without rejection reason; isolated pending context.\n";
