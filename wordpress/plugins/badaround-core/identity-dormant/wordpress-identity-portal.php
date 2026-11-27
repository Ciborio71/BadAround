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
    private static ?Closure $sessionStatus=null;

    public static function bind_session_status(callable $reader):void {
        self::$sessionStatus=Closure::fromCallable($reader);
    }
    public static function account_authenticated():bool {
        if (!self::enabled() || self::$sessionStatus===null)return false;
        try {return (self::$sessionStatus)()===true;}
        catch (Throwable) {return false;}
    }

    public static function enabled():bool {
        if (!defined('BADAROUND_IDENTITY_PORTAL_ENABLED')
            || BADAROUND_IDENTITY_PORTAL_ENABLED!==true
            || !BadAround_Identity_Private_Access::authorized())return false;
        if (BadAround_Identity_WordPress_Privacy_Notice::qa_mode())
            return BadAround_Identity_WordPress_Privacy_Notice::runtime_version()!==null;
        return defined('BADAROUND_IDENTITY_PRIVACY_APPROVED_VERSION')
            && BADAROUND_IDENTITY_PRIVACY_APPROVED_VERSION
                ===BadAround_Identity_WordPress_Privacy_Notice::verified_version();
    }

    public static function register_hooks():bool {
        if (!self::enabled() || !function_exists('add_shortcode')) {return false;}
        add_shortcode(self::SHORTCODE,[self::class,'render']);
        if (function_exists('add_action')) {
            add_action('wp_enqueue_scripts',[self::class,'enqueue_feedback']);
            add_action('admin_enqueue_scripts',[self::class,'enqueue_admin_feedback']);
        }
        return true;
    }

    public static function enqueue_feedback():void {
        if (!self::enabled() || !defined('BADAROUND_CORE_FILE')
            || !function_exists('wp_enqueue_style') || !function_exists('plugins_url'))return;
        wp_enqueue_script('badaround-identity-access',plugins_url('identity-dormant/identity-access.js',BADAROUND_CORE_FILE),[],'1.1.1',true);
        wp_enqueue_style('badaround-identity-feedback',
            plugins_url('identity-dormant/identity-feedback.css',BADAROUND_CORE_FILE),[],'1.1.0');
    }
    public static function enqueue_admin_feedback():void {
        if (($_GET['page']??null)==='ba-identity-review')self::enqueue_feedback();
    }

    public static function render_notice():string {
        if (!self::enabled() || !BadAround_Identity_WordPress_Privacy_Notice::qa_mode())return '';
        $record=BadAround_Identity_WordPress_Privacy_Notice::qa_record();
        return $record===null?'':'<details id="ba-identity-qa-notice"><summary>Informativa del collaudo riservato</summary>'.wp_kses_post($record['content']).'</details>';
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
        wp_enqueue_script('badaround-identity-portal',$script,['badaround-identity-access'], '1.0.0',true);
        $privacy=esc_url(home_url('/privacy-policy/'));
        $notice='';
        if (BadAround_Identity_WordPress_Privacy_Notice::qa_mode()) {
            $record=BadAround_Identity_WordPress_Privacy_Notice::qa_record();
            if ($record===null || !function_exists('wp_kses_post'))return '';
            $privacy='#ba-identity-qa-notice';
            $notice='<details id="ba-identity-qa-notice"><summary>Informativa del collaudo riservato</summary>'
                . wp_kses_post($record['content']).'</details>';
        }
        return '<section class="ba-identity-portal" data-ba-identity-portal aria-label="Accesso BadAround">'
            . '<h2 data-ba-portal-title>Accedi a BadAround</h2>'
            . '<p data-ba-portal-intro>Un solo codice numerico a 6 cifre per accedere o completare il primo accesso. Non occorre una password.</p>'
            . $notice
            . '<p><a href="'.$privacy.'">Leggi l’informativa privacy</a></p>'
            . '<div role="status" data-ba-result aria-live="polite"></div>'
            . '<div data-ba-access-entry></div>'
            . '<section data-ba-activity hidden aria-labelledby="ba-identity-my-activity-title">'
            . '<h3 id="ba-identity-my-activity-title">Le mie attività</h3>'
            . '<p>Stato delle tue richieste di commento e modifica.</p>'
            . '<button type="button" data-ba-refresh-activity>Aggiorna attività</button>'
            . '<ul data-ba-activity-list></ul>'
            . '<h3>Le mie segnalazioni</h3><ul data-ba-reports-list></ul>'
            . '</section>'
            . '<button type="button" data-ba-logout hidden>Esci</button>'
            . '</section>';
    }
}
