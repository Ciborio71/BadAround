<?php
/**
 * BA-IDENTITY-02C — private-QA identity portal shortcode.
 * Not loaded by Core unless all private security gates are verified.
 * The current Privacy Policy is a legal DRAFT: this portal must remain
 * disabled until a reviewed version is explicitly approved.
 */
declare(strict_types=1);
require_once __DIR__.'/wordpress-private-access.php';
require_once __DIR__.'/wordpress-privacy-notice.php';

final class BadAround_Identity_WordPress_Portal {
    public const SHORTCODE='badaround_identity_portal';

    public static function enabled():bool {
        return defined('BADAROUND_IDENTITY_PORTAL_ENABLED')
            && BADAROUND_IDENTITY_PORTAL_ENABLED===true
            && BadAround_Identity_Private_Access::authorized()
            && defined('BADAROUND_IDENTITY_PRIVACY_APPROVED_VERSION')
            && BADAROUND_IDENTITY_PRIVACY_APPROVED_VERSION
                ===BadAround_Identity_WordPress_Privacy_Notice::verified_version();
    }

    public static function register_hooks():bool {
        if (!self::enabled() || !function_exists('add_shortcode')) {return false;}
        add_shortcode(self::SHORTCODE,[self::class,'render']);
        return true;
    }

    public static function render():string {
        if (!self::enabled()
            || !function_exists('plugins_url')
            || !defined('BADAROUND_CORE_FILE')
            || !function_exists('wp_enqueue_script')
            || !function_exists('esc_url')) {
            return '';
        }
        // External JavaScript only. Do not inline tokens, WP nonces, email,
        // raw passwordless bearer secrets or user profile data.
        $script=plugins_url('identity-dormant/identity-portal.js',BADAROUND_CORE_FILE);
        wp_enqueue_script('badaround-identity-portal',$script,[], '0.2.0',true);
        $privacy=esc_url(home_url('/privacy-policy/'));
        return '<section class="ba-identity-portal" data-ba-identity-portal aria-label="Accesso BadAround">'
            . '<h2>Accedi a BadAround</h2>'
            . '<p>Utilizziamo un codice temporaneo inviato via email. Non occorre una password.</p>'
            . '<div role="status" data-ba-result aria-live="polite"></div>'
            . '<form data-ba-form="register" autocomplete="on">'
            . '<h3>Registrati</h3>'
            . '<label>Nome <input name="first_name" autocomplete="given-name" maxlength="191" required></label>'
            . '<label>Cognome <input name="last_name" autocomplete="family-name" maxlength="191" required></label>'
            . '<label>Email <input name="email" type="email" autocomplete="email" maxlength="191" required></label>'
            . '<label><input name="privacy_acknowledged" type="checkbox" required> '
            . 'Dichiaro di aver letto l\' <a href="'.$privacy.'" target="_blank" rel="noopener noreferrer">informativa privacy</a>.</label>'
            . '<button type="submit">Ricevi il codice di registrazione</button>'
            . '</form>'
            . '<form data-ba-form="login" autocomplete="on">'
            . '<h3>Hai già un account?</h3>'
            . '<label>Email <input name="email" type="email" autocomplete="email" maxlength="191" required></label>'
            . '<button type="submit">Ricevi il codice di accesso</button>'
            . '</form>'
            . '<form data-ba-form="otp" hidden>'
            . '<h3>Verifica il codice</h3>'
            . '<label>Codice a 8 cifre <input name="code" inputmode="numeric" pattern="[0-9]{8}" '
            . 'autocomplete="one-time-code" minlength="8" maxlength="8" required></label>'
            . '<button type="submit">Verifica</button>'
            . '</form>'
            . '<section data-ba-activity hidden aria-labelledby="ba-identity-my-activity-title">'
            . '<h3 id="ba-identity-my-activity-title">Le mie attività</h3>'
            . '<p>Stato delle tue richieste di commento e modifica.</p>'
            . '<button type="button" data-ba-refresh-activity>Aggiorna attività</button>'
            . '<ul data-ba-activity-list></ul>'
            . '</section>'
            . '<button type="button" data-ba-logout hidden>Esci</button>'
            . '</section>';
    }
}
