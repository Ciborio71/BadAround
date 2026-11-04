<?php
/**
 * CI-only parallel OTP consumption test against disposable MySQL.
 * Requires pcntl and pdo_mysql; will refuse any database except ba_identity_ci.
 */
declare(strict_types=1);
require __DIR__ . '/mysql-challenge-store.php';

$dsn = (string) getenv('BA_TEST_DB_DSN');
if (!str_contains($dsn, 'dbname=ba_identity_ci') || !extension_loaded('pcntl') || !extension_loaded('pdo_mysql')) {
    throw new RuntimeException('Safety stop: requires ephemeral CI MySQL and pcntl');
}
$user = getenv('BA_TEST_DB_USER') ?: 'ba_ci';
$pass = getenv('BA_TEST_DB_PASS') ?: '';
$connect = static fn(): PDO => new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$initial = $connect();
$initial->exec('CREATE TABLE IF NOT EXISTS ba_identity_challenges (
  challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  code_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at BIGINT UNSIGNED NOT NULL, expires_at BIGINT UNSIGNED NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  consumed_at BIGINT UNSIGNED NULL, version BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB');
$key = str_repeat('C', 32);
$now = 1762156800;
$challenge = BadAround_Identity_Challenge_Primitives::new_challenge('racing@example.com', $key, $now);
$store = new BadAround_Identity_Mysql_Challenge_Store($initial, 'ba_identity_challenges');
$store->insert($challenge['record']);
$id = $challenge['record']['challenge_id'];
$code = $challenge['delivery_code'];
unset($store, $initial);

$pids = [];
$channels = [];
foreach ([0,1] as $worker) {
    $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($sockets === false) { throw new RuntimeException('Cannot open process barrier'); }
    $pid = pcntl_fork();
    if ($pid === -1) { throw new RuntimeException('Cannot fork workers'); }
    if ($pid === 0) {
        fclose($sockets[0]);
        try {
            $db = $connect();
            $repo = new BadAround_Identity_Mysql_Challenge_Store($db, 'ba_identity_challenges');
            $snapshot = $repo->load($id);
            if ($snapshot === null) { exit(20); }
            $transition = BadAround_Identity_Challenge_Primitives::verify($snapshot, $code, $key, $now + 1);
            if (!$transition['ok']) { exit(21); }
            $next = $transition['record'];
            $next['version'] = $snapshot['version'] + 1;
            fwrite($sockets[1], 'R'); // both workers have read version 0
            $go = fread($sockets[1], 1);
            if ($go !== 'G') { exit(22); }
            $won = $repo->compare_and_swap($id, $snapshot['version'], $next);
            fwrite($sockets[1], $won ? 'W' : 'L');
            fclose($sockets[1]);
            exit(0);
        } catch (Throwable $exception) {
            fwrite(STDERR, 'Worker failed: '.get_class($exception)."\n");
            exit(23);
        }
    }
    fclose($sockets[1]);
    $pids[] = $pid;
    $channels[] = $sockets[0];
}

foreach ($channels as $ch) {
    if (fread($ch, 1) !== 'R') { throw new RuntimeException('Workers failed to reach barrier'); }
}
foreach ($channels as $ch) { fwrite($ch, 'G'); }
$outcomes = [];
foreach ($channels as $ch) {
    $outcomes[] = fread($ch, 1);
    fclose($ch);
}
foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
    if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
        throw new RuntimeException('Child exited unsuccessfully');
    }
}
sort($outcomes);
if ($outcomes !== ['L', 'W']) {
    throw new RuntimeException('Expected exactly one atomic winner; got '.implode(',', $outcomes));
}
$final = (new BadAround_Identity_Mysql_Challenge_Store($connect(), 'ba_identity_challenges'))->load($id);
if ($final['version'] !== 1 || $final['consumed_at'] !== $now + 1) {
    throw new RuntimeException('Unexpected persisted consumption state');
}
echo "PASS parallel OTP CAS: exactly one winner, one loser\nALL_MYSQL_CONCURRENCY_TESTS_PASS\n";
