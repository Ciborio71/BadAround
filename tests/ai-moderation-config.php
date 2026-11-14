<?php
/** Isolated filesystem and administrative authorization tests. No API requests. */
$root = sys_get_temp_dir() . '/ba-ai-config-test-' . bin2hex(random_bytes(6));
mkdir($root, 0700); mkdir($root . '/httpdocs', 0700);
define('ABSPATH', $root . '/httpdocs/');
$hooks = []; $routes = []; $allowed = true; $ssl = true; $nonce = true;
function add_action($name, $callback) { $GLOBALS['hooks'][$name] = $callback; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['routes'][$namespace . $route] = $args; }
function current_user_can($cap) { return $GLOBALS['allowed']; }
function is_ssl() { return $GLOBALS['ssl']; }
function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); }
function wp_die($message, $title = '', $args = []) { throw new RuntimeException('denied:' . ($args['response'] ?? 0)); }
function check_admin_referer($name) { if (!$GLOBALS['nonce']) { throw new RuntimeException('invalid_nonce'); } }
function wp_unslash($value) { return stripslashes($value); }
function nocache_headers() {}
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES); }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES); }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="test-only">'; }
function submit_button(...$args) { echo '<button>Save</button>'; }
class WP_REST_Response { public function __construct(public array $data, public int $status) {} public function header($name, $value) {} }
function check($value, $label) { if (!$value) { throw new RuntimeException('FAIL: ' . $label); } echo 'PASS ' . $label . "\n"; }
require __DIR__ . '/../wordpress/mu-plugins/badaround-ai-moderation-config.php';
$file = $root . '/ba-ai-moderation-private.json';
$key = 'sk-fixture_key_that_is_not_a_real_credential_123456';
$expiry = gmdate('Y-m-d', time() + 90 * 86400);
try {
    check(array_keys($hooks) === ['admin_menu', 'admin_post_badaround_ai_config_save', 'rest_api_init'], 'no public intake, mail, cron or publication hooks');
    $status = BadAround_AI_Moderation_Config::status();
    check(!$status['credential_present'] && $status['private_storage_available'] && !$status['moderation_enabled'] && !$status['external_calls_enabled'], 'empty setup remains inactive');
    check(!BadAround_AI_Moderation_Config::save('invalid', 'qa@example.test', $expiry, ''), 'malformed key rejected');
    check(!file_exists($file), 'invalid save never creates credential file');
    check(!BadAround_AI_Moderation_Config::save($key, 'bad-email', $expiry, ''), 'invalid moderator email rejected');
    check(!BadAround_AI_Moderation_Config::save($key, 'qa@example.test', '2026-02-30', ''), 'invalid calendar date rejected');
    check(!BadAround_AI_Moderation_Config::save($key, 'qa@example.test', '2000-01-01', ''), 'expired configuration rejected');
    check(BadAround_AI_Moderation_Config::save($key, 'qa@example.test', $expiry, ''), 'credential saved outside webroot');
    check(!file_exists(ABSPATH . basename($file)) && (fileperms($file) & 0777) === 0600, 'private storage permissions and location');
    $data = BadAround_AI_Moderation_Config::read();
    check($data['api_key'] === $key, 'server can read saved credential');
    $status = BadAround_AI_Moderation_Config::status();
    check($status['credential_present'] && !$status['credential_expired'] && !$status['moderation_enabled'], 'saving does not activate AI');
    check(!str_contains(json_encode($status), $key) && !str_contains(json_encode($status), 'qa@example.test'), 'diagnostics omit credential and recipient value');
    check(!BadAround_AI_Moderation_Config::save('sk-another_fake_credential_123456789', 'qa@example.test', $expiry, ''), 'stale form cannot replace configured key');
    check(BadAround_AI_Moderation_Config::read()['api_key'] === $key, 'stale save preserves original credential');
    check(BadAround_AI_Moderation_Config::save('', 'moderator@example.test', $expiry, $data['revision']), 'empty key preserves credential during metadata update');
    $updated = BadAround_AI_Moderation_Config::read();
    check($updated['api_key'] === $key && $updated['revision'] !== $data['revision'], 'metadata update rotates revision without changing secret');
    $replacement = 'sk-replacement_fixture_credential_123456789';
    check(BadAround_AI_Moderation_Config::save($replacement, 'moderator@example.test', $expiry, $updated['revision']), 'explicit credential rotation supported');
    ob_start(); BadAround_AI_Moderation_Config::render(); $html = ob_get_clean();
    check(!str_contains($html, $replacement) && !str_contains($html, $key), 'admin HTML never redisplays the secret');
    check(str_contains($html, 'type="password"') && str_contains($html, 'method="post"') && str_contains($html, 'Moderazione AI non attiva'), 'password input, POST form and inactive notice');
    foreach ([['allowed', false], ['ssl', false]] as [$variable, $value]) {
        $GLOBALS[$variable] = $value;
        try { BadAround_AI_Moderation_Config::render(); throw new LogicException('not denied'); } catch (RuntimeException $e) { check($e->getMessage() === 'denied:403', $variable . ' gate protects admin page'); }
        $GLOBALS[$variable] = true;
    }
    $_SERVER['REQUEST_METHOD'] = 'GET';
    try { BadAround_AI_Moderation_Config::handle_save(); throw new LogicException('GET accepted'); } catch (RuntimeException $e) { check($e->getMessage() === 'denied:403', 'GET cannot mutate configuration'); }
    $_SERVER['REQUEST_METHOD'] = 'POST'; $nonce = false;
    try { BadAround_AI_Moderation_Config::handle_save(); throw new LogicException('nonce accepted'); } catch (RuntimeException $e) { check($e->getMessage() === 'invalid_nonce', 'POST requires a valid nonce'); }
    $nonce = true; $allowed = false;
    try { BadAround_AI_Moderation_Config::handle_save(); throw new LogicException('unauthorized accepted'); } catch (RuntimeException $e) { check($e->getMessage() === 'denied:403', 'unauthorized POST rejected'); }
    $allowed = true; $hooks['rest_api_init'](); $route = $routes['badaround-ops/v1/ai-moderation-readiness'];
    check($route['permission_callback'](), 'administrative readiness allowed'); $allowed = false;
    check(!$route['permission_callback'](), 'anonymous readiness forbidden'); $allowed = true;
    $response = $route['callback'](); check(!str_contains(json_encode($response->data), $replacement), 'REST response never contains key');
    chmod($file, 0644); check(BadAround_AI_Moderation_Config::read() === null, 'permissive credential file rejected'); chmod($file, 0600);
    $original = file_get_contents($file); unlink($file); file_put_contents($root . '/other.json', $original); chmod($root . '/other.json', 0600); symlink($root . '/other.json', $file);
    check(BadAround_AI_Moderation_Config::read() === null && !BadAround_AI_Moderation_Config::save($key, 'qa@example.test', $expiry, ''), 'symlink storage rejected');
    unlink($file); file_put_contents($file, $original); chmod($file, 0600);
    unlink($file . '.lock'); symlink($root . '/other.json', $file . '.lock');
    check(!BadAround_AI_Moderation_Config::save($key, 'qa@example.test', $expiry, ''), 'symlink lock rejected');
    check(file_get_contents($file) === $original, 'rejected updates preserve stored bytes');
    check(count(glob($root . '/*.tmp')) === 0, 'no temporary credential files remain');
    echo "AI_CONFIG_SUITE_PASS\n";
} finally {
    foreach (glob($root . '/*') as $path) { if (is_dir($path) && !is_link($path)) { rmdir($path); } else { unlink($path); } }
    rmdir($root);
}
