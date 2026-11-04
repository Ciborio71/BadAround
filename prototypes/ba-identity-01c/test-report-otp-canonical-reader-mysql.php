<?php
/** BA-REPORT-IDENTITY-05: disposable WordPress-shaped DB fixtures. */
declare(strict_types=1);
require __DIR__.'/report-otp-canonical-reader.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if(!str_contains($dsn,'dbname=ba_identity_ci'))throw new RuntimeException('Disposable database required');
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_canon_ci_';
$db->exec("CREATE TABLE IF NOT EXISTS `{$prefix}ba_reports` (id BIGINT PRIMARY KEY,
 source_type VARCHAR(24),source_form_id BIGINT,source_entry_id BIGINT,
 event_id BIGINT,status VARCHAR(24),content_original LONGTEXT) ENGINE=InnoDB");
$db->exec("CREATE TABLE IF NOT EXISTS `{$prefix}posts` (ID BIGINT PRIMARY KEY,
 post_type VARCHAR(40),post_status VARCHAR(24)) ENGINE=InnoDB");
$db->exec("CREATE TABLE IF NOT EXISTS `{$prefix}postmeta` (meta_id BIGINT AUTO_INCREMENT PRIMARY KEY,
 post_id BIGINT,meta_key VARCHAR(191),meta_value LONGTEXT) ENGINE=InnoDB");
foreach(['ba_reports','posts','postmeta'] as $table)$db->exec("DELETE FROM `{$prefix}{$table}`");
$uuid='12345678-1234-4234-8234-123456789abc';
$h=hash('sha256',$uuid);
$form=(int)hexdec(substr($h,0,7));
$entry=(int)hexdec(substr($h,7,15));
$payload=json_encode(['submission_id'=>$uuid,'canonical'=>['submission_id'=>$uuid]],JSON_THROW_ON_ERROR);
$stmt=$db->prepare("INSERT INTO `{$prefix}ba_reports`
 (id,source_type,source_form_id,source_entry_id,event_id,status,content_original)
 VALUES (11,'native',:form,:entry,22,'validated',:payload)");
$stmt->execute([':form'=>$form,':entry'=>$entry,':payload'=>$payload]);
$db->exec("INSERT INTO `{$prefix}posts` VALUES (22,'ba_evento','pending')");
$db->exec("INSERT INTO `{$prefix}postmeta` (post_id,meta_key,meta_value)
 VALUES (22,'_ba_primary_report_id','11')");
$reader=new BadAround_Report_Otp_Canonical_Reader($db,$prefix);
function pass_read(bool $good,string $label):void{
 if(!$good)throw new RuntimeException('FAIL '.$label);
 echo 'PASS '.$label.PHP_EOL;
}
$receipt=$reader->completed($uuid);
pass_read(($receipt['report_id']??null)===11&&($receipt['event_id']??null)===22,
 'trusted canonical report and event linkage');
pass_read(!isset($receipt['content_original'])&&!isset($receipt['email']),
 'private content never returned');
pass_read($reader->completed('bad')===null,'invalid UUID denied');
$db->exec("UPDATE `{$prefix}ba_reports` SET status='failed' WHERE id=11");
pass_read($reader->completed($uuid)===null,'failed report never confirmed');
$db->exec("UPDATE `{$prefix}ba_reports` SET status='validated' WHERE id=11");
$db->exec("UPDATE `{$prefix}postmeta` SET meta_value='99' WHERE post_id=22");
pass_read($reader->completed($uuid)===null,'mismatched event ownership denied');
$db->exec("UPDATE `{$prefix}postmeta` SET meta_value='11' WHERE post_id=22");
$db->exec("UPDATE `{$prefix}posts` SET post_status='trash' WHERE ID=22");
pass_read($reader->completed($uuid)===null,'trashed event denied');
echo "ALL_REPORT_CANONICAL_READER_TESTS_PASS\n";
