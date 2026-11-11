<?php
/** Evaluate only the owning maintenance predicates, no live WordPress or writes. */
function is_user_logged_in(){return $GLOBALS['logged']??false;}
function is_admin(){return false;}function wp_doing_ajax(){return false;}function wp_doing_cron(){return false;}
function has_filter($hook,$callback){return ($GLOBALS['registered']??false)?6:false;}
class BadAround_Identity_QA_Report_REST{static function enabled(){return $GLOBALS['allowed']??false;}}
$s=file_get_contents(__DIR__.'/../wordpress/themes/badaround-child/functions.php');
$a=strpos($s,'function badaround_child_maintenance_enabled()');$b=strpos($s,'function badaround_child_render_maintenance()');
eval(substr($s,$a,$b-$a));
function verify($v,$label){if(!$v)throw new RuntimeException($label);echo "PASS $label\n";}
verify(!badaround_child_is_maintenance_exempt(),'ordinary guest retains maintenance');
$GLOBALS['allowed']=true;verify(!badaround_child_is_maintenance_exempt(),'partial bootstrap cannot expose pages');
$GLOBALS['registered']=true;verify(badaround_child_is_maintenance_exempt(),'fully authorized QA sees pages');
$GLOBALS['allowed']=false;verify(!badaround_child_is_maintenance_exempt(),'non-allowlisted peer remains blocked');
$GLOBALS['logged']=true;verify(badaround_child_is_maintenance_exempt(),'existing WordPress management access preserved');
verify(str_contains($s,"header('Cache-Control: private, no-store, max-age=0', true)")&&str_contains($s,"header('X-Robots-Tag: noindex, nofollow, noarchive', true)"),'QA content stays private and noindex');
echo "MAINTENANCE_PRIVATE_QA_PASS\n";
