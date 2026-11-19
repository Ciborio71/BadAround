<?php
/** Disposable-MySQL proof test: never access production data. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-native-receipt-proof.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!str_contains($dsn,'dbname=ba_identity_ci'))throw new RuntimeException('CI DB safety guard');
$db=new PDO($dsn,(string)getenv('BA_TEST_DB_USER'),(string)getenv('BA_TEST_DB_PASS'));
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
function receiptCheck(bool $yes,string $label):void {
    if (!$yes)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$prefix='ci_proof_';
$reports=$prefix.'ba_reports';$posts=$prefix.'posts';$meta=$prefix.'postmeta';
foreach([$meta,$reports,$posts] as $t)$db->exec("DROP TABLE IF EXISTS `{$t}`");
try {
    $db->exec("CREATE TABLE `{$reports}` (
        id BIGINT UNSIGNED PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,
        content_original LONGTEXT NOT NULL,status VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL,
        source_type VARCHAR(32) NOT NULL,source_form_id BIGINT NOT NULL,
        source_entry_id BIGINT NOT NULL) ENGINE=InnoDB");
    $db->exec("CREATE TABLE `{$posts}` (
        ID BIGINT UNSIGNED PRIMARY KEY, post_type VARCHAR(32) NOT NULL,
        post_status VARCHAR(32) NOT NULL) ENGINE=InnoDB");
    $db->exec("CREATE TABLE `{$meta}` (
        post_id BIGINT UNSIGNED NOT NULL,meta_key VARCHAR(64) NOT NULL,
        meta_value VARCHAR(64) NOT NULL) ENGINE=InnoDB");
    $reader=new BadAround_Identity_WordPress_Native_Receipt_Proof($db,$prefix);
    receiptCheck(!$reader->is_new_canonical_report(0,20),'invalid IDs rejected');
    receiptCheck(!$reader->is_new_canonical_report(10,20),'nonexistent receipt rejected');
    $uuid='123e4567-e89b-42d3-a456-426614174000';
    $hash=hash('sha256',$uuid);
    $parts=[(int)hexdec(substr($hash,0,7)),(int)hexdec(substr($hash,7,15))];
    $json=json_encode(['submission_id'=>$uuid,'canonical'=>['submission_id'=>$uuid]],JSON_THROW_ON_ERROR);
    $db->prepare("INSERT INTO `{$reports}`
        (id,event_id,content_original,status,source_type,source_form_id,source_entry_id,created_at)
        VALUES (10,20,:body,'validated','native',:form,:entry,'2026-11-05 12:00:00')")->execute(
        [':body'=>$json,':form'=>$parts[0],':entry'=>$parts[1]]
    );
    $db->exec("INSERT INTO `{$posts}` VALUES (20,'ba_evento','pending')");
    receiptCheck(!$reader->is_new_canonical_report(10,20),'missing primary report binding rejected');
    $db->exec("INSERT INTO `{$meta}` VALUES (20,'_ba_primary_report_id','10')");
    receiptCheck($reader->is_new_canonical_report(10,20),'canonical report and primary event binding verified');
    $originTime=(new DateTimeImmutable('2026-11-05 12:00:00',new DateTimeZone('UTC')))->getTimestamp();
    receiptCheck($reader->created_after_reservation(10,20,$originTime),
        'newly saved report may be recovered after matching reservation time');
    receiptCheck(!$reader->created_after_reservation(10,20,$originTime+1),
        'historical report predating reservation cannot be reassigned');
    receiptCheck(!$reader->created_after_reservation(10,21,$originTime),
        'creation order proof refuses mismatched event');

    receiptCheck(!$reader->is_new_canonical_report(10,21),'foreign event cannot claim report');
    $db->exec("UPDATE `{$posts}` SET post_type='page' WHERE ID=20");
    receiptCheck(!$reader->is_new_canonical_report(10,20),'non-event post rejected');
    $db->exec("UPDATE `{$posts}` SET post_type='ba_evento' WHERE ID=20");
    $db->exec("UPDATE `{$reports}` SET status='received' WHERE id=10");
    receiptCheck(!$reader->is_new_canonical_report(10,20),'unvalidated report rejected');
    $db->exec("UPDATE `{$reports}` SET status='validated' WHERE id=10");
    $db->exec("INSERT INTO `{$meta}` VALUES (20,'_ba_primary_report_id','10')");
    receiptCheck(!$reader->is_new_canonical_report(10,20),'ambiguous primary binding rejected');
} finally {
    foreach([$meta,$reports,$posts] as $t)$db->exec("DROP TABLE IF EXISTS `{$t}`");
}
echo 'ALL_WORDPRESS_NATIVE_RECEIPT_PROOF_MYSQL_TESTS_PASS'.PHP_EOL;
