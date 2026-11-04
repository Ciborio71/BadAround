<?php
/**
 * CI-only enrollment race regression: two OTPs, SAME email, independent PDO
 * connections. Exactly one verified identity must be created.
 * Never run outside the disposable ba_identity_ci database.
 */
declare(strict_types=1);
require __DIR__ . '/mysql-enrollment-store.php';
require_once __DIR__ . '/mysql-challenge-store.php';

$dsn = (string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/', $dsn)
    || !extension_loaded('pdo_mysql') || !extension_loaded('pcntl')) {
    throw new RuntimeException('Safety stop: ephemeral CI MySQL + pcntl required');
}
$user = getenv('BA_TEST_DB_USER') ?: 'ba_ci';
$pass = getenv('BA_TEST_DB_PASS') ?: '';
$connect = static fn(): PDO => new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 8,
]);
$setup = $connect();
$setup->exec('CREATE TABLE IF NOT EXISTS ba_identity_pending (
    challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    protected_profile TEXT NOT NULL, created_at BIGINT UNSIGNED NOT NULL,
    verified_at BIGINT UNSIGNED NULL
) ENGINE=InnoDB');
$setup->exec('CREATE TABLE IF NOT EXISTS ba_identities_ci (
    identity_id VARCHAR(128) NOT NULL PRIMARY KEY,
    email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    protected_profile TEXT NOT NULL, verified_at BIGINT UNSIGNED NOT NULL,
    revoked_at BIGINT UNSIGNED NULL
) ENGINE=InnoDB');
$setup->exec('CREATE TABLE IF NOT EXISTS ba_identity_challenges (
    challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at BIGINT UNSIGNED NOT NULL, expires_at BIGINT UNSIGNED NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    consumed_at BIGINT UNSIGNED NULL, version BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB');
$setup->exec('DELETE FROM ba_identity_pending');
$setup->exec('DELETE FROM ba_identities_ci');
$setup->exec('DELETE FROM ba_identity_challenges');
$key = str_repeat('R', 32);
$encryptKey = str_repeat('Q', 32);
$now = 1762156800;
$profile = BadAround_Identity_Enrollment::validate_profile('Anna','Rossi','race@example.com');
$challenges = [];
$challengeStore = new BadAround_Identity_Mysql_Challenge_Store($setup,'ba_identity_challenges');
$pendingStore = new BadAround_Identity_Mysql_Enrollment_Store($setup,'ba_identity_pending','ba_identities_ci',$encryptKey);
$service = new BadAround_Identity_Enrollment(
    $pendingStore, $challengeStore,
    new BadAround_Identity_Challenge_Verifier($challengeStore,$key), $key
);
foreach ([0,1] as $i) {
    $c = BadAround_Identity_Challenge_Primitives::new_challenge($profile['email'],$key,$now);
    $challengeStore->insert($c['record']);
    $service->stage($profile,$c['record'],$now);
    $challenges[] = $c;
}
unset($service,$challengeStore,$pendingStore,$setup);

$pids = [];
$channels = [];
foreach ([0,1] as $i) {
    $pair = stream_socket_pair(STREAM_PF_UNIX,STREAM_SOCK_STREAM,STREAM_IPPROTO_IP);
    if ($pair === false) { throw new RuntimeException('Process socket failed'); }
    $pid = pcntl_fork();
    if ($pid === -1) { throw new RuntimeException('Process fork failed'); }
    if ($pid === 0) {
        fclose($pair[0]);
        try {
            $pdo = $connect();
            $ch = new BadAround_Identity_Mysql_Challenge_Store($pdo,'ba_identity_challenges');
            $pending = new BadAround_Identity_Mysql_Enrollment_Store($pdo,'ba_identity_pending','ba_identities_ci',$encryptKey);
            $enrollment = new BadAround_Identity_Enrollment(
                $pending,$ch,new BadAround_Identity_Challenge_Verifier($ch,$key),$key
            );
            fwrite($pair[1], 'R');
            if (fread($pair[1],1) !== 'G') { exit(21); }
            $result = $enrollment->verify_and_activate(
                $challenges[$i]['record']['challenge_id'],$challenges[$i]['delivery_code'],$now+1
            );
            fwrite($pair[1], $result === null ? 'L' : 'W');
            fclose($pair[1]);
            exit(0);
        } catch (Throwable $e) {
            fwrite(STDERR,'Enrollment worker exception: '.get_class($e)."\n");
            exit(22);
        }
    }
    fclose($pair[1]);
    $pids[] = $pid;
    $channels[] = $pair[0];
}
foreach ($channels as $ch) {
    stream_set_timeout($ch,20);
    if (fread($ch,1) !== 'R') { throw new RuntimeException('Race barrier failed'); }
}
foreach ($channels as $ch) { fwrite($ch,'G'); }
$results = [];
foreach ($channels as $ch) { $results[] = fread($ch,1); fclose($ch); }
foreach ($pids as $pid) {
    pcntl_waitpid($pid,$status);
    if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
        throw new RuntimeException('Enrollment process failed');
    }
}
sort($results);
if ($results !== ['L','W']) {
    throw new RuntimeException('Expected one enrollment winner, one loser');
}
$check = $connect();
$n = (int)$check->query('SELECT COUNT(*) FROM ba_identities_ci')->fetchColumn();
$used = (int)$check->query('SELECT COUNT(*) FROM ba_identity_challenges WHERE consumed_at IS NOT NULL')->fetchColumn();
$verified = (int)$check->query('SELECT COUNT(*) FROM ba_identity_pending WHERE verified_at IS NOT NULL')->fetchColumn();
if ($n !== 1 || $used !== 1 || $verified !== 1) {
    throw new RuntimeException('Race violated identity/OTP atomicity');
}
echo "PASS same-email parallel enrollment: one identity, one OTP, one pending verification\n";
echo "ALL_MYSQL_ENROLLMENT_RACE_TESTS_PASS\n";
