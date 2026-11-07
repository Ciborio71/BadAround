<?php
/** Actual configuration template with a disposable DB and separate PHP processes. */
declare(strict_types=1);
$dsn=(string)getenv('BA_TEST_DB_DSN');
if(!str_contains($dsn,'dbname=ba_identity_ci'))throw new RuntimeException('CI database required');
$db=new PDO($dsn,(string)getenv('BA_TEST_DB_USER'),(string)getenv('BA_TEST_DB_PASS'),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$flags=['BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED','BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',
    'BADAROUND_IDENTITY_QA_NOTICE_ENABLED','BADAROUND_IDENTITY_REGISTRATION_ENABLED',
    'BADAROUND_IDENTITY_LOGIN_ENABLED','BADAROUND_IDENTITY_PORTAL_ENABLED',
    'BADAROUND_IDENTITY_COMMUNITY_ENABLED','BADAROUND_IDENTITY_QA_REPORTS_ENABLED'];
if(($argv[1]??null)==='--worker') {
    if(!preg_match('/host=([^;]+)/',$dsn,$host) || !preg_match('/port=([0-9]+)/',$dsn,$port))
        throw new RuntimeException('Explicit local test host and port required');
    define('DB_HOST',$host[1].':'.$port[1]);define('DB_NAME','ba_identity_ci');
    define('DB_USER',(string)getenv('BA_TEST_DB_USER'));define('DB_PASSWORD',(string)getenv('BA_TEST_DB_PASS'));
    $table_prefix='qa_cfg_';$mode=$argv[2];
    $_SERVER['REMOTE_ADDR']=$mode==='deny'||$mode==='forged'?'203.0.113.43':'203.0.113.42';
    if($mode==='forged')$_SERVER['HTTP_X_FORWARDED_FOR']='203.0.113.42';
    if($mode==='missing_peer')unset($_SERVER['REMOTE_ADDR']);
    if($mode==='existing_key')define('BADAROUND_IDENTITY_ROOT_SECRET',str_repeat('b',96));
    if($mode==='weak_existing_key')define('BADAROUND_IDENTITY_ROOT_SECRET','weak');
    if($mode==='disabled')define('BADAROUND_IDENTITY_LOGIN_ENABLED',false);
    $ok=include $argv[3];
    $active=[];foreach($flags as $f)$active[$f]=defined($f)?constant($f):null;
    echo json_encode(['ok'=>$ok,'flags'=>$active,'root_defined'=>defined('BADAROUND_IDENTITY_ROOT_SECRET'),
        'existing_key_preserved'=>defined('BADAROUND_IDENTITY_ROOT_SECRET')
            && BADAROUND_IDENTITY_ROOT_SECRET===str_repeat('b',96),
        'public_approval_defined'=>defined('BADAROUND_IDENTITY_PRIVACY_APPROVED_VERSION')]);
    exit;
}
function cfgCheck(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$db->exec('DROP TABLE IF EXISTS qa_cfg_options');
$db->exec('CREATE TABLE qa_cfg_options (option_name VARCHAR(191) PRIMARY KEY,option_value LONGTEXT NOT NULL) ENGINE=InnoDB');
$file=tempnam(sys_get_temp_dir(),'ba-qa-config-');
chmod($file,0600);
file_put_contents($file,str_replace('__GENERATED_ROOT_SECRET__',str_repeat('a',96),
    file_get_contents(__DIR__.'/../tools/templates/identity-private-config.php.in')));
$reset=static function()use($db):void {
    $db->exec('DELETE FROM qa_cfg_options');
    $q=$db->prepare('INSERT INTO qa_cfg_options VALUES (?,?)');
    foreach(['badaround_identity_qa_ip_allowlist'=>['203.0.113.42'],
        'badaround_identity_qa_email_allowlist'=>['tester@example.org'],
        'badaround_identity_qa_notice_adoption'=>['scope'=>'private_identity_qa','state'=>'owner_adopted']] as $k=>$v)
        $q->execute([$k,serialize($v)]);
};
$run=static function(string $mode)use($file):array {
    $p=proc_open([PHP_BINARY,__FILE__,'--worker',$mode,$file],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Unable to launch test process');
    fclose($pipes[0]);stream_set_timeout($pipes[1],10);stream_set_timeout($pipes[2],10);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
    if($code!==0 || $err!=='')throw new RuntimeException('Configuration worker failed');
    return json_decode($out,true,32,JSON_THROW_ON_ERROR);
};
try {
    $reset();$r=$run('allow');
    cfgCheck($r['ok']===true && !in_array(null,$r['flags'],true) && !in_array(false,$r['flags'],true),
        'exact peer and private settings enable all QA features');
    cfgCheck($r['root_defined'] && !$r['public_approval_defined'],
        'private key configured without public privacy approval');
    foreach(['deny','forged','missing_peer'] as $mode) {
        $r=$run($mode);
        cfgCheck($r['ok']===false && !$r['root_defined'] && count(array_filter($r['flags']))===0,
            $mode.' cannot activate private runtime or key');
    }
    $r=$run('existing_key');
    cfgCheck($r['ok']===true && $r['existing_key_preserved'],'existing strong key is preserved');
    $r=$run('weak_existing_key');
    cfgCheck($r['ok']===false && count(array_filter($r['flags']))===0,'weak existing key is never replaced or activated');
    $r=$run('disabled');
    cfgCheck($r['ok']===false && !$r['root_defined'] && count(array_filter($r['flags']))===0,
        'explicit disabled feature never overridden');
    $q=$db->prepare('UPDATE qa_cfg_options SET option_value=? WHERE option_name=?');
    foreach([[],['*'],['203.0.113.42','203.0.113.43']] as $bad) {
        $reset();$q->execute([serialize($bad),'badaround_identity_qa_ip_allowlist']);$r=$run('allow');
        cfgCheck($r['ok']===false && !$r['root_defined'],'invalid or expanded peer list rejected');
    }
    $reset();$q->execute([serialize(['other@example.org','tester@example.org']),'badaround_identity_qa_email_allowlist']);
    cfgCheck($run('allow')['ok']===false,'test configuration requires exactly one explicit inbox');
    $reset();$q->execute([serialize(['scope'=>'public','state'=>'owner_adopted']),'badaround_identity_qa_notice_adoption']);
    cfgCheck($run('allow')['ok']===false,'public notice scope cannot activate private test configuration');
}finally {
    $db->exec('DROP TABLE IF EXISTS qa_cfg_options');unlink($file);
}
echo "ALL_PRIVATE_CONFIG_MYSQL_TESTS_PASS\n";
