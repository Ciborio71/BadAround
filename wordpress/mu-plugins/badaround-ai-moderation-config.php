<?php
/**
 * Plugin Name: BadAround AI moderation configuration
 * Description: Private credential setup only. No AI calls, mail, cron or publication hooks.
 */
if (!defined('ABSPATH')) { exit; }

final class BadAround_AI_Moderation_Config {
    private const FILE = 'ba-ai-moderation-private.json';

    private static function path(): ?string {
        $webroot = realpath(ABSPATH);
        if ($webroot === false) { return null; }
        $parent = realpath(dirname($webroot));
        return $parent && $parent !== $webroot ? $parent . '/' . self::FILE : null;
    }

    private static function safe_file(string $path): bool {
        clearstatcache(true, $path);
        if (is_link($path) || !is_file($path)) { return false; }
        $mode = fileperms($path);
        if ($mode === false || ($mode & 0077) !== 0) { return false; }
        return !function_exists('posix_geteuid') || fileowner($path) === posix_geteuid();
    }

    private static function valid_key($key): bool {
        return is_string($key) && preg_match('/\Ask-[A-Za-z0-9_-]{16,508}\z/D', $key) === 1;
    }

    private static function valid_date($value): bool {
        if (!is_string($value) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $value)) { return false; }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year);
    }

    private static function decode(string $bytes): ?array {
        $data = json_decode($bytes, true);
        if (!is_array($data) || ($data['version'] ?? null) !== 1
            || !self::valid_key($data['api_key'] ?? null)
            || !is_string($data['moderator_email'] ?? null) || !is_email($data['moderator_email'])
            || !self::valid_date($data['expires_on'] ?? null)
            || !is_string($data['revision'] ?? null) || !preg_match('/\A[a-f0-9]{32}\z/D', $data['revision'])) { return null; }
        return $data;
    }

    /** Server-side use only; never expose this result through a REST response or HTML. */
    public static function read(): ?array {
        $path = self::path();
        if (!$path || !self::safe_file($path) || filesize($path) > 8192) { return null; }
        $bytes = @file_get_contents($path);
        return is_string($bytes) ? self::decode($bytes) : null;
    }

    public static function status(): array {
        $path = self::path();
        $data = self::read();
        return [
            'credential_present' => $data !== null,
            'credential_expired' => $data !== null && $data['expires_on'] < gmdate('Y-m-d'),
            'expires_on' => $data['expires_on'] ?? null,
            'moderator_email_configured' => $data !== null,
            'private_storage_available' => $path !== null && is_writable(dirname($path)),
            'private_file_secure' => $path !== null && self::safe_file($path),
            'moderation_enabled' => false,
            'external_calls_enabled' => false,
        ];
    }

    /** Atomic private-file update. Empty key preserves the configured credential. */
    public static function save(string $key, string $email, string $expires, string $expected): bool {
        $path = self::path();
        if (!$path || !is_writable(dirname($path)) || !is_email($email) || !self::valid_date($expires)
            || $expires < gmdate('Y-m-d') || strlen($email) > 191) { return false; }
        $lockPath = $path . '.lock';
        $lock = null; $temporary = null; $priorMask = umask(0077);
        try {
            if (file_exists($lockPath) || is_link($lockPath)) {
                if (!self::safe_file($lockPath)) { return false; }
                $lock = @fopen($lockPath, 'r+b');
            } else { $lock = @fopen($lockPath, 'x+b'); }
            if (!$lock || !@flock($lock, LOCK_EX) || !self::safe_file($lockPath)) { return false; }
            $exists = file_exists($path) || is_link($path);
            $current = self::read();
            if (($exists && !$current) || !hash_equals($current['revision'] ?? '', $expected)) { return false; }
            if ($key === '') { $key = $current['api_key'] ?? ''; }
            if (!self::valid_key($key)) { return false; }
            $data = ['version' => 1, 'api_key' => $key, 'moderator_email' => $email,
                'expires_on' => $expires, 'revision' => bin2hex(random_bytes(16))];
            $bytes = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $temporary = $path . '.' . bin2hex(random_bytes(12)) . '.tmp';
            $handle = @fopen($temporary, 'x+b');
            if (!$handle) { return false; }
            try {
                if (!@chmod($temporary, 0600) || !self::safe_file($temporary)
                    || fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) { return false; }
            } finally { fclose($handle); }
            if (@file_get_contents($temporary) !== $bytes || !@rename($temporary, $path)) { return false; }
            $temporary = null;
            return self::safe_file($path) && @file_get_contents($path) === $bytes;
        } catch (Throwable $error) {
            // Deliberately do not log exceptions or submitted credentials.
            return false;
        } finally {
            if ($temporary !== null && is_file($temporary)) { @unlink($temporary); }
            if (is_resource($lock)) { @flock($lock, LOCK_UN); fclose($lock); }
            umask($priorMask);
        }
    }

    public static function register_page(): void {
        add_management_page('Moderazione AI BadAround', 'Moderazione AI', 'manage_options', 'badaround-ai-moderation', [self::class, 'render']);
    }

    public static function handle_save(): void {
        if (!current_user_can('manage_options') || !is_ssl() || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die('Operazione non consentita.', '', ['response' => 403]); return;
        }
        check_admin_referer('badaround_ai_config_save');
        $values = [];
        foreach (['api_key', 'moderator_email', 'expires_on', 'revision'] as $name) {
            if (!isset($_POST[$name]) || !is_string($_POST[$name]) || strlen($_POST[$name]) > 1024) {
                wp_die('Dati non validi.', '', ['response' => 400]); return;
            }
            $values[$name] = trim(wp_unslash($_POST[$name]));
        }
        $saved = self::save($values['api_key'], $values['moderator_email'], $values['expires_on'], $values['revision']);
        wp_safe_redirect(admin_url('tools.php?page=badaround-ai-moderation&setup=' . ($saved ? 'saved' : 'failed')));
        exit;
    }

    public static function render(): void {
        if (!current_user_can('manage_options') || !is_ssl()) { wp_die('Accesso amministratore via HTTPS richiesto.', '', ['response' => 403]); return; }
        nocache_headers();
        $data = self::read();
        $status = self::status();
        echo '<div class="wrap"><h1>Moderazione AI BadAround</h1>';
        echo '<p><strong>Moderazione AI non attiva.</strong> Questa pagina configura soltanto la chiave e l’email del moderatore. Il salvataggio non invia dati a OpenAI, non invia email e non pubblica segnalazioni.</p>';
        if (($_GET['setup'] ?? '') === 'saved') { echo '<div class="notice notice-success"><p>Configurazione salvata. La chiave non viene mostrata nuovamente.</p></div>'; }
        if (($_GET['setup'] ?? '') === 'failed') { echo '<div class="notice notice-error"><p>Salvataggio non completato. Ricarica la pagina e controlla i campi. Se il problema persiste, occorre verificare lo spazio privato sul server.</p></div>'; }
        if (!$status['private_storage_available']) { echo '<div class="notice notice-error"><p>Lo spazio privato sul server non è scrivibile: non inserire la chiave finché la configurazione del server non è stata completata.</p></div>'; }
        echo '<p>Chiave: <strong>' . ($data ? 'presente' : 'non configurata') . '</strong>. Verifica di validità con OpenAI: <strong>non ancora eseguita</strong>.</p>';
        if ($status['credential_expired']) { echo '<div class="notice notice-warning"><p>La scadenza indicata per la chiave è trascorsa: sostituiscila prima di utilizzare l’integrazione.</p></div>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" autocomplete="off"><input type="hidden" name="action" value="badaround_ai_config_save">';
        wp_nonce_field('badaround_ai_config_save');
        echo '<input type="hidden" name="revision" value="' . esc_attr($data['revision'] ?? '') . '"><table class="form-table"><tbody>';
        echo '<tr><th><label for="ba-ai-key">Chiave API OpenAI</label></th><td><input id="ba-ai-key" name="api_key" type="password" class="regular-text" maxlength="512" autocomplete="new-password" spellcheck="false" ' . ($data ? '' : 'required') . '><p class="description">Incolla qui la chiave del progetto BadAround. Viene salvata sul server fuori dalla cartella pubblica del sito. Per mantenerla invariata lascia vuoto il campo dopo il primo salvataggio.</p></td></tr>';
        echo '<tr><th><label for="ba-ai-email">Email del moderatore</label></th><td><input id="ba-ai-email" name="moderator_email" type="email" class="regular-text" maxlength="191" required value="' . esc_attr($data['moderator_email'] ?? '') . '"><p class="description">Riceverà le proposte di moderazione quando la funzione sarà attivata.</p></td></tr>';
        echo '<tr><th><label for="ba-ai-expiry">Scadenza della chiave</label></th><td><input id="ba-ai-expiry" name="expires_on" type="date" required value="' . esc_attr($data['expires_on'] ?? '') . '"><p class="description">Indica la data impostata su OpenAI. Questo campo non modifica la scadenza della chiave.</p></td></tr>';
        echo '</tbody></table>';
        submit_button('Salva configurazione', 'primary', 'submit', true, $status['private_storage_available'] ? [] : ['disabled' => 'disabled']);
        echo '</form><p>Le regole di moderazione e la conferma umana saranno configurate nella fase successiva.</p></div>';
    }
}

add_action('admin_menu', [BadAround_AI_Moderation_Config::class, 'register_page']);
add_action('admin_post_badaround_ai_config_save', [BadAround_AI_Moderation_Config::class, 'handle_save']);
add_action('rest_api_init', static function () {
    register_rest_route('badaround-ops/v1', '/ai-moderation-readiness', [
        'methods' => 'GET', 'permission_callback' => static function () { return current_user_can('manage_options'); },
        'callback' => static function () {
            $response = new WP_REST_Response(BadAround_AI_Moderation_Config::status(), 200);
            $response->header('Cache-Control', 'private, no-store, max-age=0');
            return $response;
        },
    ]);
});
