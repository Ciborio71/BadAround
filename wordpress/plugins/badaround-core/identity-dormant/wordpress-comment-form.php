<?php
/**
 * BA-IDENTITY-02F: private QA event-comment form; no public content writes.
 * Only published canonical event IDs resolved server-side. Replies and
 * pseudonym projections require trusted read models and remain disabled.
 */
declare(strict_types=1);
require_once __DIR__.'/wordpress-identity-portal.php';

final class BadAround_Identity_WordPress_Comment_Form {
    public const SHORTCODE='badaround_identity_comment_form';
    public static function enabled():bool {
        return BadAround_Identity_WordPress_Portal::enabled()
            && defined('BADAROUND_IDENTITY_COMMUNITY_ENABLED')
            && BADAROUND_IDENTITY_COMMUNITY_ENABLED===true;
    }
    public static function register_hooks():bool {
        if (!self::enabled() || !function_exists('add_shortcode')) {return false;}
        add_shortcode(self::SHORTCODE,[self::class,'render']);
        return true;
    }
    public static function render():string {
        if (!self::enabled()
            || !function_exists('get_queried_object_id')
            || !function_exists('get_post')
            || !function_exists('get_post_meta')
            || !function_exists('plugins_url')
            || !function_exists('wp_enqueue_script')
            || !defined('BADAROUND_CORE_FILE')) {return '';}
        $eventId=(int)get_queried_object_id();
        if($eventId<=0){return '';}
        $event=get_post($eventId);
        if (!is_object($event)
            || ($event->post_type??null)!=='ba_evento'
            || ($event->post_status??null)!=='publish'
            || (string)get_post_meta($eventId,'_ba_moderation_status',true)!=='published') {
            return '';
        }
        $script=plugins_url('identity-dormant/identity-comment-form.js',BADAROUND_CORE_FILE);
        wp_enqueue_script('badaround-identity-comment-form',$script,[], '0.3.0',true);
        return '<section id="commenti" class="ba-card ba-event-section ba-identity-community" data-ba-comment-form data-event-id="'.(int)$eventId.'">'
            . '<h2>'.htmlspecialchars(class_exists('BadAround_Event_SEO')?BadAround_Event_SEO::section_headings($eventId)['comments']:'Commenti della community',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</h2>'
            . (class_exists('BadAround_Identity_Community_Review',false)?BadAround_Identity_Community_Review::render_comments($eventId):'')
            . '<h3>Commenta la segnalazione</h3>'
            . '<p>Scrivi qui aggiornamenti destinati alla community. Per l’invio serve accedere; il testo sarà pubblicato solo dopo approvazione. Per dati personali o dettagli delicati usa il <a href="#contribuisci">modulo riservato</a>.</p>'
            . '<div role="status" data-ba-comment-status aria-live="polite"></div><button type="button" data-ba-comment-login hidden>Accedi per inviare il commento</button><div data-ba-comment-access hidden></div>'
            . '<form>'
            . '<label>Commento <textarea name="body" required minlength="3" maxlength="2500" rows="4"></textarea></label>'
            . '<input type="hidden" name="parent_id" value="">'
            . '<p data-ba-reply-status></p><button type="button" data-ba-cancel-reply hidden>Annulla risposta</button>'
            . '<label>Come vuoi comparire? <select name="display_mode"><option value="anonymous">Anonimo</option><option value="pseudonym">Pseudonimo</option></select></label>'
            . '<label data-ba-alias-field hidden>Pseudonimo pubblico <input name="alias" maxlength="80"></label>'
            . '<p>Nome, cognome ed email del tuo account rimangono privati.</p>'
            . '<button type="submit">Invia il commento</button>'
            . '<p class="ba-moderation-note">I commenti sono soggetti a moderazione e vengono pubblicati solo dopo approvazione.</p>'
            . '</form>'
            . '<details data-ba-revision><summary>Proponi una modifica alla tua segnalazione</summary>'
            . '<p>Solo chi ha creato la segnalazione può richiederne una modifica. La proposta sarà sottoposta a moderazione.</p>'
            . '<div role="status" data-ba-revision-status aria-live="polite"></div>'
            . '<form data-ba-revision-form>'
            . '<label>Descrizione aggiornata <textarea name="description" required minlength="10" maxlength="5000" rows="5"></textarea></label>'
            . '<label>Motivo della modifica <textarea name="reason" maxlength="500" rows="2"></textarea></label>'
            . '<button type="submit">Invia modifica per moderazione</button>'
            . '</form></details>'
            . '</section>';
    }
}
