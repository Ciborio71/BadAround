<?php
/* Pure installer control-flow/DDL capture: no DB connection, no WordPress/site execution. */
$repo=$argv[1]??getcwd();$tmp=sys_get_temp_dir().'/ba-f17f-installer-'.bin2hex(random_bytes(8));
mkdir($tmp.'/wp-admin/includes',0700,true);file_put_contents($tmp.'/wp-admin/includes/upgrade.php','<?php');
define('ABSPATH',$tmp.'/');
$GLOBALS['version']='1.8.0';$GLOBALS['calls']=[];
function get_option($name){return $GLOBALS['version'];}
function update_option($name,$version,$autoload){$GLOBALS['version']=$version;}
function get_role($name){return null;}
function taxonomy_exists($name){return false;}
function dbDelta($sql){$GLOBALS['calls'][]=$sql;}
class BadAround_Event_Post_Type {const EVENT_TYPE_TAX='ba_tipo_evento';}
class DryDB {public $prefix='dry_';function get_charset_collate(){return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';}}
$GLOBALS['wpdb']=new DryDB();
require $repo.'/wordpress/plugins/badaround-core/includes/class-badaround-native-media-ledger.php';
require $repo.'/wordpress/plugins/badaround-core/includes/class-badaround-installer.php';
require $repo.'/wordpress/plugins/badaround-core/includes/class-badaround-native-media-config.php';
function verify($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label."\n";}
try {
 $installer=new BadAround_Installer();$installer->maybe_upgrade();
 verify($GLOBALS['version']==='1.9.0'&&count($GLOBALS['calls'])===1,'1.8 -> 1.9 installer dispatch/version gate');
 $sql=$GLOBALS['calls'][0];$installer->maybe_upgrade();verify(count($GLOBALS['calls'])===1,'second maybe_upgrade is a no-op at 1.9');
 $GLOBALS['version']='1.8.0';$installer->maybe_upgrade();verify(count($GLOBALS['calls'])===2&&$GLOBALS['calls'][1]===$sql,'recovery rerun produces identical DDL');
 preg_match_all('/CREATE TABLE dry_ba_native_media_([a-z_]+)/',$sql,$matches);
 verify($matches[1]===['sessions','items','upload_keys','limits','operations'],'exactly five native table declarations');
 verify(!preg_match('/\b(DROP|TRUNCATE|DELETE|RENAME)\b/i',$sql),'no destructive DDL/data statements');
 verify(str_contains($sql,'report_id bigint(20) unsigned NOT NULL'),'final media report_id remains nonnullable');
 $config=new BadAround_Native_Media_Config();verify($config->get('enabled')===false&&!$config->enabled(),'media disabled by default');
 verify($config->get('keys')===[]&&$config->get('active_key_id')==='','no default capability secret/key ring');
 verify($config->get('temporary_budget')===0&&$config->get('archive_budget')===0,'temporary/archive budgets default zero');
 verify($config->get('hosting_verified')===false,'hosting proof not assumed');
 echo "F1.7F dry probe: 10 checks PASS; no database accessed; dbDelta execution is stubbed, not hosting proof.\n";
} finally {unlink($tmp.'/wp-admin/includes/upgrade.php');rmdir($tmp.'/wp-admin/includes');rmdir($tmp.'/wp-admin');rmdir($tmp);}
